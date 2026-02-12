<?php

namespace App\Http\Controllers;

use App\Models\GrammarExerciseAIHelp;
use App\Models\GrammarAttempt;
use App\Models\GrammarTopic;
use App\Models\GrammarRule;
use App\Models\GrammarTopicAIHelp;
use App\Models\AiRequest;
use Illuminate\Support\Facades\Auth;

class GrammarController extends Controller
{
    public function index()
    {
        $topics = GrammarTopic::withCount([
            'rules' => fn ($query) => $query->visible(),
            'exercises' => fn ($query) => $this->applyPracticeExerciseFilter($query),
        ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $progress = $this->grammarProgressData($topics);
        $topicProgress = $this->topicProgress($topics);

        return view('grammar.index', [
            'topics' => $topics,
            'recommendedTopicId' => $progress['recommendedTopicId'],
            'topicProgress' => $topicProgress,
        ]);
    }

    public function show(GrammarTopic $topic)
    {
        $progress = $this->grammarProgressData();
        $completedTopicIds = $progress['completedTopicIds'];
        $recommendedTopicId = $progress['recommendedTopicId'];

        if (Auth::check()
            && !in_array($topic->id, $completedTopicIds, true)
            && $recommendedTopicId !== null
            && $topic->id !== $recommendedTopicId
        ) {
            return redirect()
                ->route('grammar.index')
                ->with('status', __('app.complete_previous_topic_first'));
        }

        $topic->load([
            'rules' => fn ($query) => $query->visible()->orderBy('sort_order')->orderBy('id'),
        ])->loadCount([
            'exercises' => fn ($query) => $this->applyPracticeExerciseFilter($query),
        ]);

        $topicHelp = null;
        if (auth()->check()) {
            $topicHelp = GrammarTopicAIHelp::where('user_id', auth()->id())
                ->where('grammar_topic_id', $topic->id)
                ->latest('id')
                ->first();
            $this->syncAiHelpIfNeeded($topicHelp);
        }

        return view('grammar.show', [
            'topic' => $topic,
            'topicHelp' => $topicHelp,
        ]);
    }

    public function practice(GrammarTopic $topic)
    {
        $progress = $this->grammarProgressData();
        $completedTopicIds = $progress['completedTopicIds'];
        $recommendedTopicId = $progress['recommendedTopicId'];

        if (Auth::check()
            && !in_array($topic->id, $completedTopicIds, true)
            && $recommendedTopicId !== null
            && $topic->id !== $recommendedTopicId
        ) {
            return redirect()
                ->route('grammar.index')
                ->with('status', __('app.complete_previous_topic_first'));
        }

        $ruleParam = request()->query('rule');
        $levelParam = request()->query('level');
        $ruleId = null;
        if ($ruleParam) {
            $rule = GrammarRule::where('id', $ruleParam)
                ->orWhere('rule_key', $ruleParam)
                ->first();
            $ruleId = $rule?->id;
        }

        $topic->load([
            'exercises' => fn ($query) => $this->applyPracticeExerciseFilter($query, $ruleId, $levelParam)->orderBy('sort_order')->orderBy('id'),
        ]);

        $helpByExercise = collect();
        if (auth()->check() && $topic->exercises->isNotEmpty()) {
            $helps = GrammarExerciseAIHelp::where('user_id', auth()->id())
                ->whereIn('grammar_exercise_id', $topic->exercises->pluck('id'))
                ->orderByDesc('id')
                ->get();
            $this->syncAiHelpsCollection($helps);
            $helpByExercise = $helps->unique('grammar_exercise_id')->keyBy('grammar_exercise_id');
        }

        return view('grammar.practice', [
            'topic' => $topic,
            'helpByExercise' => $helpByExercise,
        ]);
    }

    private function grammarProgressData($topics = null): array
    {
        if (!Auth::check()) {
            return [
                'completedTopicIds' => [],
                'recommendedTopicId' => null,
            ];
        }

        $orderedTopics = $topics instanceof \Illuminate\Support\Collection
            ? $topics
            : GrammarTopic::orderBy('sort_order')->orderBy('id')->get(['id']);

        if ($orderedTopics->isEmpty()) {
            return [
                'completedTopicIds' => [],
                'recommendedTopicId' => null,
            ];
        }

        $completedTopicIds = GrammarAttempt::where('user_id', Auth::id())
            ->distinct()
            ->pluck('grammar_topic_id')
            ->all();

        $completedLookup = array_flip($completedTopicIds);
        $recommendedTopicId = null;

        foreach ($orderedTopics as $topic) {
            if (isset($completedLookup[$topic->id])) {
                continue;
            }

            if ($recommendedTopicId === null) {
                $recommendedTopicId = $topic->id;
                continue;
            }
        }

        return [
            'completedTopicIds' => $completedTopicIds,
            'recommendedTopicId' => $recommendedTopicId,
        ];
    }

    private function topicProgress(\Illuminate\Support\Collection $topics): array
    {
        if (!Auth::check() || $topics->isEmpty()) {
            return [];
        }

        $topicIds = $topics->pluck('id')->all();
        $attempts = GrammarAttempt::where('user_id', Auth::id())
            ->whereIn('grammar_topic_id', $topicIds)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get(['grammar_topic_id', 'score', 'total']);

        $progress = [];
        foreach ($attempts as $attempt) {
            if (isset($progress[$attempt->grammar_topic_id])) {
                continue;
            }

            $total = (int) ($attempt->total ?? 0);
            $score = (int) ($attempt->score ?? 0);
            $percent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

            $progress[$attempt->grammar_topic_id] = [
                'percent' => $percent,
                'score' => $score,
                'total' => $total,
            ];
        }

        return $progress;
    }

    private function applyPracticeExerciseFilter($query, ?int $ruleId = null, ?string $level = null)
    {
        $allowedTypes = ['mcq', 'gap', 'tf', 'reorder'];

        $query->where(function ($builder) use ($allowedTypes) {
            $builder->whereNull('exercise_type')
                ->orWhereIn('exercise_type', $allowedTypes)
                ->orWhereIn('type', $allowedTypes);
        })->whereHas('rule', function ($builder) {
            $builder->whereNull('rule_key')->orWhere('rule_key', '!=', '__unmapped__');
        });

        if ($ruleId) {
            $query->where('grammar_rule_id', $ruleId);
        }

        if ($level) {
            $query->where(function ($builder) use ($level) {
                $builder->where('cefr_level', $level)
                    ->orWhereHas('rule', fn ($ruleQuery) => $ruleQuery->where('cefr_level', $level));
            });
        }

        return $query;
    }


    private function syncAiHelpIfNeeded(?object $help): void
    {
        if (!$help || !in_array($help->status, ['queued', 'processing'], true) || !$help->ai_request_id) {
            return;
        }

        $aiRequest = AiRequest::find($help->ai_request_id);
        if (!$aiRequest) {
            return;
        }

        if ($aiRequest->status === 'pending' && $aiRequest->isQuotaError()) {
            $aiRequest->update([
                'status' => 'failed_quota',
                'finished_at' => now(),
            ]);
            $help->update([
                'status' => 'failed',
                'error_message' => $aiRequest->error_text,
            ]);
            return;
        }

        if ($aiRequest->status === 'done') {
            $help->update([
                'status' => 'done',
                'ai_response' => $aiRequest->output_json['text'] ?? null,
                'error_message' => null,
            ]);
        } elseif (in_array($aiRequest->status, ['failed', 'failed_quota'], true)) {
            $help->update([
                'status' => 'failed',
                'error_message' => $aiRequest->error_text,
            ]);
        } elseif ($aiRequest->status === 'processing') {
            $help->update(['status' => 'processing']);
        }
    }

    private function syncAiHelpsCollection($helps): void
    {
        $pending = $helps
            ->whereIn('status', ['queued', 'processing'])
            ->whereNotNull('ai_request_id');

        if ($pending->isEmpty()) {
            return;
        }

        $requestIds = $pending->pluck('ai_request_id')->unique()->values();
        $requests = AiRequest::whereIn('id', $requestIds)->get()->keyBy('id');

        foreach ($pending as $help) {
            $aiRequest = $requests->get($help->ai_request_id);
            if (!$aiRequest) {
                continue;
            }

            if ($aiRequest->status === 'pending' && $aiRequest->isQuotaError()) {
                $aiRequest->update([
                    'status' => 'failed_quota',
                    'finished_at' => now(),
                ]);
                $help->update([
                    'status' => 'failed',
                    'error_message' => $aiRequest->error_text,
                ]);
                continue;
            }

            if ($aiRequest->status === 'done') {
                $help->update([
                    'status' => 'done',
                    'ai_response' => $aiRequest->output_json['text'] ?? null,
                    'error_message' => null,
                ]);
            } elseif (in_array($aiRequest->status, ['failed', 'failed_quota'], true)) {
                $help->update([
                    'status' => 'failed',
                    'error_message' => $aiRequest->error_text,
                ]);
            } elseif ($aiRequest->status === 'processing') {
                $help->update(['status' => 'processing']);
            }
        }
    }
}
