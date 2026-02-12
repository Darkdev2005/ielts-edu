<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrammarTopic;
use App\Models\GrammarRule;
use App\Models\GrammarExercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GrammarTopicController extends Controller
{
    public function index()
    {
        $topics = GrammarTopic::withCount(['rules', 'exercises'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.grammar.topics.index', compact('topics'));
    }

    public function create()
    {
        return view('admin.grammar.topics.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateTopic($request);
        $data['created_by'] = Auth::id();
        $data['title'] = $data['title_uz'];
        $data['description'] = $data['description_uz'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['slug'] = $this->generateUniqueSlug($data['title_uz']);
        $data['topic_key'] = $this->resolveTopicKey($data['topic_key'] ?? null, $data['title_uz']);

        GrammarTopic::create($data);

        return redirect()->route('admin.grammar.topics.index');
    }

    public function edit(GrammarTopic $topic)
    {
        return view('admin.grammar.topics.edit', compact('topic'));
    }

    public function update(Request $request, GrammarTopic $topic)
    {
        $data = $this->validateTopic($request);
        $data['title'] = $data['title_uz'];
        $data['description'] = $data['description_uz'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? $topic->sort_order ?? 0;
        $data['topic_key'] = $this->resolveTopicKey($data['topic_key'] ?? null, $data['title_uz'], $topic->id);
        if (!$topic->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['title_uz'], $topic->id);
        }
        $topic->update($data);

        return redirect()->route('admin.grammar.topics.index');
    }

    public function destroy(GrammarTopic $topic)
    {
        $topic->delete();

        return redirect()->route('admin.grammar.topics.index');
    }

    public function importWorkbook(Request $request)
    {
        $data = $request->validate([
            'xlsx' => ['required', 'file', 'mimes:xls,xlsx', 'max:5120'],
        ]);

        $file = $data['xlsx'];
        if (!$file->isValid()) {
            return redirect()
                ->route('admin.grammar.topics.index')
                ->with('import_errors', [__('app.import_failed')]);
        }

        $topicsRows = $this->readSpreadsheetRows($file, 'grammar_topics');
        $rulesRows = $this->readSpreadsheetRows($file, 'grammar_rules');
        $exercisesRows = $this->readSpreadsheetRows($file, 'grammar_exercises');

        if (empty($topicsRows) && empty($rulesRows) && empty($exercisesRows)) {
            return redirect()
                ->route('admin.grammar.topics.index')
                ->with('import_errors', [__('app.import_failed')]);
        }

        $imported = [
            'topics' => 0,
            'rules' => 0,
            'exercises' => 0,
        ];
        $errors = [];

        if (!empty($topicsRows)) {
            $this->importTopicsSheet($topicsRows, $imported, $errors);
        }

        if (!empty($rulesRows)) {
            $this->importRulesSheet($rulesRows, $imported, $errors);
        }

        if (!empty($exercisesRows)) {
            $this->importExercisesSheet($exercisesRows, $imported, $errors);
        }

        return redirect()
            ->route('admin.grammar.topics.index')
            ->with('imported_workbook', $imported)
            ->with('import_errors', $errors);
    }

    private function validateTopic(Request $request): array
    {
        return $request->validate([
            'topic_key' => ['nullable', 'string', 'max:100'],
            'title_uz' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_ru' => ['nullable', 'string', 'max:255'],
            'description_uz' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'description_ru' => ['nullable', 'string'],
            'cefr_level' => ['nullable', 'in:A1,A2,B1,B2,C1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'topic';
        }
        $slug = $base;
        $suffix = 2;

        while (GrammarTopic::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $slug;
    }

    private function resolveTopicKey(?string $topicKey, string $title, ?int $ignoreId = null): string
    {
        $candidate = trim((string) $topicKey);
        if ($candidate === '') {
            $candidate = Str::slug($title);
        }
        if ($candidate === '') {
            $candidate = 'topic';
        }

        $base = $candidate;
        $suffix = 2;
        while (GrammarTopic::where('topic_key', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $candidate;
    }

    private function importTopicsSheet(array $rows, array &$imported, array &$errors): void
    {
        $header = $this->normalizeHeader($rows[0] ?? []);
        if (!$this->looksLikeTopicHeader($header)) {
            $errors[] = __('app.import_failed');
            return;
        }

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            $rowData = array_combine($header, array_pad($row, count($header), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $i + 1]);
                continue;
            }

            $topicKey = trim((string) ($rowData['topic_key'] ?? $rowData['topic_slug'] ?? ''));
            $titleUz = trim((string) ($rowData['title_uz'] ?? $rowData['title'] ?? ''));
            $titleEn = trim((string) ($rowData['title_en'] ?? ''));
            $titleRu = trim((string) ($rowData['title_ru'] ?? ''));
            $descriptionUz = trim((string) ($rowData['description_uz'] ?? $rowData['description'] ?? ''));
            $descriptionEn = trim((string) ($rowData['description_en'] ?? ''));
            $descriptionRu = trim((string) ($rowData['description_ru'] ?? ''));
            $cefrLevel = trim((string) ($rowData['cefr_level'] ?? $rowData['level'] ?? ''));
            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));

            if ($titleUz === '') {
                $errors[] = __('app.import_error_title', ['line' => $i + 1]);
                continue;
            }

            $topicKey = $this->resolveTopicKey($topicKey, $titleUz);

            $topic = GrammarTopic::where('topic_key', $topicKey)->first();
            if (!$topic) {
                $topic = new GrammarTopic();
                $topic->created_by = Auth::id();
                $topic->slug = $this->generateUniqueSlug($titleUz);
            }

            $topic->topic_key = $topicKey;
            $topic->title_uz = $titleUz;
            $topic->title_en = $titleEn !== '' ? $titleEn : null;
            $topic->title_ru = $titleRu !== '' ? $titleRu : null;
            $topic->description_uz = $descriptionUz !== '' ? $descriptionUz : null;
            $topic->description_en = $descriptionEn !== '' ? $descriptionEn : null;
            $topic->description_ru = $descriptionRu !== '' ? $descriptionRu : null;
            $topic->title = $titleUz;
            $topic->description = $descriptionUz !== '' ? $descriptionUz : null;
            $topic->cefr_level = in_array($cefrLevel, ['A1', 'A2', 'B1', 'B2', 'C1'], true) ? $cefrLevel : null;
            $topic->sort_order = $sortOrder;
            $topic->save();

            $imported['topics'] += 1;
        }
    }

    private function importRulesSheet(array $rows, array &$imported, array &$errors): void
    {
        $header = $this->normalizeHeader($rows[0] ?? []);
        if (!$this->looksLikeRuleHeader($header)) {
            $errors[] = __('app.import_failed');
            return;
        }

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            $rowData = array_combine($header, array_pad($row, count($header), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $i + 1]);
                continue;
            }

            $topicKey = trim((string) ($rowData['topic_key'] ?? $rowData['topic_slug'] ?? ''));
            $targetTopic = $this->resolveTopicFromKey($topicKey);
            if (!$targetTopic) {
                $errors[] = __('app.import_error_topic_key', ['line' => $i + 1, 'value' => $topicKey]);
                continue;
            }

            $ruleKey = trim((string) ($rowData['rule_key'] ?? $rowData['rule_id'] ?? ''));
            if ($ruleKey === '') {
                $errors[] = __('app.import_error_rule_id', ['line' => $i + 1]);
                continue;
            }

            $ruleUz = trim((string) ($rowData['rule_text_uz'] ?? $rowData['rule_uz'] ?? ''));
            $ruleEn = trim((string) ($rowData['rule_text_en'] ?? $rowData['rule_en'] ?? ''));
            $ruleRu = trim((string) ($rowData['rule_text_ru'] ?? $rowData['rule_ru'] ?? ''));
            $formula = trim((string) ($rowData['formula'] ?? ''));
            $exampleEn = trim((string) ($rowData['example_en'] ?? ''));
            $exampleUz = trim((string) ($rowData['example_uz'] ?? ''));
            $exampleRu = trim((string) ($rowData['example_ru'] ?? ''));
            $exampleNegative = trim((string) ($rowData['example_negative'] ?? $rowData['example_nega'] ?? ''));
            $commonMistake = trim((string) ($rowData['common_mistake'] ?? $rowData['common_mist'] ?? ''));
            $correctForm = trim((string) ($rowData['correct_form'] ?? ''));

            if ($ruleUz === '' && $ruleEn === '' && $ruleRu === '') {
                $errors[] = __('app.import_error_content', ['line' => $i + 1]);
                continue;
            }

            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));
            $imageUrl = trim((string) ($rowData['image_url'] ?? ''));
            $level = trim((string) ($rowData['cefr_level'] ?? $rowData['level'] ?? ''));
            $ruleType = trim((string) ($rowData['rule_type'] ?? 'core'));
            if ($ruleType === 'spelling') {
                $ruleType = 'usage';
            }
            if (!in_array($ruleType, ['core', 'usage', 'note', 'exception'], true)) {
                $ruleType = 'core';
            }

            $title = trim((string) ($rowData['title'] ?? ''));
            $content = trim((string) ($rowData['content'] ?? ''));
            $title = $title !== '' ? $title : $this->firstNonEmpty([$ruleUz, $ruleEn, $ruleRu, $ruleKey]);
            $content = $content !== '' ? $content : $this->firstNonEmpty([$ruleUz, $ruleEn, $ruleRu, $formula, $exampleUz, $exampleEn, $exampleRu, $exampleNegative, $commonMistake, $correctForm, $title]);

            GrammarRule::updateOrCreate(
                [
                    'grammar_topic_id' => $targetTopic->id,
                    'rule_key' => $this->normalizeRuleKey($ruleKey, $targetTopic),
                ],
                [
                    'level' => $level !== '' ? $level : null,
                    'cefr_level' => $level !== '' ? $level : null,
                    'rule_type' => $ruleType,
                    'title' => $title,
                    'content' => $content,
                    'rule_text_uz' => $ruleUz !== '' ? $ruleUz : null,
                    'rule_text_en' => $ruleEn !== '' ? $ruleEn : null,
                    'rule_text_ru' => $ruleRu !== '' ? $ruleRu : null,
                    'formula' => $formula !== '' ? $formula : null,
                    'example_uz' => $exampleUz !== '' ? $exampleUz : null,
                    'example_en' => $exampleEn !== '' ? $exampleEn : null,
                    'example_ru' => $exampleRu !== '' ? $exampleRu : null,
                    'negative_example' => $exampleNegative !== '' ? $exampleNegative : null,
                    'common_mistake' => $commonMistake !== '' ? $commonMistake : null,
                    'correct_form' => $correctForm !== '' ? $correctForm : null,
                    'sort_order' => $sortOrder,
                    'image_path' => $this->resolveImagePath($imageUrl),
                ]
            );

            $imported['rules'] += 1;
        }
    }

    private function importExercisesSheet(array $rows, array &$imported, array &$errors): void
    {
        $header = $this->normalizeHeader($rows[0] ?? []);
        if (!$this->looksLikeExerciseHeader($header)) {
            $errors[] = __('app.import_failed');
            return;
        }

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            $rowData = array_combine($header, array_pad($row, count($header), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $i + 1]);
                continue;
            }

            $topicKey = trim((string) ($rowData['topic_key'] ?? $rowData['topic_slug'] ?? ''));
            $targetTopic = $this->resolveTopicFromKey($topicKey);
            if (!$targetTopic) {
                $errors[] = __('app.import_error_topic_key', ['line' => $i + 1, 'value' => $topicKey]);
                continue;
            }

            $ruleIdentifier = trim((string) ($rowData['rule_key'] ?? $rowData['rule_id'] ?? ''));
            if ($ruleIdentifier === '') {
                $errors[] = __('app.import_error_rule_id', ['line' => $i + 1]);
                continue;
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
                $errors[] = __('app.import_error_rule_id', ['line' => $i + 1]);
                continue;
            }

            $exerciseId = trim((string) ($rowData['exercise_id'] ?? ''));
            $type = strtolower(trim((string) ($rowData['exercise_type'] ?? $rowData['type'] ?? '')));
            $type = $type !== '' ? $type : 'mcq';

            $question = trim((string) ($rowData['question'] ?? $rowData['prompt'] ?? ''));
            if ($question === '') {
                $errors[] = __('app.import_error_prompt', ['line' => $i + 1]);
                continue;
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
                    $i + 1,
                    $errors
                );
                if (!$normalized) {
                    continue;
                }
                [$optionsStore, $correctRaw] = $normalized;
            } elseif ($type === 'tf') {
                if ($correctRaw === '') {
                    $errors[] = __('app.import_error_correct', ['line' => $i + 1]);
                    continue;
                }
                $normalized = strtolower($correctRaw);
                if (!in_array($normalized, ['true', 'false'], true)) {
                    $errors[] = __('app.import_error_correct', ['line' => $i + 1]);
                    continue;
                }
                $correctRaw = $normalized;
            } else {
                if ($correctRaw === '') {
                    $errors[] = __('app.import_error_correct', ['line' => $i + 1]);
                    continue;
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

            $imported['exercises'] += 1;
        }
    }

    private function normalizeHeader(array $header): array
    {
        $header = array_map('trim', $header);
        if (!empty($header)) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        return array_map(static fn ($value) => strtolower($value), $header);
    }

    private function looksLikeTopicHeader(array $header): bool
    {
        $known = [
            'topic_key',
            'topic_slug',
            'title',
            'title_uz',
            'title_en',
            'title_ru',
            'description',
            'description_uz',
            'description_en',
            'description_ru',
            'cefr_level',
            'sort_order',
        ];

        foreach ($header as $value) {
            if (in_array($value, $known, true)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeRuleHeader(array $header): bool
    {
        $known = [
            'topic_key',
            'topic_slug',
            'rule_key',
            'rule_id',
            'rule_text_uz',
            'rule_text_en',
            'rule_text_ru',
            'rule_type',
            'formula',
            'example_uz',
            'example_en',
            'example_ru',
            'example_negative',
            'example_nega',
            'common_mistake',
            'common_mist',
            'correct_form',
        ];

        foreach ($header as $value) {
            if (in_array($value, $known, true)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeExerciseHeader(array $header): bool
    {
        $known = [
            'topic_key',
            'topic_slug',
            'rule_key',
            'rule_id',
            'exercise_id',
            'exercise_type',
            'type',
            'question',
            'prompt',
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

        foreach ($header as $value) {
            if (in_array($value, $known, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolveTopicFromKey(string $topicKey): ?GrammarTopic
    {
        $topicKey = trim($topicKey);
        if ($topicKey === '') {
            return null;
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

        $topic = GrammarTopic::whereRaw('LOWER(title) = ?', [Str::lower($topicKey)])->first();
        if ($topic) {
            return $topic;
        }

        if ($normalized !== '') {
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

    private function normalizeRuleKey(string $ruleKey, GrammarTopic $topic): string
    {
        $candidate = trim($ruleKey);
        if ($candidate === '') {
            $candidate = 'rule';
        }

        $base = $candidate;
        $suffix = 2;
        while (GrammarRule::where('grammar_topic_id', $topic->id)
            ->where('rule_key', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $candidate;
    }

    private function resolveImagePath(string $imageUrl): ?string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $imageUrl)) {
            return $imageUrl;
        }

        return ltrim($imageUrl, '/');
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
