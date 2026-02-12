<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitExceededException;
use App\Models\UserVocab;
use App\Models\VocabItem;
use App\Models\VocabList;
use App\Services\AI\GeminiClient;
use App\Services\AI\GeminiException;
use App\Services\AI\GroqClient;
use App\Services\AI\GroqException;
use App\Services\AI\RateLimiterMySql;
use App\Services\FeatureGate;
use App\Services\UsageLimiter;
use App\Services\Vocabulary\SrsScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VocabularyController extends Controller
{
    public function index()
    {
        $lists = VocabList::withCount('items')->latest()->paginate(12);

        return view('vocabulary.index', compact('lists'));
    }

    public function translatePage()
    {
        return view('vocabulary.translate', $this->buildTranslatePageData());
    }

    public function show(VocabList $list)
    {
        $list->loadCount('items');

        $userId = auth()->id();

        $items = VocabItem::where('vocab_list_id', $list->id)
            ->orderBy('term')
            ->paginate(40)
            ->withQueryString();

        $dueCount = UserVocab::where('user_id', $userId)
            ->whereHas('item', fn ($q) => $q->where('vocab_list_id', $list->id))
            ->where(function ($q) {
                $q->whereNull('next_review_at')
                    ->orWhere('next_review_at', '<=', now());
            })
            ->count();

        $newCount = VocabItem::where('vocab_list_id', $list->id)
            ->whereDoesntHave('progress', fn ($q) => $q->where('user_id', $userId))
            ->count();

        $queueCount = $dueCount + $newCount;

        $start = Carbon::now()->subDays(6)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $raw = UserVocab::where('user_id', $userId)
            ->whereHas('item', fn ($q) => $q->where('vocab_list_id', $list->id))
            ->whereBetween('last_reviewed_at', [$start, $end])
            ->selectRaw('DATE(last_reviewed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $progressDays = collect(range(6, 0))->map(function ($offset) use ($raw) {
            $date = Carbon::now()->subDays($offset)->toDateString();
            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M d'),
                'count' => (int) ($raw[$date] ?? 0),
            ];
        });

        $weeklyReviews = $progressDays->sum('count');

        $missingTranslations = VocabItem::where('vocab_list_id', $list->id)
            ->where(function ($q) {
                $q->whereNull('definition_uz')
                    ->orWhere('definition_uz', '')
                    ->orWhereNull('definition_ru')
                    ->orWhere('definition_ru', '');
            })
            ->count();

        return view('vocabulary.show', compact('list', 'dueCount', 'newCount', 'queueCount', 'progressDays', 'weeklyReviews', 'items', 'missingTranslations'));
    }

    public function translate(
        Request $request,
        VocabList $list,
        RateLimiterMySql $rateLimiter,
        FeatureGate $featureGate,
        UsageLimiter $usageLimiter,
        GeminiClient $geminiClient,
        GroqClient $groqClient
    ) {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'force' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        if ($user) {
            $allowed = $rateLimiter->hit('user', (string) $user->id, (int) config('ai.user_rpm', 20));
            if (!$allowed) {
                return back()->with('status', __('app.rate_limit_exceeded'));
            }
        }

        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$globalAllowed) {
            return back()->with('status', __('app.rate_limit_exceeded'));
        }

        $plan = $user ? $featureGate->currentPlan($user) : null;
        if ($user && $plan?->slug === 'free') {
            try {
                $usageLimiter->assertWithinLimit($user, 'ai_daily');
            } catch (LimitExceededException $e) {
                return back()
                    ->with('status', __('app.daily_limit_reached'))
                    ->with('upgrade_prompt', true);
            }
        }

        $limit = (int) ($data['limit'] ?? 40);
        $force = (bool) ($data['force'] ?? false);

        $query = VocabItem::where('vocab_list_id', $list->id);
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('definition_uz')
                    ->orWhere('definition_uz', '')
                    ->orWhereNull('definition_ru')
                    ->orWhere('definition_ru', '');
            });
        }

        $items = $query->orderBy('term')->limit($limit)->get([
            'id',
            'term',
            'definition',
            'definition_uz',
            'definition_ru',
            'example',
            'part_of_speech',
        ]);

        if ($items->isEmpty()) {
            return back()->with('status', __('app.vocab_translate_none'));
        }

        $prompt = $this->buildTranslationPrompt($items);
        $parameters = [
            'task' => 'vocab_translate',
            'temperature' => 0.2,
            'max_output_tokens' => min(2048, 300 + ($items->count() * 40)),
        ];

        try {
            $provider = config('ai.provider', 'gemini');
            $result = $provider === 'groq'
                ? $groqClient->generate($prompt, [], $parameters)
                : $geminiClient->generate($prompt, [], $parameters);
        } catch (GeminiException|GroqException $e) {
            return back()->with('status', __('app.vocab_translate_failed', ['message' => $e->getMessage()]));
        } catch (\Throwable $e) {
            return back()->with('status', __('app.vocab_translate_failed', ['message' => $e->getMessage()]));
        }

        $payload = $this->extractJson((string) ($result['text'] ?? ''));
        if (!is_array($payload)) {
            return back()->with('status', __('app.vocab_translate_failed', ['message' => __('app.ai_help_failed')]));
        }

        $itemsById = $items->keyBy('id');
        $updated = 0;

        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            if (!$id || !$itemsById->has($id)) {
                continue;
            }

            $item = $itemsById->get($id);
            $update = [];
            $uz = trim((string) ($row['uz'] ?? $row['uzbek'] ?? ''));
            $ru = trim((string) ($row['ru'] ?? $row['russian'] ?? ''));

            if (($force || blank($item->definition_uz)) && $uz !== '') {
                $update['definition_uz'] = $uz;
            }

            if (($force || blank($item->definition_ru)) && $ru !== '') {
                $update['definition_ru'] = $ru;
            }

            if (!empty($update)) {
                $item->update($update);
                $updated++;
            }
        }

        if ($user && $plan?->slug === 'free') {
            $usageLimiter->increment($user, 'ai_daily');
        }

        $message = $updated > 0
            ? __('app.vocab_translate_success', ['count' => $updated])
            : __('app.vocab_translate_none');

        return back()->with('status', $message);
    }

    public function translateFromForm(
        Request $request,
        RateLimiterMySql $rateLimiter,
        FeatureGate $featureGate,
        UsageLimiter $usageLimiter,
        GeminiClient $geminiClient,
        GroqClient $groqClient
    ) {
        $data = $request->validate([
            'list_id' => ['required', 'integer', 'exists:vocab_lists,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $list = VocabList::findOrFail((int) $data['list_id']);

        return $this->translate($request, $list, $rateLimiter, $featureGate, $usageLimiter, $geminiClient, $groqClient);
    }

    public function quickTranslate(
        Request $request,
        RateLimiterMySql $rateLimiter,
        FeatureGate $featureGate,
        UsageLimiter $usageLimiter,
        GeminiClient $geminiClient,
        GroqClient $groqClient
    ) {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:80'],
            'example' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();
        if ($user) {
            $allowed = $rateLimiter->hit('user', (string) $user->id, (int) config('ai.user_rpm', 20));
            if (!$allowed) {
                return view('vocabulary.translate', $this->buildTranslatePageData(null, __('app.rate_limit_exceeded'), $data['term'], $data['example'] ?? null));
            }
        }

        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$globalAllowed) {
            return view('vocabulary.translate', $this->buildTranslatePageData(null, __('app.rate_limit_exceeded'), $data['term'], $data['example'] ?? null));
        }

        $plan = $user ? $featureGate->currentPlan($user) : null;
        if ($user && $plan?->slug === 'free') {
            try {
                $usageLimiter->assertWithinLimit($user, 'ai_daily');
            } catch (LimitExceededException $e) {
                return view('vocabulary.translate', $this->buildTranslatePageData(null, __('app.daily_limit_reached'), $data['term'], $data['example'] ?? null, true));
            }
        }

        $term = trim($data['term']);
        $example = trim((string) ($data['example'] ?? ''));

        $prompt = $this->buildQuickTranslatePrompt($term, $example);
        $parameters = [
            'task' => 'vocab_quick_translate',
            'temperature' => 0.2,
            'max_output_tokens' => 256,
        ];

        try {
            $provider = config('ai.provider', 'gemini');
            $result = $provider === 'groq'
                ? $groqClient->generate($prompt, [], $parameters)
                : $geminiClient->generate($prompt, [], $parameters);
        } catch (GeminiException|GroqException $e) {
            $message = __('app.vocab_translate_failed', ['message' => $e->getMessage()]);
            return view('vocabulary.translate', $this->buildTranslatePageData(null, $message, $term, $example));
        } catch (\Throwable $e) {
            $message = __('app.vocab_translate_failed', ['message' => $e->getMessage()]);
            return view('vocabulary.translate', $this->buildTranslatePageData(null, $message, $term, $example));
        }

        $payload = $this->extractJson((string) ($result['text'] ?? ''));
        if (!is_array($payload)) {
            return view('vocabulary.translate', $this->buildTranslatePageData(null, __('app.ai_help_failed'), $term, $example));
        }

        $quickResult = [
            'term' => $term,
            'example' => $example,
            'uz' => trim((string) ($payload['uz'] ?? $payload['uzbek'] ?? '')),
            'ru' => trim((string) ($payload['ru'] ?? $payload['russian'] ?? '')),
        ];

        if ($user && $plan?->slug === 'free') {
            $usageLimiter->increment($user, 'ai_daily');
        }

        return view('vocabulary.translate', $this->buildTranslatePageData($quickResult, null, $term, $example));
    }

    public function review(VocabList $list)
    {
        $item = $this->nextItem($list);

        return view('vocabulary.review', [
            'list' => $list,
            'item' => $item,
        ]);
    }

    public function grade(Request $request, VocabList $list, VocabItem $item, SrsScheduler $scheduler)
    {
        $this->ensureListMatch($list, $item);

        $data = $request->validate([
            'quality' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        $progress = UserVocab::firstOrCreate([
            'user_id' => auth()->id(),
            'vocab_item_id' => $item->id,
        ]);

        $scheduler->grade($progress, (int) $data['quality'])->save();

        return redirect()->route('vocabulary.review', $list);
    }

    public function reset(VocabList $list)
    {
        UserVocab::where('user_id', auth()->id())
            ->whereHas('item', fn ($q) => $q->where('vocab_list_id', $list->id))
            ->delete();

        return redirect()->route('vocabulary.review', $list);
    }

    private function nextItem(VocabList $list): ?VocabItem
    {
        $userId = auth()->id();

        $dueProgress = UserVocab::where('user_id', $userId)
            ->whereHas('item', fn ($q) => $q->where('vocab_list_id', $list->id))
            ->where(function ($q) {
                $q->whereNull('next_review_at')
                    ->orWhere('next_review_at', '<=', now());
            })
            ->orderBy('next_review_at')
            ->first();

        if ($dueProgress) {
            return $dueProgress->item;
        }

        return VocabItem::where('vocab_list_id', $list->id)
            ->whereDoesntHave('progress', fn ($q) => $q->where('user_id', $userId))
            ->first();
    }

    private function ensureListMatch(VocabList $list, VocabItem $item): void
    {
        if ($item->vocab_list_id !== $list->id) {
            abort(404);
        }
    }

    private function buildTranslationPrompt($items): string
    {
        $payload = $items->map(function (VocabItem $item) {
            return [
                'id' => $item->id,
                'term' => $item->term,
                'definition' => $item->definition,
                'part_of_speech' => $item->part_of_speech,
                'example' => $item->example,
            ];
        })->values()->all();

        return implode("\n", [
            'You are a professional IELTS vocabulary translator.',
            'Translate each item into Uzbek (Latin) and Russian (Cyrillic).',
            'Return JSON array only in this shape: [{"id":1,"uz":"...","ru":"..."}].',
            'If a translation is unknown, use empty string.',
            'Do not include extra keys or explanations.',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function buildQuickTranslatePrompt(string $term, string $example): string
    {
        $lines = [
            'Translate the word or phrase into Uzbek (Latin) and Russian (Cyrillic).',
            'Return JSON only in this shape: {"uz":"...","ru":"..."}.',
            'If unclear, use the example to infer meaning.',
            'Do not include extra keys or explanations.',
            "Term: {$term}",
        ];

        if ($example !== '') {
            $lines[] = "Example: {$example}";
        }

        return implode("\n", $lines);
    }

    private function buildTranslatePageData(
        ?array $quickResult = null,
        ?string $quickError = null,
        ?string $quickTerm = null,
        ?string $quickExample = null,
        bool $upgradePrompt = false
    ): array {
        $lists = VocabList::withCount('items')
            ->orderBy('title')
            ->get();

        $missingCounts = VocabItem::selectRaw("
                vocab_list_id,
                SUM(CASE
                    WHEN definition_uz IS NULL OR definition_uz = ''
                         OR definition_ru IS NULL OR definition_ru = ''
                    THEN 1 ELSE 0 END) as missing_count
            ")
            ->groupBy('vocab_list_id')
            ->pluck('missing_count', 'vocab_list_id');

        if ($upgradePrompt) {
            session()->flash('upgrade_prompt', true);
            session()->flash('status', $quickError ?? __('app.daily_limit_reached'));
        }

        return [
            'lists' => $lists,
            'missingCounts' => $missingCounts,
            'quickResult' => $quickResult,
            'quickError' => $quickError,
            'quickTerm' => $quickTerm,
            'quickExample' => $quickExample,
        ];
    }

    private function extractJson(string $content): ?array
    {
        $candidates = $this->buildJsonCandidates($content);
        foreach ($candidates as $candidate) {
            $decoded = $this->decodeJsonCandidate($candidate);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function buildJsonCandidates(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];
        $fenceStripped = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $fenceStripped = preg_replace('/\s*```$/', '', (string) $fenceStripped);
        $fenceStripped = trim((string) $fenceStripped);
        if ($fenceStripped !== '' && $fenceStripped !== $trimmed) {
            $candidates[] = $fenceStripped;
        }

        $start = strpos($trimmed, '[');
        $end = strrpos($trimmed, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($trimmed, $start, $end - $start + 1);
        }

        $startObj = strpos($trimmed, '{');
        $endObj = strrpos($trimmed, '}');
        if ($startObj !== false && $endObj !== false && $endObj > $startObj) {
            $candidates[] = substr($trimmed, $startObj, $endObj - $startObj + 1);
        }

        return array_values(array_unique($candidates));
    }

    private function decodeJsonCandidate(string $candidate): ?array
    {
        $normalized = $this->normalizeJsonCandidate($candidate);
        if ($normalized === '') {
            return null;
        }

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $unescaped = stripslashes($normalized);
        if ($unescaped !== $normalized) {
            $decoded = json_decode($unescaped, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeJsonCandidate(string $candidate): string
    {
        $normalized = trim($candidate);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized);
        $normalized = str_replace(
            ["\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}"],
            '"',
            $normalized
        );
        $normalized = str_replace(["\u{2018}", "\u{2019}"], "'", $normalized);
        $normalized = preg_replace('/,\s*([}\]])/', '$1', $normalized);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $normalized);

        return trim((string) $normalized);
    }
}
