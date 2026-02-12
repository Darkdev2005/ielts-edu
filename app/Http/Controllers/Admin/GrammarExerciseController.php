<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateGrammarExercises;
use App\Models\GrammarExercise;
use App\Models\GrammarRule;
use App\Models\GrammarTopic;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GrammarExerciseController extends Controller
{
    public function index(GrammarTopic $topic)
    {
        $topic->load(['exercises' => fn ($query) => $query->with('rule')->orderBy('sort_order')->orderBy('id')]);

        return view('admin.grammar.exercises.index', compact('topic'));
    }

    public function create(GrammarTopic $topic)
    {
        $rules = $topic->rules()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.grammar.exercises.create', compact('topic', 'rules'));
    }

    public function store(Request $request, GrammarTopic $topic)
    {
        $data = $this->validateExercise($request, $topic);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['exercise_type'] = $data['exercise_type'] ?? 'mcq';
        $data['question'] = $data['question'] ?? $data['prompt'] ?? '';
        $data['options'] = $this->normalizeOptions($data['exercise_type'], $data);
        $data['correct_answer'] = $this->normalizeCorrectAnswer($data['exercise_type'], $data['correct_answer'] ?? '');
        $data['explanation'] = $this->firstNonEmpty([
            $data['explanation_uz'] ?? '',
            $data['explanation_en'] ?? '',
            $data['explanation_ru'] ?? '',
        ]) ?: null;
        $data['grammar_topic_id'] = $topic->id;
        $topic->exercises()->create($data);

        return redirect()->route('admin.grammar.exercises.index', $topic);
    }

    public function import(Request $request, GrammarTopic $topic)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $data['csv'];
        if (!$file->isValid()) {
            return redirect()
                ->route('admin.grammar.exercises.index', $topic)
                ->with('import_errors', [__('app.import_failed')]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $defaultHeader = ['prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'explanation', 'sort_order'];
        $imported = 0;
        $errors = [];
        $lineNumber = 1;
        $unmappedRuleId = $this->resolveUnmappedRuleId($topic);

        $processLegacyRow = function (array $row) use ($topic, $unmappedRuleId, &$imported, &$errors, $defaultHeader, &$lineNumber) {
            $lineNumber += 1;
            if (count($row) === 1 && trim($row[0] ?? '') === '') {
                return;
            }

            $rowData = array_combine($defaultHeader, array_pad($row, count($defaultHeader), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

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
            $normalized = $this->normalizeMcqOptionsAndAnswer($options, $correctRaw, $lineNumber, $errors);
            if (!$normalized) {
                return;
            }
            [$optionsAssoc, $correctRaw] = $normalized;

            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));
            $explanation = trim((string) ($rowData['explanation'] ?? '')) ?: null;
            $payload = [
                'full_sentence' => $prompt,
                'options' => [
                    'a' => $options[0] ?? null,
                    'b' => $options[1] ?? null,
                    'c' => $options[2] ?? null,
                    'd' => $options[3] ?? null,
                ],
                'explanation_en' => $explanation,
            ];
            $payload = collect($payload)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? $payload : null;

            $topic->exercises()->create([
                'grammar_rule_id' => $unmappedRuleId,
                'exercise_type' => 'mcq',
                'type' => 'mcq',
                'question' => $prompt,
                'prompt' => $prompt,
                'options' => $optionsAssoc,
                'correct_answer' => $correctRaw,
                'explanation' => $explanation,
                'explanation_uz' => $explanation,
                'payload_json' => $payload,
                'sort_order' => $sortOrder,
            ]);

            $imported += 1;
        };

        $processNewRow = function (array $row, array $header) use (&$imported, &$errors, &$lineNumber, $topic) {
            $lineNumber += 1;
            if (count($row) === 1 && trim($row[0] ?? '') === '') {
                return;
            }

            $rowData = array_combine($header, array_pad($row, count($header), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

            $topicKey = trim((string) ($rowData['topic_key'] ?? $rowData['topic_slug'] ?? ''));
            $targetTopic = $this->resolveTopicFromKey($topicKey, $topic);
            if (!$targetTopic) {
                $errors[] = __('app.import_error_topic_key', ['line' => $lineNumber, 'value' => $topicKey]);
                return;
            }

            $ruleIdentifier = trim((string) ($rowData['rule_key'] ?? $rowData['rule_id'] ?? ''));
            if ($ruleIdentifier === '') {
                $errors[] = __('app.import_error_rule_id', ['line' => $lineNumber]);
                return;
            }

            $ruleQuery = GrammarRule::where('grammar_topic_id', $targetTopic->id);
            if (ctype_digit($ruleIdentifier)) {
                $ruleQuery->where('id', (int) $ruleIdentifier);
            } else {
                $ruleQuery->where(function ($query) use ($ruleIdentifier) {
                    $query->where('rule_key', $ruleIdentifier)
                        ->orWhere('rule_id', $ruleIdentifier);
                });
            }
            $rule = $ruleQuery->first();
            if (!$rule) {
                $errors[] = __('app.import_error_rule_id', ['line' => $lineNumber]);
                return;
            }

            $exerciseId = trim((string) ($rowData['exercise_id'] ?? ''));

            $type = strtolower(trim((string) ($rowData['exercise_type'] ?? $rowData['type'] ?? '')));
            $type = $type !== '' ? $type : 'mcq';

            $question = trim((string) ($rowData['question'] ?? $rowData['prompt'] ?? ''));
            if ($question === '') {
                $errors[] = __('app.import_error_prompt', ['line' => $lineNumber]);
                return;
            }

            $optionA = trim((string) ($rowData['option_a'] ?? ''));
            $optionB = trim((string) ($rowData['option_b'] ?? ''));
            $optionC = trim((string) ($rowData['option_c'] ?? ''));
            $optionD = trim((string) ($rowData['option_d'] ?? ''));

            $correctRaw = trim((string) ($rowData['correct_answer'] ?? ''));

            if ($type === 'mcq') {
                $normalized = $this->normalizeMcqOptionsAndAnswer(
                    [$optionA, $optionB, $optionC, $optionD],
                    $correctRaw,
                    $lineNumber,
                    $errors
                );
                if (!$normalized) {
                    return;
                }
                [$optionsStore, $correctRaw] = $normalized;
            } elseif ($type === 'tf') {
                if ($correctRaw === '') {
                    $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
                    return;
                }
                $normalized = strtolower($correctRaw);
                if (!in_array($normalized, ['true', 'false'], true)) {
                    $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
                    return;
                }
                $correctRaw = $normalized;
            } else {
                if ($correctRaw === '') {
                    $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
                    return;
                }
            }

            if ($exerciseId === '') {
                $signature = implode('|', [
                    $targetTopic->id,
                    $ruleIdentifier,
                    $type,
                    $question,
                    $optionA,
                    $optionB,
                    $optionC,
                    $optionD,
                    $correctRaw,
                ]);
                $exerciseId = 'auto-'.substr(sha1($signature), 0, 12);
            }

            $explanationUz = trim((string) ($rowData['explanation_uz'] ?? ''));
            $explanationEn = trim((string) ($rowData['explanation_en'] ?? ''));
            $explanationRu = trim((string) ($rowData['explanation_ru'] ?? ''));
            $explanation = $this->firstNonEmpty([$explanationUz, $explanationEn, $explanationRu, trim((string) ($rowData['explanation'] ?? ''))]);
            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));
            $cefrLevel = trim((string) ($rowData['cefr_level'] ?? ''));

            $optionsStore = $type === 'mcq'
                ? ($optionsStore ?? [])
                : [];

            GrammarExercise::updateOrCreate(
                [
                    'grammar_topic_id' => $targetTopic->id,
                    'exercise_id' => $exerciseId,
                ],
                [
                    'grammar_rule_id' => $rule->id,
                    'exercise_type' => $type,
                    'type' => $type,
                    'question' => $question,
                    'prompt' => $question,
                    'options' => $optionsStore,
                    'correct_answer' => $correctRaw,
                    'explanation' => $explanation ?: null,
                    'explanation_uz' => $explanationUz ?: null,
                    'explanation_en' => $explanationEn ?: null,
                    'explanation_ru' => $explanationRu ?: null,
                    'cefr_level' => $cefrLevel !== '' ? $cefrLevel : null,
                    'sort_order' => $sortOrder,
                ]
            );

            $imported += 1;
        };

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $rows = $this->readSpreadsheetRows($file, 'grammar_exercises');
            if (empty($rows)) {
                return redirect()
                    ->route('admin.grammar.exercises.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $header = $this->normalizeHeader($rows[0] ?? []);
            $hasHeader = $this->looksLikeHeader($header);
            $schema = $hasHeader ? $this->detectExerciseSchema($header) : 'legacy';
            $startIndex = $hasHeader ? 1 : 0;

            for ($i = $startIndex; $i < count($rows); $i++) {
                $row = $rows[$i];
                if ($schema === 'new') {
                    $processNewRow($row, $header);
                } else {
                    $processLegacyRow($row);
                }
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return redirect()
                    ->route('admin.grammar.exercises.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);

                return redirect()
                    ->route('admin.grammar.exercises.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = $this->normalizeHeader(str_getcsv($firstLine, $delimiter));
            $hasHeader = $this->looksLikeHeader($header);
            $schema = $hasHeader ? $this->detectExerciseSchema($header) : 'legacy';

            if (!$hasHeader) {
                $processLegacyRow($header);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($schema === 'new') {
                    $processNewRow($row, $header);
                } else {
                    $processLegacyRow($row);
                }
            }

            fclose($handle);
        }

        return redirect()
            ->route('admin.grammar.exercises.index', $topic)
            ->with('imported', $imported)
            ->with('import_errors', $errors);
    }

    public function generate(Request $request, GrammarTopic $topic)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        GenerateGrammarExercises::dispatch($topic->id, $data['count']);

        return redirect()
            ->route('admin.grammar.exercises.index', $topic)
            ->with('status', __('app.grammar_generating'));
    }

    public function downloadSample(Request $request, GrammarTopic $topic)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $sampleRuleKey = $topic->rules()
            ->where('rule_key', '!=', '__unmapped__')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('rule_key') ?: 'rule_key_here';
        $headings = [
            'topic_key',
            'rule_key',
            'exercise_id',
            'exercise_type',
            'question',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_answer',
            'explanation_uz',
            'explanation_en',
            'explanation_ru',
            'cefr_level',
            'sort_order',
        ];

        $rows = [
            [
                $topic->topic_key ?? $topic->slug ?? ('topic-'.$topic->id),
                $sampleRuleKey,
                'ex-001',
                'mcq',
                'She ___ a doctor.',
                'am',
                'is',
                'are',
                'be',
                'B',
                'She uchun "is" ishlatiladi.',
                'Use "is" with she.',
                'S "she" ispolzuyte "is".',
                $topic->cefr_level ?: 'A1',
                1,
            ],
        ];

        $export = new ArrayExport($headings, $rows);
        $filename = 'grammar-topic-'.$topic->id.'-exercises-sample.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    public function export(Request $request, GrammarTopic $topic)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $exercises = $topic->exercises()->with(['topic', 'rule'])->orderBy('sort_order')->orderBy('id')->get();

        $headings = [
            'topic_key',
            'rule_key',
            'exercise_id',
            'exercise_type',
            'correct_answer',
            'sort_order',
            'question',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'explanation_uz',
            'explanation_en',
            'explanation_ru',
            'cefr_level',
        ];

        $rows = $exercises->map(function (GrammarExercise $exercise) {
            $options = (array) $exercise->options;
            $optionA = $options['A'] ?? ($options[0] ?? '');
            $optionB = $options['B'] ?? ($options[1] ?? '');
            $optionC = $options['C'] ?? ($options[2] ?? '');
            $optionD = $options['D'] ?? ($options[3] ?? '');
            $exerciseId = $exercise->exercise_id ?: 'ex-'.$exercise->id;

            return [
                $exercise->topic?->topic_key ?? $exercise->topic?->slug ?? ($exercise->grammar_topic_id ? 'topic-'.$exercise->grammar_topic_id : null),
                $exercise->rule?->rule_key,
                $exerciseId,
                $exercise->exercise_type ?? $exercise->type ?? 'mcq',
                $exercise->correct_answer,
                $exercise->sort_order,
                $exercise->question ?? $exercise->prompt,
                $optionA,
                $optionB,
                $optionC,
                $optionD,
                $exercise->explanation_uz ?? '',
                $exercise->explanation_en ?? $exercise->explanation,
                $exercise->explanation_ru ?? '',
                $exercise->cefr_level ?? '',
            ];
        })->all();

        $export = new ArrayExport($headings, $rows);
        $filename = 'grammar-topic-'.$topic->id.'-exercises-'.now()->format('Ymd-His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    public function edit(GrammarTopic $topic, GrammarExercise $exercise)
    {
        $this->ensureTopicMatch($topic, $exercise);

        $rules = $topic->rules()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.grammar.exercises.edit', compact('topic', 'exercise', 'rules'));
    }

    public function update(Request $request, GrammarTopic $topic, GrammarExercise $exercise)
    {
        $this->ensureTopicMatch($topic, $exercise);

        $data = $this->validateExercise($request, $topic, $exercise->id);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['exercise_type'] = $data['exercise_type'] ?? $exercise->exercise_type ?? 'mcq';
        $data['question'] = $data['question'] ?? $data['prompt'] ?? '';
        $data['options'] = $this->normalizeOptions($data['exercise_type'], $data);
        $data['correct_answer'] = $this->normalizeCorrectAnswer($data['exercise_type'], $data['correct_answer'] ?? '');
        $data['explanation'] = $this->firstNonEmpty([
            $data['explanation_uz'] ?? '',
            $data['explanation_en'] ?? '',
            $data['explanation_ru'] ?? '',
        ]) ?: null;
        $data['grammar_topic_id'] = $topic->id;
        $exercise->update($data);

        return redirect()->route('admin.grammar.exercises.index', $topic);
    }

    public function destroy(GrammarTopic $topic, GrammarExercise $exercise)
    {
        $this->ensureTopicMatch($topic, $exercise);
        $exercise->delete();

        return redirect()->route('admin.grammar.exercises.index', $topic);
    }

    private function validateExercise(Request $request, GrammarTopic $topic, ?int $ignoreId = null): array
    {
        $ruleExists = Rule::exists('grammar_rules', 'id')->where('grammar_topic_id', $topic->id);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'grammar_rule_id' => ['required', 'integer', $ruleExists],
            'exercise_type' => ['required', 'in:mcq,gap,tf,reorder'],
            'question' => ['required', 'string'],
            'option_a' => ['nullable', 'string'],
            'option_b' => ['nullable', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'correct_answer' => ['required', 'string'],
            'explanation_uz' => ['nullable', 'string'],
            'explanation_en' => ['nullable', 'string'],
            'explanation_ru' => ['nullable', 'string'],
            'cefr_level' => ['nullable', 'in:A1,A2,B1,B2,C1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = (string) $request->input('exercise_type');
            $answer = trim((string) $request->input('correct_answer'));

            if ($type === 'mcq') {
                $options = [
                    trim((string) $request->input('option_a')),
                    trim((string) $request->input('option_b')),
                    trim((string) $request->input('option_c')),
                    trim((string) $request->input('option_d')),
                ];
                if (in_array('', $options, true)) {
                    $validator->errors()->add('options', __('app.import_error_options'));
                    return;
                }
                $letter = strtoupper($answer);
                if (!in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                    $validator->errors()->add('correct_answer', __('app.import_error_correct'));
                }
                return;
            }

            if ($type === 'tf') {
                $normalized = strtolower($answer);
                if (!in_array($normalized, ['true', 'false'], true)) {
                    $validator->errors()->add('correct_answer', __('app.import_error_correct'));
                }
                return;
            }

            if ($answer === '') {
                $validator->errors()->add('correct_answer', __('app.import_error_correct'));
            }
        });

        return $validator->validate();
    }

    private function normalizeOptions(string $type, array $data): array
    {
        if ($type !== 'mcq') {
            return [];
        }

        return [
            'A' => trim((string) ($data['option_a'] ?? '')),
            'B' => trim((string) ($data['option_b'] ?? '')),
            'C' => trim((string) ($data['option_c'] ?? '')),
            'D' => trim((string) ($data['option_d'] ?? '')),
        ];
    }

    private function normalizeCorrectAnswer(string $type, string $answer): string
    {
        $answer = trim($answer);
        if ($type === 'mcq') {
            return strtoupper(substr($answer, 0, 1));
        }

        if ($type === 'tf') {
            return strtolower($answer) === 'true' ? 'true' : 'false';
        }

        return $answer;
    }

    private function normalizeMcqOptionsAndAnswer(array $options, string $correctRaw, int $lineNumber, array &$errors): ?array
    {
        $options = array_map(static fn ($value) => trim((string) $value), $options);
        $optionsMap = [
            'A' => $options[0] ?? '',
            'B' => $options[1] ?? '',
            'C' => $options[2] ?? '',
            'D' => $options[3] ?? '',
        ];

        $filtered = array_filter($optionsMap, static fn ($value) => $value !== '');
        if (count($filtered) < 2) {
            $errors[] = __('app.import_error_options', ['line' => $lineNumber]);
            return null;
        }

        $raw = trim($correctRaw);
        if ($raw === '') {
            $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
            return null;
        }

        $letter = null;
        if (is_numeric($raw)) {
            $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
            $letter = $map[$raw] ?? null;
        }

        if ($letter === null) {
            $upper = strtoupper($raw);
            if (in_array($upper, ['A', 'B', 'C', 'D'], true)) {
                $letter = $upper;
            }
        }

        if ($letter === null) {
            foreach ($filtered as $key => $value) {
                if (strcasecmp($value, $raw) === 0) {
                    $letter = $key;
                    break;
                }
            }
        }

        if ($letter === null || !array_key_exists($letter, $filtered)) {
            $errors[] = __('app.import_error_correct', ['line' => $lineNumber]);
            return null;
        }

        return [$filtered, $letter];
    }

    private function normalizeHeader(array $header): array
    {
        $header = array_map('trim', $header);
        if (!empty($header)) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        return array_map(static fn ($value) => strtolower($value), $header);
    }

    private function looksLikeHeader(array $header): bool
    {
        $known = [
            'prompt',
            'question',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_answer',
            'explanation',
            'sort_order',
            'topic_key',
            'topic_slug',
            'rule_key',
            'rule_id',
            'exercise_id',
            'type',
            'exercise_type',
            'explanation_uz',
            'explanation_en',
            'explanation_ru',
            'cefr_level',
        ];

        foreach ($header as $value) {
            if (in_array($value, $known, true)) {
                return true;
            }
        }

        return false;
    }

    private function detectExerciseSchema(array $header): string
    {
        $markers = [
            'topic_key',
            'topic_slug',
            'rule_key',
            'rule_id',
            'exercise_id',
            'type',
            'exercise_type',
            'explanation_uz',
            'explanation_en',
            'explanation_ru',
            'question',
        ];

        foreach ($markers as $marker) {
            if (in_array($marker, $header, true)) {
                return 'new';
            }
        }

        return 'legacy';
    }

    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveTopicFromKey(string $topicKey, GrammarTopic $fallback): ?GrammarTopic
    {
        $topicKey = trim($topicKey);
        if ($topicKey === '') {
            return $fallback;
        }

        $topic = GrammarTopic::where('topic_key', $topicKey)->first();
        if ($topic) {
            return $topic;
        }

        $topic = GrammarTopic::where('slug', $topicKey)->first();
        if ($topic) {
            return $topic;
        }

        $normalized = Str::slug(str_replace('_', ' ', $topicKey));
        if ($normalized !== '' && $normalized !== $topicKey) {
            $topic = GrammarTopic::where('topic_key', $normalized)->first();
            if ($topic) {
                return $topic;
            }
            $topic = GrammarTopic::where('slug', $normalized)->first();
            if ($topic) {
                return $topic;
            }
        }

        if (ctype_digit($topicKey)) {
            $topic = GrammarTopic::find((int) $topicKey);
            if ($topic) {
                return $topic;
            }
        }

        $topic = GrammarTopic::where('title', $topicKey)->first();
        if ($topic) {
            return $topic;
        }

        $topic = GrammarTopic::whereRaw('LOWER(title) = ?', [strtolower($topicKey)])->first();
        if ($topic) {
            return $topic;
        }

        if ($normalized !== '') {
            $fallbackSlug = (string) ($fallback->slug ?? '');
            $fallbackTitle = (string) ($fallback->title ?? '');
            if (($fallbackSlug !== '' && str_contains($fallbackSlug, $normalized))
                || ($fallbackTitle !== '' && str_contains(Str::slug($fallbackTitle), $normalized))) {
                return $fallback;
            }

            $matches = GrammarTopic::where('topic_key', 'like', '%'.$normalized.'%')
                ->orWhere('slug', 'like', '%'.$normalized.'%')
                ->limit(2)
                ->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    private function ensureTopicMatch(GrammarTopic $topic, GrammarExercise $exercise): void
    {
        if ($exercise->grammar_topic_id !== $topic->id) {
            abort(404);
        }
    }

    private function resolveUnmappedRuleId(GrammarTopic $topic): int
    {
        $rule = $topic->rules()->where('rule_key', '__unmapped__')->first();
        if ($rule) {
            return (int) $rule->id;
        }

        $created = $topic->rules()->create([
            'rule_key' => '__unmapped__',
            'rule_type' => 'note',
            'rule_text_uz' => "Ushbu mashq hali aniq qoidaga bog'lanmagan.",
            'title' => 'Unmapped rule',
            'content' => 'System placeholder for exercises without a mapped rule.',
            'sort_order' => 9999,
        ]);

        return (int) $created->id;
    }

    private function readSpreadsheetRows(\Illuminate\Http\UploadedFile $file, ?string $preferredSheetName = null): array
    {
        $path = $file->getRealPath();
        if (!$path) {
            return [];
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = null;
            if ($preferredSheetName) {
                $needle = strtolower(trim($preferredSheetName));
                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    if (strtolower(trim($worksheet->getTitle())) === $needle) {
                        $sheet = $worksheet;
                        break;
                    }
                }
            }

            $sheet ??= $spreadsheet->getSheet(0);
            if (!$sheet) {
                return [];
            }

            return $sheet->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }
}
