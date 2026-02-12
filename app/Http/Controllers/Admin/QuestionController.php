<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ArrayExport;
use App\Models\Lesson;
use App\Models\Question;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\Storage;

class QuestionController extends Controller
{
    public function index(Lesson $lesson)
    {
        $query = $lesson->questions()
            ->where(function ($builder) {
                $builder->where('mode', 'practice')->orWhereNull('mode');
            })
            ->orderBy('id');
        $lesson->setRelation('questions', $query->get());

        return view('admin.questions.index', [
            'lesson' => $lesson,
        ]);
    }

    public function create(Lesson $lesson)
    {
        return view('admin.questions.create', compact('lesson'));
    }

    public function store(Request $request, Lesson $lesson)
    {
        $data = $request->validate([
            'type' => ['required', 'in:mcq,tfng,completion,matching'],
            'prompt' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string'],
            'correct_answer' => ['required', 'string'],
            'ai_explanation' => ['nullable', 'string'],
            'matching_items' => ['nullable', 'string'],
        ]);

        $prepared = $this->buildQuestionPayload($data);
        if (isset($prepared['error'])) {
            return back()->withErrors(['prompt' => $prepared['error']])->withInput();
        }

        $lesson->questions()->create($prepared);

        return redirect()->route('admin.questions.index', $lesson);
    }

    public function import(Request $request, Lesson $lesson)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $data['csv'];
        if (!$file->isValid()) {
            return redirect()
                ->route('admin.questions.index', $lesson)
                ->with('import_errors', [__('app.import_failed')]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $defaultHeader = ['question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'ai_explanation', 'matching_items'];
        $legacyHeader = ['prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'ai_explanation'];
        $headerMap = null;
        $imported = 0;
        $errors = [];
        $lineNumber = 1;

        $processRow = function (array $row) use ($lesson, &$imported, &$errors, $defaultHeader, $legacyHeader, &$lineNumber, &$headerMap) {
            $lineNumber += 1;
            if (count($row) === 1 && trim($row[0] ?? '') === '') {
                return;
            }

            if (is_array($headerMap) && !empty($headerMap)) {
                $rowData = [];
                foreach ($defaultHeader as $field) {
                    $index = $headerMap[$field] ?? null;
                    $rowData[$field] = $index !== null ? ($row[$index] ?? null) : null;
                }
            } else {
                $firstCell = strtolower(trim((string) ($row[0] ?? '')));
                $headerToUse = in_array($firstCell, ['mcq', 'tfng', 'completion', 'matching'], true)
                    ? $defaultHeader
                    : $legacyHeader;
                $rowData = array_combine($headerToUse, array_pad($row, count($headerToUse), null));
                if ($headerToUse === $legacyHeader) {
                    $rowData['question_type'] = $rowData['question_type'] ?? 'mcq';
                    $rowData['matching_items'] = $rowData['matching_items'] ?? '';
                }
            }
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

            $type = $this->normalizeQuestionType($rowData['question_type'] ?? null);
            $prompt = trim((string) ($rowData['prompt'] ?? ''));
            $options = [
                trim((string) ($rowData['option_a'] ?? '')),
                trim((string) ($rowData['option_b'] ?? '')),
                trim((string) ($rowData['option_c'] ?? '')),
                trim((string) ($rowData['option_d'] ?? '')),
            ];

            $correctRaw = trim((string) ($rowData['correct_answer'] ?? ''));

            if ($prompt === '') {
                $errors[] = __('app.import_error_prompt', ['line' => $lineNumber]);
                return;
            }

            $payload = $this->buildQuestionPayload([
                'type' => $type,
                'prompt' => $prompt,
                'options' => $options,
                'correct_answer' => $correctRaw,
                'ai_explanation' => trim((string) ($rowData['ai_explanation'] ?? '')) ?: null,
                'matching_items' => (string) ($rowData['matching_items'] ?? ''),
            ]);
            if (isset($payload['error'])) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]).' '.$payload['error'];
                return;
            }

            $lesson->questions()->create($payload);

            $imported += 1;
        };

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $rows = Excel::toArray([], $file)[0] ?? [];
            if (empty($rows)) {
                return redirect()
                    ->route('admin.questions.index', $lesson)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $header = array_map('trim', $rows[0] ?? []);
            if (!empty($header)) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            $hasHeader = in_array('prompt', $header, true);
            $startIndex = 0;
            if ($hasHeader) {
                $lineNumber = 1;
                $headerMap = $this->buildHeaderMap($header);
                $startIndex = 1;
            } else {
                $processRow($header);
                $startIndex = 1;
            }

            for ($i = $startIndex; $i < count($rows); $i++) {
                $processRow($rows[$i]);
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return redirect()
                    ->route('admin.questions.index', $lesson)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);

                return redirect()
                    ->route('admin.questions.index', $lesson)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = str_getcsv($firstLine, $delimiter);
            $header = array_map('trim', $header);
            if (!empty($header)) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }

            $hasHeader = in_array('prompt', $header, true);
            if ($hasHeader) {
                $lineNumber = 1;
                $headerMap = $this->buildHeaderMap($header);
            } else {
                $processRow($header);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $processRow($row);
            }

            fclose($handle);
        }

