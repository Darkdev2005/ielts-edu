<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\GrammarAttempt;
use App\Models\GrammarTopic;
use App\Models\UserStudyPlan;
use App\Models\UserVocab;
use App\Models\VocabItem;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $userId = Auth::id();
        $user = Auth::user();
        $canWriting = $user && ($user->is_admin || $featureGate->userCan($user, 'writing_ai'));
        $canSpeaking = $user && ($user->is_admin || $featureGate->userCan($user, 'speaking_ai'));
        $canAnalyticsFull = $user && ($user->is_admin || $featureGate->userCan($user, 'analytics_full'));
        $currentPlan = $user ? $featureGate->currentPlan($user) : null;

        $recentAttempts = Attempt::with('lesson')
            ->where('user_id', $userId)
            ->latest()
            ->take(10)
            ->get();

        $lastAttempt = Attempt::with('lesson')
            ->where('user_id', $userId)
            ->latest('completed_at')
            ->first();

        $stats = Attempt::where('user_id', $userId)
            ->select(
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('AVG(score / NULLIF(total, 0)) as avg_score')
            )
            ->first();

        $grammarStats = GrammarAttempt::where('user_id', $userId)
            ->select(
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('AVG(score / NULLIF(total, 0)) as avg_score')
            )
            ->first();

        $grammarTopicsTotal = GrammarTopic::count();
        $grammarTopicsPracticed = GrammarAttempt::where('user_id', $userId)
            ->distinct('grammar_topic_id')
            ->count('grammar_topic_id');
        $grammarBestScore = (float) GrammarAttempt::where('user_id', $userId)
            ->selectRaw('MAX(score / NULLIF(total, 0)) as best_score')
            ->value('best_score');

        $dashboardGrammarTopics = GrammarTopic::orderBy('sort_order')
            ->orderBy('id')
            ->take(4)
            ->get();

        $dashboardTopicProgress = $this->topicProgressForTopics($dashboardGrammarTopics, $userId);

        $recommendedTopicId = null;
        if ($userId) {
            $completedTopicIds = GrammarAttempt::where('user_id', $userId)
                ->distinct()
                ->pluck('grammar_topic_id')
                ->all();
            $completedLookup = array_flip($completedTopicIds);
            $orderedTopicIds = GrammarTopic::orderBy('sort_order')->orderBy('id')->pluck('id');
            foreach ($orderedTopicIds as $topicId) {
                if (!isset($completedLookup[$topicId])) {
                    $recommendedTopicId = $topicId;
                    break;
                }
            }
        }

        $vocabTotalWords = VocabItem::count();
        $vocabReviewed = UserVocab::where('user_id', $userId)->count();
        $vocabMastered = UserVocab::where('user_id', $userId)
            ->where('repetitions', '>=', 3)
            ->count();
        $vocabDue = UserVocab::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('next_review_at')
                    ->orWhere('next_review_at', '<=', now());
            })
            ->count();
        $vocabNew = VocabItem::whereDoesntHave('progress', fn ($q) => $q->where('user_id', $userId))
            ->count();

        $start = Carbon::now()->subDays(6)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $raw = UserVocab::where('user_id', $userId)
            ->whereBetween('last_reviewed_at', [$start, $end])
            ->selectRaw('DATE(last_reviewed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $vocabProgressDays = collect(range(6, 0))->map(function ($offset) use ($raw) {
            $date = Carbon::now()->subDays($offset)->toDateString();
            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M d'),
                'count' => (int) ($raw[$date] ?? 0),
            ];
        });

        $weeklyVocabReviews = $vocabProgressDays->sum('count');

        $rawGrammar = GrammarAttempt::where('user_id', $userId)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $grammarProgressDays = collect(range(6, 0))->map(function ($offset) use ($rawGrammar) {
            $date = Carbon::now()->subDays($offset)->toDateString();
            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M d'),
                'count' => (int) ($rawGrammar[$date] ?? 0),
            ];
        });

        $weeklyGrammarAttempts = $grammarProgressDays->sum('count');

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $studyPlan = UserStudyPlan::firstOrCreate(
            [
                'user_id' => $userId,
                'week_start_date' => $weekStart->toDateString(),
            ],
            [
                'lessons_target' => 3,
                'grammar_target' => 2,
                'vocab_target' => 1,
            ]
        );

        $lessonsDone = Attempt::where('user_id', $userId)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->count();

        $grammarDone = GrammarAttempt::where('user_id', $userId)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->count();

        $vocabDone = UserVocab::where('user_id', $userId)
            ->whereBetween('last_reviewed_at', [$weekStart, $weekEnd])
            ->count();

        $studyProgress = [
            'lessons' => [
                'done' => $lessonsDone,
                'target' => (int) $studyPlan->lessons_target,
                'percent' => $studyPlan->lessons_target > 0 ? min(100, (int) round(($lessonsDone / $studyPlan->lessons_target) * 100)) : 0,
            ],
            'grammar' => [
                'done' => $grammarDone,
                'target' => (int) $studyPlan->grammar_target,
                'percent' => $studyPlan->grammar_target > 0 ? min(100, (int) round(($grammarDone / $studyPlan->grammar_target) * 100)) : 0,
            ],
            'vocab' => [
                'done' => $vocabDone,
                'target' => (int) $studyPlan->vocab_target,
                'percent' => $studyPlan->vocab_target > 0 ? min(100, (int) round(($vocabDone / $studyPlan->vocab_target) * 100)) : 0,
            ],
        ];

        if ($canAnalyticsFull) {
            $requestedWeakDays = request('weak_days');
            if ($requestedWeakDays !== null) {
                $weakDays = (int) $requestedWeakDays;
                $weakDays = in_array($weakDays, [7, 30], true) ? $weakDays : 30;
                session(['weak_days' => $weakDays]);
            } else {
                $weakDays = (int) session('weak_days', 30);
                $weakDays = in_array($weakDays, [7, 30], true) ? $weakDays : 30;
            }
        } else {
            $weakDays = 7;
        }
        $weakStart = Carbon::now()->subDays($weakDays)->startOfDay();
        $weakEnd = Carbon::now()->endOfDay();

        $lessonWeaknesses = AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('lessons', 'lessons.id', '=', 'attempts.lesson_id')
            ->where('attempts.user_id', $userId)
            ->whereBetween(DB::raw('COALESCE(attempts.completed_at, attempts.created_at)'), [$weakStart, $weakEnd])
            ->groupBy('lessons.id', 'lessons.title', 'lessons.type', 'lessons.difficulty')
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.type',
                'lessons.difficulty',
                DB::raw('COUNT(*) as total_answers'),
                DB::raw('SUM(CASE WHEN attempt_answers.is_correct = 0 THEN 1 ELSE 0 END) as wrong_answers'),
                DB::raw('MAX(COALESCE(attempts.completed_at, attempts.created_at)) as last_attempted_at')
            )
            ->orderByRaw('SUM(CASE WHEN attempt_answers.is_correct = 0 THEN 1 ELSE 0 END) DESC')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_answers;
                $wrong = (int) $row->wrong_answers;
                $accuracy = $total > 0 ? round((($total - $wrong) / $total) * 100) : 0;
                $row->accuracy = $accuracy;
                $row->wrong_answers = $wrong;
                return $row;
            });

        $lessonWeaknesses = $lessonWeaknesses
            ->sortBy('accuracy')
            ->take(3)
            ->values();

        $weakLessonIds = $lessonWeaknesses->pluck('id')->all();
        $weakAccuracyTrend = collect();

        if ($canAnalyticsFull && !empty($weakLessonIds)) {
            $rawWeakTrend = AttemptAnswer::query()
                ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
                ->where('attempts.user_id', $userId)
                ->whereIn('attempts.lesson_id', $weakLessonIds)
                ->whereBetween(DB::raw('COALESCE(attempts.completed_at, attempts.created_at)'), [$weakStart, $weakEnd])
                ->selectRaw(
                    'DATE(COALESCE(attempts.completed_at, attempts.created_at)) as day, 
                    SUM(CASE WHEN attempt_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_total,
                    COUNT(*) as total'
                )
                ->groupBy('day')
                ->pluck('correct_total', 'day');

            $weakTrendTotals = AttemptAnswer::query()
                ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
                ->where('attempts.user_id', $userId)
                ->whereIn('attempts.lesson_id', $weakLessonIds)
                ->whereBetween(DB::raw('COALESCE(attempts.completed_at, attempts.created_at)'), [$weakStart, $weakEnd])
                ->selectRaw(
                    'DATE(COALESCE(attempts.completed_at, attempts.created_at)) as day, 
                    COUNT(*) as total'
                )
                ->groupBy('day')
                ->pluck('total', 'day');

            $weakAccuracyTrend = collect(range($weakDays - 1, 0))->map(function ($offset) use ($weakTrendTotals, $rawWeakTrend) {
                $date = Carbon::now()->subDays($offset)->toDateString();
                $total = (int) ($weakTrendTotals[$date] ?? 0);
                $correct = (int) ($rawWeakTrend[$date] ?? 0);
                $accuracy = $total > 0 ? round(($correct / $total) * 100) : 0;
                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('M d'),
                    'accuracy' => $accuracy,
                ];
            });
        }

        $questionTypeStats = collect();
        $topicGrowth = collect();

        if ($canAnalyticsFull) {
            $typeStatsRaw = AttemptAnswer::query()
                ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
                ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
                ->join('lessons', 'lessons.id', '=', 'attempts.lesson_id')
                ->where('attempts.user_id', $userId)
                ->where('attempts.mode', 'practice')
                ->select(
                    'lessons.type as module',
                    'questions.type as question_type',
                    DB::raw('SUM(COALESCE(attempt_answers.score, CASE WHEN attempt_answers.is_correct = 1 THEN 1 ELSE 0 END)) as score_sum'),
                    DB::raw('SUM(COALESCE(attempt_answers.max_score, 1)) as max_sum'),
                    DB::raw('COUNT(*) as items')
                )
                ->groupBy('module', 'question_type')
                ->get();

            $questionTypeStats = $typeStatsRaw
                ->groupBy('module')
                ->map(function ($rows) {
                    return $rows->map(function ($row) {
                        $max = (int) $row->max_sum;
                        $score = (int) $row->score_sum;
                        $accuracy = $max > 0 ? round(($score / $max) * 100) : 0;
                        return [
                            'question_type' => $row->question_type ?? 'mcq',
                            'accuracy' => $accuracy,
                            'items' => (int) $row->items,
                        ];
                    })->sortBy('accuracy')->values();
                });

            $recentAttemptsForGrowth = Attempt::with('lesson')
                ->where('user_id', $userId)
                ->whereNotNull('completed_at')
                ->where('mode', 'practice')
                ->orderByDesc('completed_at')
                ->limit(200)
                ->get()
                ->groupBy('lesson_id');

            $topicGrowth = $recentAttemptsForGrowth->map(function ($attempts) {
                $latest = $attempts->first();
                $previous = $attempts->skip(1)->first();
                if (!$latest || !$previous) {
                    return null;
                }
                $latestAcc = $latest->total > 0 ? round(($latest->score / $latest->total) * 100) : 0;
                $prevAcc = $previous->total > 0 ? round(($previous->score / $previous->total) * 100) : 0;
                return [
                    'lesson_id' => $latest->lesson_id,
                    'title' => $latest->lesson?->title ?? '',
                    'module' => $latest->lesson?->type ?? '',
                    'difficulty' => $latest->lesson?->difficulty ?? '',
                    'latest_accuracy' => $latestAcc,
                    'previous_accuracy' => $prevAcc,
                    'delta' => $latestAcc - $prevAcc,
                    'latest_at' => $latest->completed_at,
                ];
            })->filter()->sortByDesc('delta')->take(5)->values();
        }

        return view('dashboard.index', [
            'recentAttempts' => $recentAttempts,
            'lastAttempt' => $lastAttempt,
            'stats' => $stats,
            'grammarStats' => $grammarStats,
            'grammarTopicsTotal' => $grammarTopicsTotal,
            'grammarTopicsPracticed' => $grammarTopicsPracticed,
            'grammarBestScore' => $grammarBestScore,
            'dashboardGrammarTopics' => $dashboardGrammarTopics,
            'dashboardTopicProgress' => $dashboardTopicProgress,
            'recommendedTopicId' => $recommendedTopicId,
            'canWriting' => $canWriting,
            'canSpeaking' => $canSpeaking,
            'vocabTotalWords' => $vocabTotalWords,
            'vocabReviewed' => $vocabReviewed,
            'vocabMastered' => $vocabMastered,
            'vocabDue' => $vocabDue,
            'vocabNew' => $vocabNew,
            'vocabProgressDays' => $vocabProgressDays,
            'weeklyVocabReviews' => $weeklyVocabReviews,
            'grammarProgressDays' => $grammarProgressDays,
            'weeklyGrammarAttempts' => $weeklyGrammarAttempts,
            'lessonWeaknesses' => $lessonWeaknesses,
            'weakDays' => $weakDays,
            'weakAccuracyTrend' => $weakAccuracyTrend,
            'studyPlan' => $studyPlan,
            'studyProgress' => $studyProgress,
            'questionTypeStats' => $questionTypeStats,
            'topicGrowth' => $topicGrowth,
            'canAnalyticsFull' => $canAnalyticsFull,
            'currentPlan' => $currentPlan,
        ]);
    }

    private function topicProgressForTopics($topics, int $userId): array
    {
        if (!$userId || $topics->isEmpty()) {
            return [];
        }

        $topicIds = $topics->pluck('id')->all();
        $attempts = GrammarAttempt::where('user_id', $userId)
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

    public function updateStudyPlan(Request $request)
    {
        $data = $request->validate([
            'lessons_target' => ['required', 'integer', 'min:1', 'max:20'],
            'grammar_target' => ['required', 'integer', 'min:1', 'max:20'],
            'vocab_target' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $weekStart = Carbon::now()->startOfWeek()->toDateString();

        UserStudyPlan::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'week_start_date' => $weekStart,
            ],
            $data
        );

        return back()->with('status', __('app.saved'));
    }
}
