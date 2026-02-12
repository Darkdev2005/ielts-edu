<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\GrammarRule;
use App\Models\GrammarTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GrammarRuleController extends Controller
{
    public function index(GrammarTopic $topic)
    {
        $topic->load(['rules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return view('admin.grammar.rules.index', compact('topic'));
    }

    public function create(GrammarTopic $topic)
    {
        return view('admin.grammar.rules.create', compact('topic'));
    }

    public function store(Request $request, GrammarTopic $topic)
    {
        $data = $this->validateRule($request, $topic);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['rule_key'] = $this->normalizeRuleKey($data['rule_key'] ?? null, $topic);
        $data['title'] = $this->resolveRuleTitle($data);
        $data['content'] = $this->resolveRuleContent($data);
        if (!empty($data['cefr_level']) && empty($data['level'])) {
            $data['level'] = $data['cefr_level'];
        }
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('grammar/rules', 'public');
        }
        $topic->rules()->create($data);

        return redirect()->route('admin.grammar.rules.index', $topic);
    }

    public function import(Request $request, GrammarTopic $topic)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $data['csv'];
        if (!$file->isValid()) {
            return redirect()
                ->route('admin.grammar.rules.index', $topic)
                ->with('import_errors', [__('app.import_failed')]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $defaultHeader = ['title', 'content', 'sort_order', 'image_url'];
        $imported = 0;
        $errors = [];
        $lineNumber = 1;

        $processLegacyRow = function (array $row) use ($topic, &$imported, &$errors, $defaultHeader, &$lineNumber) {
            $lineNumber += 1;
            if (count($row) === 1 && trim($row[0] ?? '') === '') {
                return;
            }

            $rowData = array_combine($defaultHeader, array_pad($row, count($defaultHeader), null));
            if (!$rowData) {
                $errors[] = __('app.import_row_invalid', ['line' => $lineNumber]);
                return;
            }

            $title = trim((string) ($rowData['title'] ?? ''));
            $content = trim((string) ($rowData['content'] ?? ''));
            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));
            $imageUrl = trim((string) ($rowData['image_url'] ?? ''));

            if ($title === '') {
                $errors[] = __('app.import_error_title', ['line' => $lineNumber]);
                return;
            }

            if ($content === '') {
                $errors[] = __('app.import_error_content', ['line' => $lineNumber]);
                return;
            }

            $topic->rules()->create([
                'rule_key' => $this->normalizeRuleKey(null, $topic),
                'rule_type' => 'core',
                'rule_text_uz' => $content,
                'title' => $title,
                'content' => $content,
                'sort_order' => $sortOrder,
                'image_path' => $this->resolveImagePath($imageUrl),
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

            $ruleKey = trim((string) ($rowData['rule_key'] ?? $rowData['rule_id'] ?? ''));
            if ($ruleKey === '') {
                $errors[] = __('app.import_error_rule_id', ['line' => $lineNumber]);
                return;
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
                $errors[] = __('app.import_error_content', ['line' => $lineNumber]);
                return;
            }

            $sortOrder = (int) trim((string) ($rowData['sort_order'] ?? '0'));
            $imageUrl = trim((string) ($rowData['image_url'] ?? ''));
            $level = trim((string) ($rowData['cefr_level'] ?? $rowData['level'] ?? ''));
            $ruleType = trim((string) ($rowData['rule_type'] ?? 'core'));

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
                    'rule_type' => $ruleType !== '' ? $ruleType : null,
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

            $imported += 1;
        };

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $rows = $this->readSpreadsheetRows($file, 'grammar_rules');
            if (empty($rows)) {
                return redirect()
                    ->route('admin.grammar.rules.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $header = $this->normalizeHeader($rows[0] ?? []);
            $hasHeader = $this->looksLikeHeader($header);
            $schema = $hasHeader ? $this->detectRuleSchema($header) : 'legacy';
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
                    ->route('admin.grammar.rules.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);

                return redirect()
                    ->route('admin.grammar.rules.index', $topic)
                    ->with('import_errors', [__('app.import_failed')]);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = $this->normalizeHeader(str_getcsv($firstLine, $delimiter));
            $hasHeader = $this->looksLikeHeader($header);
            $schema = $hasHeader ? $this->detectRuleSchema($header) : 'legacy';

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
            ->route('admin.grammar.rules.index', $topic)
            ->with('imported', $imported)
            ->with('import_errors', $errors);
    }

    public function downloadSample(Request $request, GrammarTopic $topic)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $headings = [
            'topic_key',
            'rule_key',
            'cefr_level',
            'rule_type',
            'sort_order',
            'rule_text_uz',
            'rule_text_en',
            'rule_text_ru',
            'formula',
            'example_uz',
            'example_en',
            'example_ru',
            'example_nega',
            'title',
            'content',
            'image_url',
        ];
        $rows = [
            [
                $topic->topic_key ?? $topic->slug ?? ('topic-'.$topic->id),
                'subject_pronouns_basic',
                $topic->cefr_level ?: 'A1',
                'core',
                1,
                'Subject pronoun gap boshida keladi.',
                'Subject pronouns come at the beginning of the sentence.',
                'Lichnye mestoimeniya stoyat v nachale predlozheniya.',
                'Subject + verb + object',
                'Men ishlayman.',
                'I work.',
                'Ya rabotayu.',
                'Work I.',
                'Subject Pronouns',
                'Subject pronoun gap boshida keladi.',
                '',
            ],
        ];

        $export = new ArrayExport($headings, $rows);
        $filename = 'grammar-topic-'.$topic->id.'-rules-sample.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            $export,
            $filename,
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX
        );
    }

    public function edit(GrammarTopic $topic, GrammarRule $rule)
    {
        $this->ensureTopicMatch($topic, $rule);

        return view('admin.grammar.rules.edit', compact('topic', 'rule'));
    }

    public function update(Request $request, GrammarTopic $topic, GrammarRule $rule)
    {
        $this->ensureTopicMatch($topic, $rule);

        $data = $this->validateRule($request, $topic, $rule->id);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['rule_key'] = $this->normalizeRuleKey($data['rule_key'] ?? $rule->rule_key, $topic, $rule->id);
        $data['title'] = $this->resolveRuleTitle($data);
        $data['content'] = $this->resolveRuleContent($data);
        if (!empty($data['cefr_level']) && empty($data['level'])) {
            $data['level'] = $data['cefr_level'];
        }
        if ($request->boolean('remove_image')) {
            if ($rule->image_path) {
                Storage::disk('public')->delete($rule->image_path);
            }
            $data['image_path'] = null;
        }
        if ($request->hasFile('image')) {
            if ($rule->image_path) {
                Storage::disk('public')->delete($rule->image_path);
            }
            $data['image_path'] = $request->file('image')->store('grammar/rules', 'public');
        }
        $rule->update($data);

        return redirect()->route('admin.grammar.rules.index', $topic);
    }

    public function destroy(GrammarTopic $topic, GrammarRule $rule)
    {
        $this->ensureTopicMatch($topic, $rule);
        $rule->delete();

        return redirect()->route('admin.grammar.rules.index', $topic);
    }

    private function validateRule(Request $request, GrammarTopic $topic, ?int $ignoreId = null): array
    {
        $uniqueRuleKey = Rule::unique('grammar_rules', 'rule_key')->where('grammar_topic_id', $topic->id);
        if ($ignoreId) {
            $uniqueRuleKey = $uniqueRuleKey->ignore($ignoreId);
        }

        return $request->validate([
            'rule_key' => ['nullable', 'string', 'max:120', $uniqueRuleKey],
            'rule_type' => ['required', 'in:core,usage,note,exception'],
            'cefr_level' => ['nullable', 'in:A1,A2,B1,B2,C1'],
            'rule_text_uz' => ['required', 'string'],
            'rule_text_en' => ['nullable', 'string'],
            'rule_text_ru' => ['nullable', 'string'],
            'formula' => ['nullable', 'string'],
            'example_uz' => ['nullable', 'string'],
            'example_en' => ['nullable', 'string'],
            'example_ru' => ['nullable', 'string'],
            'negative_example' => ['nullable', 'string'],
            'common_mistake' => ['nullable', 'string'],
            'correct_form' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
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
            'title',
            'content',
            'sort_order',
            'image_url',
            'topic_key',
            'topic_slug',
            'rule_id',
            'rule_key',
            'level',
            'cefr_level',
            'rule_type',
            'rule_text_uz',
            'rule_text_en',
            'rule_text_ru',
            'rule_uz',
            'rule_en',
            'rule_ru',
            'formula',
            'example_en',
            'example_uz',
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

    private function detectRuleSchema(array $header): string
    {
        $markers = [
            'topic_key',
            'topic_slug',
            'rule_id',
            'rule_key',
            'rule_text_uz',
            'rule_text_en',
            'rule_text_ru',
            'rule_type',
            'formula',
            'common_mistake',
            'correct_form',
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

        $topic = GrammarTopic::whereRaw('LOWER(title) = ?', [Str::lower($topicKey)])->first();
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

    private function ensureTopicMatch(GrammarTopic $topic, GrammarRule $rule): void
    {
        if ($rule->grammar_topic_id !== $topic->id) {
            abort(404);
        }
    }

    private function normalizeRuleKey(?string $ruleKey, GrammarTopic $topic, ?int $ignoreId = null): string
    {
        $candidate = trim((string) $ruleKey);
        if ($candidate === '') {
            $candidate = 'rule';
        }

        $base = $candidate;
        $suffix = 2;
        while (GrammarRule::where('grammar_topic_id', $topic->id)
            ->where('rule_key', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $candidate;
    }

    private function resolveRuleTitle(array $data): string
    {
        $title = trim((string) ($data['rule_text_uz'] ?? ''));
        if ($title !== '') {
            return Str::limit($title, 120, '');
        }

        $title = trim((string) ($data['rule_text_en'] ?? ''));
        if ($title !== '') {
            return Str::limit($title, 120, '');
        }

        $title = trim((string) ($data['rule_text_ru'] ?? ''));
        if ($title !== '') {
            return Str::limit($title, 120, '');
        }

        return (string) ($data['rule_key'] ?? 'Rule');
    }

    private function resolveRuleContent(array $data): string
    {
        return $this->firstNonEmpty([
            $data['rule_text_uz'] ?? '',
            $data['rule_text_en'] ?? '',
            $data['rule_text_ru'] ?? '',
            $data['formula'] ?? '',
            $data['example_uz'] ?? '',
            $data['example_en'] ?? '',
            $data['example_ru'] ?? '',
            $data['negative_example'] ?? '',
            $data['common_mistake'] ?? '',
            $data['correct_form'] ?? '',
        ]);
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