        return redirect()
            ->route('admin.questions.index', $lesson)
            ->with('imported', $imported)
            ->with('import_errors', $errors);
    }

    public function downloadSample(Request $request, Lesson $lesson)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $filename = $format === 'csv' ? 'lesson_questions_sample.csv' : 'lesson_questions_sample.xlsx';
        $path = 'samples/'.$filename;

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($path), $filename);
    }

    public function export(Request $request, Lesson $lesson)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $questions = $lesson->questions()->orderBy('id')->get();

        $headings = [
            'question_type',
            'prompt',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_answer',
            'ai_explanation',
            'matching_items',
        ];

        $rows = $questions->map(function (Question $question) {
            $options = is_array($question->options) ? $question->options : [];
            $matchingItems = (string) collect((array) data_get($question->meta, 'items', []))
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->implode('|');
            return [
                $question->type ?? 'mcq',
                $question->prompt,
                $options[0] ?? '',
                $options[1] ?? '',
                $options[2] ?? '',
                $options[3] ?? '',
                $question->correct_answer,
                $question->ai_explanation,
                $matchingItems,
            ];
        })->all();

        $export = new ArrayExport($headings, $rows);
        $filename = 'lesson-'.$lesson->id.'-questions-'.now()->format('Ymd-His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    public function edit(Lesson $lesson, Question $question)
    {
        $this->ensureLessonMatch($lesson, $question);

        return view('admin.questions.edit', compact('lesson', 'question'));
    }

    public function update(Request $request, Lesson $lesson, Question $question)
    {
        $this->ensureLessonMatch($lesson, $question);

        $data = $request->validate([
            'type' => ['required', 'in:mcq,tfng,completion,matching'],
            'prompt' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string'],
            'correct_answer' => ['required', 'string'],
            'ai_explanation' => ['nullable', 'string'],
            'matching_items' => ['nullable', 'string'],
        ]);

        $prepared = $this->buildQuestionPayload($data);
        if (isset($prepared['error'])) {
            return back()->withErrors(['prompt' => $prepared['error']])->withInput();
        }

        $question->update($prepared);

        return redirect()->route('admin.questions.index', $lesson);
    }

    public function destroy(Lesson $lesson, Question $question)
    {
        $this->ensureLessonMatch($lesson, $question);
        $question->delete();

        return redirect()->route('admin.questions.index', $lesson);
    }

    private function ensureLessonMatch(Lesson $lesson, Question $question): void
    {
        if ($question->lesson_id !== $lesson->id) {
            abort(404);
        }
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $column) {
            $key = strtolower(trim((string) $column));
            if ($key === '') {
                continue;
            }
            $map[$key] = (int) $index;
        }

        return $map;
    }

    private function buildQuestionPayload(array $data): array
    {
        $type = $this->normalizeQuestionType($data['type'] ?? null);
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $options = array_values(array_filter(array_map('trim', (array) ($data['options'] ?? [])), fn ($value) => $value !== ''));
        $correctRaw = trim((string) ($data['correct_answer'] ?? ''));
        $matchingItems = $this->parseList((string) ($data['matching_items'] ?? ''));
        $meta = !empty($matchingItems) ? ['items' => $matchingItems] : null;

        if ($prompt === '') {
            return ['error' => __('app.import_error_prompt', ['line' => ''])];
        }

        if ($type === 'mcq') {
            if (count($options) !== 4) {
                return ['error' => 'MCQ uchun 4 ta variant kerak.'];
            }
            $correct = $this->normalizeMcqCorrect($correctRaw);
            if ($correct === null) {
                return ['error' => __('app.import_error_correct', ['line' => ''])];
            }
        } elseif ($type === 'tfng') {
            if (empty($options)) {
                $options = ['TRUE', 'FALSE', 'NOT GIVEN'];
            }
            $correct = $this->normalizeTfngCorrect($correctRaw);
            if ($correct === null) {
                return ['error' => 'TFNG uchun correct_answer TRUE/FALSE/NOT GIVEN bo‘lishi kerak.'];
            }
        } elseif ($type === 'completion') {
            $correct = $correctRaw;
            if ($correct === '') {
                return ['error' => 'Completion uchun correct_answer kerak.'];
            }
            $options = [];
        } elseif ($type === 'matching') {
            if (count($options) < 2) {
                return ['error' => 'Matching uchun kamida 2 ta option kerak.'];
            }
            if (empty($matchingItems)) {
                return ['error' => 'Matching uchun matching_items kerak.'];
            }
            $correct = $this->normalizeMatchingCorrect($correctRaw, count($matchingItems), count($options));
            if ($correct === null) {
                return ['error' => 'Matching uchun correct_answer formati xato (masalan: 1:A|2:C|3:B).'];
            }
            $correct = json_encode($correct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $correct = $correctRaw;
        }

        return [
            'type' => $type,
            'prompt' => $prompt,
            'options' => $options,
            'correct_answer' => $correct,
            'ai_explanation' => trim((string) ($data['ai_explanation'] ?? '')) ?: null,
            'meta' => $meta,
            'mode' => 'practice',
        ];
    }

    private function normalizeQuestionType(?string $value): string
    {
        $type = strtolower(trim((string) $value));
        return in_array($type, ['mcq', 'tfng', 'completion', 'matching'], true) ? $type : 'mcq';
    }

    private function normalizeMcqCorrect(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
            $value = $map[$value] ?? $value;
        } else {
            $value = strtoupper(substr($value, 0, 1));
        }
        return in_array($value, ['A', 'B', 'C', 'D'], true) ? $value : null;
    }

    private function normalizeTfngCorrect(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        if (in_array($value, ['TRUE', 'T'], true)) {
            return 'TRUE';
        }
        if (in_array($value, ['FALSE', 'F'], true)) {
            return 'FALSE';
        }
        if (in_array($value, ['NOT GIVEN', 'NOT_GIVEN', 'NG', 'N'], true)) {
            return 'NOT GIVEN';
        }
        return null;
    }

    private function normalizeMatchingCorrect(string $value, int $itemCount, int $optionCount): ?array
    {
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        $map = [];
        if (is_array($decoded)) {
            $map = $decoded;
        } else {
            $pairs = preg_split('/\\s*[|;,]\\s*/', $value);
            foreach ($pairs as $pair) {
                if ($pair === '') {
                    continue;
                }
                if (preg_match('/(\\d+)\\s*[:=]\\s*([A-Za-z]+)/', $pair, $matches)) {
                    $map[(int) $matches[1]] = strtoupper(substr($matches[2], 0, 1));
                }
            }
        }

        if (empty($map)) {
            return null;
        }

        $maxLetter = chr(64 + max(1, min(26, $optionCount)));
        foreach ($map as $key => $letter) {
            $index = (int) $key;
            if ($index < 1 || $index > $itemCount) {
                return null;
            }
            $letter = strtoupper((string) $letter);
            if ($letter < 'A' || $letter > $maxLetter) {
                return null;
            }
            $map[$index] = $letter;
        }

        return $map;
    }

    private function parseList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $parts = preg_split('/\\R+|\\s*\\|\\s*/', $value);
        $parts = array_values(array_filter(array_map('trim', (array) $parts), fn ($item) => $item !== ''));
        return $parts;
    }

}
