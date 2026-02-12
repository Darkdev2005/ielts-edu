<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\MockQuestion;
use App\Models\MockSection;
use App\Models\MockTest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class MockQuestionController extends Controller
{
    public function index(MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        return view('admin.mock-questions.section-index', [
            'test' => $mockTest,
            'section' => $mockSection->load([
                'questions' => fn ($query) => $query->orderBy('order_index')->orderBy('id'),
            ]),
        ]);
    }

    public function create(MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        return view('admin.mock-questions.create', [
            'test' => $mockTest,
            'section' => $mockSection,
        ]);
    }

    public function store(Request $request, MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        $data = $this->validated($request);
        $mockSection->questions()->create($data);
        $this->syncSectionQuestionCount($mockSection);

        return redirect()
            ->route('admin.mock-test-sections.questions.index', [$mockTest, $mockSection])
            ->with('status', __('app.saved'));
    }

    public function edit(MockTest $mockTest, MockSection $mockSection, MockQuestion $mockQuestion)
    {
        $this->ensureQuestionMatch($mockTest, $mockSection, $mockQuestion);

        return view('admin.mock-questions.edit', [
            'test' => $mockTest,
            'section' => $mockSection,
            'question' => $mockQuestion,
        ]);
    }

    public function update(Request $request, MockTest $mockTest, MockSection $mockSection, MockQuestion $mockQuestion)
    {
        $this->ensureQuestionMatch($mockTest, $mockSection, $mockQuestion);

        $data = $this->validated($request);
        $mockQuestion->update($data);
        $this->syncSectionQuestionCount($mockSection);

        return redirect()
            ->route('admin.mock-test-sections.questions.index', [$mockTest, $mockSection])
            ->with('status', __('app.saved'));
    }

    public function destroy(MockTest $mockTest, MockSection $mockSection, MockQuestion $mockQuestion)
    {
        $this->ensureQuestionMatch($mockTest, $mockSection, $mockQuestion);

        $mockQuestion->delete();
        $this->syncSectionQuestionCount($mockSection);

        return redirect()
            ->route('admin.mock-test-sections.questions.index', [$mockTest, $mockSection])
            ->with('status', __('app.deleted'));
    }

    public function import(Request $request, MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $data['csv'];
        if (!$file->isValid()) {
            return back()->with('import_errors', [__('app.import_failed')]);
        }

        $defaultHeader = ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'order_index'];
        $headerAliases = [
            'question_type' => ['question_type', 'questiontype', 'type', 'question type'],
            'question_text' => ['question_text', 'questiontext', 'question', 'question title', 'question_title', 'content'],
            'option_a' => ['option_a', 'optiona', 'a', 'choice_a', 'choicea', 'option_1', 'option1'],
            'option_b' => ['option_b', 'optionb', 'b', 'choice_b', 'choiceb', 'option_2', 'option2'],
            'option_c' => ['option_c', 'optionc', 'c', 'choice_c', 'choicec', 'option_3', 'option3'],
            'option_d' => ['option_d', 'optiond', 'd', 'choice_d', 'choiced', 'option_4', 'option4'],
            'correct_answer' => ['correct_answer', 'correctanswer', 'answer', 'correct option', 'correct_option'],
            'order_index' => ['order_index', 'orderindex', 'order', 'sort_order', 'sortorder'],
        ];
        $errors = [];
        $imported = 0;
        $lineNumber = 1;

        $processRow = function (array $row, ?array $headerMap = null) use ($mockSection, &$errors, &$imported, &$lineNumber, $defaultHeader): void {
            $lineNumber += 1;

            if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                return;
            }

            $rowData = $this->rowToData($row, $defaultHeader, $headerMap);
            if (!is_array($rowData)) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

            $questionType = $this->normalizeQuestionType($rowData['question_type'] ?? '');
            $questionText = trim((string) ($rowData['question_text'] ?? ''));
            $orderIndex = is_numeric($rowData['order_index'] ?? null)
                ? (int) $rowData['order_index']
                : ($mockSection->questions()->max('order_index') ?? 0) + 1;

            $options = [
                'A' => trim((string) ($rowData['option_a'] ?? '')),
                'B' => trim((string) ($rowData['option_b'] ?? '')),
                'C' => trim((string) ($rowData['option_c'] ?? '')),
                'D' => trim((string) ($rowData['option_d'] ?? '')),
            ];
            $correctAnswer = $this->normalizeCorrectAnswer($questionType, $rowData['correct_answer'] ?? '', $options);

            if (!in_array($questionType, ['mcq', 'tfng', 'ynng', 'completion', 'matching'], true)) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

            if ($questionText === '') {
                $errors[] = __('app.import_error_prompt', ['line' => $lineNumber]);
                return;
            }

            if ($correctAnswer === '') {
                $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
                return;
            }

            if ($questionType === 'mcq' && in_array('', $options, true)) {
                $errors[] = __('app.import_error_options', ['line' => $lineNumber]);
                return;
            }

            $mockSection->questions()->create([
                'question_type' => $questionType,
                'question_text' => $questionText,
                'options_json' => $questionType === 'mcq' ? $options : null,
                'correct_answer' => $correctAnswer,
                'order_index' => max(1, $orderIndex),
            ]);

            $imported += 1;
        };

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $rows = Excel::toArray([], $file)[0] ?? [];
            if (empty($rows)) {
                return back()->with('import_errors', [__('app.import_failed')]);
            }

            $headerIndex = $this->detectHeaderRowIndex($rows, $headerAliases);
            $hasHeader = $headerIndex !== null;
            $headerMap = $hasHeader ? $this->buildHeaderMap($rows[$headerIndex], $headerAliases) : null;
            $start = $hasHeader ? $headerIndex + 1 : 0;
            $lineNumber = $hasHeader ? $headerIndex + 1 : 0;

            for ($i = $start; $i < count($rows); $i++) {
                $processRow($rows[$i], $hasHeader ? $headerMap : null);
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return back()->with('import_errors', [__('app.import_failed')]);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                return back()->with('import_errors', [__('app.import_failed')]);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $rows = [str_getcsv($firstLine, $delimiter)];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }

            fclose($handle);

            $headerIndex = $this->detectHeaderRowIndex($rows, $headerAliases);
            $hasHeader = $headerIndex !== null;
            $headerMap = $hasHeader ? $this->buildHeaderMap($rows[$headerIndex], $headerAliases) : null;
            $start = $hasHeader ? $headerIndex + 1 : 0;
            $lineNumber = $hasHeader ? $headerIndex + 1 : 0;

            for ($i = $start; $i < count($rows); $i++) {
                $processRow($rows[$i], $hasHeader ? $headerMap : null);
            }
        }

        $this->syncSectionQuestionCount($mockSection);

        return redirect()
            ->route('admin.mock-test-sections.questions.index', [$mockTest, $mockSection])
            ->with('imported', $imported)
            ->with('import_errors', $errors);
    }

    public function export(Request $request, MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $headings = ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'order_index'];

        $rows = $mockSection->questions()
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->map(function (MockQuestion $question) {
                return [
                    $question->question_type,
                    $question->question_text,
                    $question->options_json['A'] ?? '',
                    $question->options_json['B'] ?? '',
                    $question->options_json['C'] ?? '',
                    $question->options_json['D'] ?? '',
                    $question->correct_answer,
                    $question->order_index,
                ];
            })
            ->all();

        $filename = 'mock-section-'.$mockSection->id.'-questions-'.now()->format('Ymd-His').'.'.($format === 'csv' ? 'csv' : 'xlsx');
        $export = new ArrayExport($headings, $rows);

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    public function sample(Request $request, MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureSectionMatch($mockTest, $mockSection);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $headings = ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'order_index'];
        $rows = [
            ['mcq', 'What is the writer trying to say?', 'A option', 'B option', 'C option', 'D option', 'B', 1],
            ['tfng', 'The passage says all parks are free.', '', '', '', '', 'FALSE', 2],
            ['completion', 'Complete: The city invested in ____ spaces.', '', '', '', '', 'green', 3],
            ['matching', 'Match heading to paragraph A.', '', '', '', '', 'iii', 4],
        ];

        $filename = 'mock_questions_sample.'.($format === 'csv' ? 'csv' : 'xlsx');
        $export = new ArrayExport($headings, $rows);

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question_type' => ['required', 'in:mcq,tfng,ynng,completion,matching'],
            'question_text' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'options.A' => ['nullable', 'string'],
            'options.B' => ['nullable', 'string'],
            'options.C' => ['nullable', 'string'],
            'options.D' => ['nullable', 'string'],
            'correct_answer' => ['required', 'string', 'max:255'],
            'order_index' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $questionType = $data['question_type'];
        $options = [
            'A' => trim((string) ($data['options']['A'] ?? '')),
            'B' => trim((string) ($data['options']['B'] ?? '')),
            'C' => trim((string) ($data['options']['C'] ?? '')),
            'D' => trim((string) ($data['options']['D'] ?? '')),
        ];

        if ($questionType === 'mcq' && in_array('', $options, true)) {
            throw ValidationException::withMessages([
                'options' => __('app.import_error_options', ['line' => 1]),
            ]);
        }

        return [
            'question_type' => $questionType,
            'question_text' => trim((string) $data['question_text']),
            'options_json' => $questionType === 'mcq' ? $options : null,
            'correct_answer' => $this->normalizeCorrectAnswer($questionType, $data['correct_answer'], $options),
            'order_index' => (int) $data['order_index'],
        ];
    }

    private function normalizeQuestionType($value): string
    {
        $type = strtolower(trim((string) $value));
        $type = str_replace([' ', '-', '/'], '_', $type);
        $type = preg_replace('/_+/', '_', $type) ?? $type;

        return match ($type) {
            'multiple_choice', 'multiplechoice' => 'mcq',
            'true_false_not_given', 'truefalse_notgiven', 'true_false_ng', 'tfn', 'tf_ng' => 'tfng',
            'yes_no_not_given', 'yesno_notgiven', 'y_n_ng', 'y/n/ng', 'ynng' => 'ynng',
            'fill_blank', 'fill_in_the_blank', 'sentence_completion' => 'completion',
            'match', 'heading_matching', 'matching_headings' => 'matching',
            default => $type,
        };
    }

    /**
     * @param array<int, mixed> $header
     * @param array<string, array<int, string>> $aliases
     * @return array<int, string|null>
     */
    private function buildHeaderMap(array $header, array $aliases): array
    {
        return collect($header)
            ->map(function ($column) use ($aliases) {
                $normalized = $this->normalizeHeaderName((string) $column);
                foreach ($aliases as $target => $keys) {
                    foreach ($keys as $key) {
                        if ($normalized === $this->normalizeHeaderName($key)) {
                            return $target;
                        }
                    }
                }

                return null;
            })
            ->all();
    }

    /**
     * @param array<int, string|null> $headerMap
     */
    private function isHeaderRow(array $headerMap): bool
    {
        $mapped = array_values(array_filter($headerMap));

        return in_array('question_type', $mapped, true) && in_array('question_text', $mapped, true);
    }

    /**
     * @param array<int, mixed> $row
     * @param array<int, string> $defaultHeader
     * @param array<int, string|null>|null $headerMap
     * @return array<string, mixed>|null
     */
    private function rowToData(array $row, array $defaultHeader, ?array $headerMap = null): ?array
    {
        if (is_array($headerMap) && !empty($headerMap)) {
            $data = array_fill_keys($defaultHeader, null);
            foreach ($headerMap as $index => $key) {
                if (!$key || !array_key_exists($index, $row)) {
                    continue;
                }
                $data[$key] = $row[$index];
            }

            return $data;
        }

        $positional = $this->mapPositionalRow($row, $defaultHeader, 0);
        if ($this->looksLikeQuestionRow($positional)) {
            return $positional;
        }

        $maxStart = max(0, count($row) - count($defaultHeader));
        for ($start = 1; $start <= $maxStart; $start++) {
            $candidate = $this->mapPositionalRow($row, $defaultHeader, $start);
            if ($this->looksLikeQuestionRow($candidate)) {
                return $candidate;
            }
        }

        $rowData = array_combine($defaultHeader, array_pad($row, count($defaultHeader), null));
        return is_array($rowData) ? $rowData : null;
    }

    private function normalizeHeaderName(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = preg_replace('/\s+/u', '_', (string) $value);
        $value = strtolower(trim((string) $value));
        $value = str_replace('-', '_', $value);

        return preg_replace('/_+/', '_', $value) ?? $value;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<string, array<int, string>> $aliases
     */
    private function detectHeaderRowIndex(array $rows, array $aliases): ?int
    {
        $limit = min(5, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $headerMap = $this->buildHeaderMap($rows[$i] ?? [], $aliases);
            if ($this->isHeaderRow($headerMap)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $row
     * @param array<int, string> $defaultHeader
     * @return array<string, mixed>
     */
    private function mapPositionalRow(array $row, array $defaultHeader, int $start): array
    {
        $slice = array_slice(array_values($row), $start, count($defaultHeader));
        $slice = array_pad($slice, count($defaultHeader), null);
        $mapped = array_combine($defaultHeader, $slice);

        return is_array($mapped) ? $mapped : array_fill_keys($defaultHeader, null);
    }

    /**
     * @param array<string, mixed> $rowData
     */
    private function looksLikeQuestionRow(array $rowData): bool
    {
        $type = $this->normalizeQuestionType($rowData['question_type'] ?? '');
        $text = trim((string) ($rowData['question_text'] ?? ''));

        return in_array($type, ['mcq', 'tfng', 'completion', 'matching'], true) && $text !== '';
    }

    private function normalizeCorrectAnswer(string $questionType, $value, array $options = []): string
    {
        $answer = trim((string) $value);
        if ($questionType === 'mcq') {
            $answer = strtoupper($answer);
            if (is_numeric($answer)) {
                $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
                return $map[$answer] ?? '';
            }

            if (preg_match('/^([A-D])\b/', $answer, $matches)) {
                return strtoupper($matches[1]);
            }

            $first = substr($answer, 0, 1);
            if (in_array($first, ['A', 'B', 'C', 'D'], true)) {
                return $first;
            }

            $needle = Str::lower(trim($answer));
            if ($needle !== '' && !empty($options)) {
                foreach ($options as $letter => $text) {
                    if ($needle === Str::lower(trim((string) $text))) {
                        return $letter;
                    }
                }
            }

            return '';
        }

        if ($questionType === 'tfng') {
            $normalized = strtoupper($answer);
            $normalized = str_replace(['NOT GIVEN', 'NOT-GIVEN', 'NOTGIVEN', 'N.G.', 'NG'], 'NOT_GIVEN', $normalized);
            if ($normalized === 'T') {
                $normalized = 'TRUE';
            } elseif ($normalized === 'F') {
                $normalized = 'FALSE';
            }
            if (in_array($normalized, ['TRUE', 'FALSE', 'NOT_GIVEN'], true)) {
                return $normalized;
            }

            return '';
        }

        if ($questionType === 'ynng') {
            $normalized = strtoupper($answer);
            $normalized = str_replace(['NOT GIVEN', 'NOT-GIVEN', 'NOTGIVEN', 'N.G.', 'NG'], 'NOT_GIVEN', $normalized);
            if ($normalized === 'Y') {
                $normalized = 'YES';
            } elseif ($normalized === 'N') {
                $normalized = 'NO';
            }
            if (in_array($normalized, ['YES', 'NO', 'NOT_GIVEN'], true)) {
                return $normalized;
            }

            return '';
        }

        return $answer;
    }

    private function syncSectionQuestionCount(MockSection $mockSection): void
    {
        $mockSection->update([
            'question_count' => $mockSection->questions()->count(),
        ]);
    }

    private function ensureSectionMatch(MockTest $mockTest, MockSection $mockSection): void
    {
        if ($mockSection->mock_test_id !== $mockTest->id) {
            abort(404);
        }
    }

    private function ensureQuestionMatch(MockTest $mockTest, MockSection $mockSection, MockQuestion $mockQuestion): void
    {
        $this->ensureSectionMatch($mockTest, $mockSection);
        if ($mockQuestion->mock_section_id !== $mockSection->id) {
            abort(404);
        }
    }
}
