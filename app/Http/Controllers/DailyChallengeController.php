<?php

namespace App\Http\Controllers;

use App\Models\DailyChallenge;
use App\Models\DailyChallengeAnswer;
use App\Models\AiRequest;
use App\Models\GrammarExerciseAIHelp;
use App\Models\GrammarExercise;
use App\Models\Question;
use App\Models\QuestionAIHelp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyChallengeController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->user()->id;
        $today = Carbon::now()->toDateString();
        $practiceMode = $request->boolean('practice');

        $challenge = DailyChallenge::firstOrCreate(
            [
                'user_id' => $userId,
                'challenge_date' => $today,
            ],
            [
                'question_ids' => [],
                'grammar_exercise_id' => null,
                'score' => 0,
                'total' => 0,
            ]
        );

        if (empty($challenge->question_ids)) {
            $questionIds = Question::inRandomOrder()->limit(5)->pluck('id')->all();
            $grammarExerciseId = GrammarExercise::where(function ($query) {
                $query->whereNull('exercise_type')
                    ->orWhere('exercise_type', 'mcq')
                    ->orWhere('type', 'mcq');
            })->whereHas('rule', function ($query) {
                $query->whereNull('rule_key')->orWhere('rule_key', '!=', '__unmapped__');
            })->inRandomOrder()->value('id');

            $challenge->update([
                'question_ids' => $questionIds,
                'grammar_exercise_id' => $grammarExerciseId,
                'total' => count($questionIds) + ($grammarExerciseId ? 1 : 0),
            ]);
        }

        $challengeAnswers = $challenge->answers()
            ->with(['question', 'grammarExercise'])
            ->get();
        $mistakeCount = $challengeAnswers
            ->whereNotNull('question_id')
            ->where('is_correct', false)
            ->count();

        $practiceScore = null;
        $practiceTotal = null;
        $practiceCompletedAt = null;

        $questionIds = $challenge->question_ids ?? [];
        $questions = Question::whereIn('id', $questionIds)
            ->get()
            ->sortBy(fn ($q) => array_search($q->id, $questionIds))
            ->values();
        $grammarExercise = $challenge->grammarExercise;
        $answers = $challengeAnswers;

        if ($practiceMode) {
            $practiceData = $request->session()->get('daily_practice');
            if (is_array($practiceData) && !empty($practiceData['question_ids'])) {
                $practiceQuestionIds = array_values(array_unique($practiceData['question_ids']));
                $questions = Question::whereIn('id', $practiceQuestionIds)
                    ->get()
                    ->sortBy(fn ($q) => array_search($q->id, $practiceQuestionIds))
                    ->values();
                $practiceGrammarId = $practiceData['grammar_exercise_id'] ?? null;
                $grammarExercise = $practiceGrammarId ? GrammarExercise::find($practiceGrammarId) : null;

                $practiceResults = $request->session()->get('daily_practice_results', []);
                $practiceScore = $practiceResults['score'] ?? null;
                $practiceTotal = $practiceResults['total'] ?? (count($practiceQuestionIds) + ($grammarExercise ? 1 : 0));
                $practiceCompletedAt = $practiceResults['completed_at'] ?? null;

                $answers = collect($practiceResults['answers'] ?? [])
                    ->map(fn ($row) => (object) $row);
            } else {
                $practiceMode = false;
            }
        }

        $helpByQuestion = collect();
        if ($questions->isNotEmpty()) {
            $helps = QuestionAIHelp::where('user_id', $userId)
                ->whereIn('question_id', $questions->pluck('id'))
                ->orderByDesc('id')
                ->get();
            $this->syncAiHelpsCollection($helps);
            $helpByQuestion = $helps->unique('question_id')->keyBy('question_id');
        }

        $helpByGrammarExercise = collect();
        if ($grammarExercise) {
            $helps = GrammarExerciseAIHelp::where('user_id', $userId)
                ->where('grammar_exercise_id', $grammarExercise->id)
                ->orderByDesc('id')
                ->get();
            $this->syncAiHelpsCollection($helps);
            $helpByGrammarExercise = $helps->unique('grammar_exercise_id')->keyBy('grammar_exercise_id');
        }

        $streak = $this->calculateStreak($userId);

        $todayLeaderboard = DailyChallenge::with('user')
            ->whereDate('challenge_date', $today)
            ->whereNotNull('completed_at')
            ->orderByDesc('score')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $streakLeaders = $this->buildStreakLeaderboard();

        return view('daily-challenge.show', [
            'challenge' => $challenge,
            'questions' => $questions,
            'grammarExercise' => $grammarExercise,
            'answers' => $answers,
            'streak' => $streak,
            'todayLeaderboard' => $todayLeaderboard,
            'streakLeaders' => $streakLeaders,
            'helpByQuestion' => $helpByQuestion,
            'helpByGrammarExercise' => $helpByGrammarExercise,
            'practiceMode' => $practiceMode,
            'practiceScore' => $practiceScore,
            'practiceTotal' => $practiceTotal,
            'practiceCompletedAt' => $practiceCompletedAt,
            'mistakeCount' => $mistakeCount,
        ]);
    }

    public function submit(Request $request, DailyChallenge $challenge)
    {
        if ($challenge->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($challenge->completed_at) {
            return redirect()->route('daily-challenge.show')->with('status', __('app.already_completed'));
        }

        $data = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'in:A,B,C,D'],
            'grammar_answer' => ['nullable', 'in:A,B,C,D'],
        ]);

        $answers = $data['answers'] ?? [];
        $grammarAnswer = $data['grammar_answer'] ?? null;

        DB::transaction(function () use ($challenge, $answers, $grammarAnswer) {
            $score = 0;

            $challenge->answers()->delete();

            foreach ($challenge->question_ids ?? [] as $questionId) {
                $question = Question::find($questionId);
                if (!$question) {
                    continue;
                }

                $selected = $answers[$questionId] ?? null;
                $isCorrect = $selected !== null && $selected === $question->correct_answer;
                $score += $isCorrect ? 1 : 0;

                DailyChallengeAnswer::create([
                    'daily_challenge_id' => $challenge->id,
                    'question_id' => $questionId,
                    'selected_answer' => $selected,
                    'is_correct' => $isCorrect,
                ]);
            }

            if ($challenge->grammar_exercise_id) {
                $exercise = GrammarExercise::find($challenge->grammar_exercise_id);
                if ($exercise) {
                    $isCorrect = $grammarAnswer !== null && $grammarAnswer === $exercise->correct_answer;
                    $score += $isCorrect ? 1 : 0;

                    DailyChallengeAnswer::create([
                        'daily_challenge_id' => $challenge->id,
                        'grammar_exercise_id' => $exercise->id,
                        'selected_answer' => $grammarAnswer,
                        'is_correct' => $isCorrect,
                    ]);
                }
            }

            $challenge->update([
                'score' => $score,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('daily-challenge.show')->with('status', __('app.daily_challenge_completed'));
    }

    public function practiceMistakes(Request $request)
    {
        $userId = $request->user()->id;
        $today = Carbon::now()->toDateString();

        $challenge = DailyChallenge::where('user_id', $userId)
            ->whereDate('challenge_date', $today)
            ->first();

        if (!$challenge || !$challenge->completed_at) {
            return back()->with('status', __('app.practice_requires_completion'));
        }

        $wrongIds = DailyChallengeAnswer::where('daily_challenge_id', $challenge->id)
            ->whereNotNull('question_id')
            ->where('is_correct', false)
            ->pluck('question_id')
            ->unique()
            ->values();

        $wrongGrammarId = DailyChallengeAnswer::where('daily_challenge_id', $challenge->id)
            ->whereNotNull('grammar_exercise_id')
            ->where('is_correct', false)
            ->value('grammar_exercise_id');

        if ($wrongIds->isEmpty()) {
            return back()->with('status', __('app.practice_no_mistakes'));
        }

        $extraCount = rand(2, 3);
        $extraIds = Question::whereNotIn('id', $wrongIds)
            ->inRandomOrder()
            ->limit($extraCount)
            ->pluck('id');

        $finalIds = $wrongIds->merge($extraIds)->unique()->values()->all();

        $request->session()->put('daily_practice', [
            'question_ids' => $finalIds,
            'grammar_exercise_id' => $wrongGrammarId,
            'base_challenge_id' => $challenge->id,
            'created_at' => now()->toDateTimeString(),
        ]);
        $request->session()->forget('daily_practice_results');

        return redirect()->route('daily-challenge.show', ['practice' => 1])
            ->with('status', __('app.practice_ready'));
    }

    public function submitPractice(Request $request)
    {
        $practiceData = $request->session()->get('daily_practice');
        if (!is_array($practiceData) || empty($practiceData['question_ids'])) {
            return redirect()->route('daily-challenge.show')->with('status', __('app.practice_not_ready'));
        }

        $data = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'in:A,B,C,D'],
            'grammar_answer' => ['nullable', 'in:A,B,C,D'],
        ]);

        $answers = $data['answers'] ?? [];
        $grammarAnswer = $data['grammar_answer'] ?? null;
        $questionIds = array_values(array_unique($practiceData['question_ids']));
        $score = 0;
        $resultRows = [];

        foreach ($questionIds as $questionId) {
            $question = Question::find($questionId);
            if (!$question) {
                continue;
            }

            $selected = $answers[$questionId] ?? null;
            $isCorrect = $selected !== null && $selected === $question->correct_answer;
            $score += $isCorrect ? 1 : 0;

            $resultRows[] = [
                'question_id' => $questionId,
                'selected_answer' => $selected,
                'is_correct' => $isCorrect,
            ];
        }

        $practiceGrammarId = $practiceData['grammar_exercise_id'] ?? null;
        if ($practiceGrammarId) {
            $exercise = GrammarExercise::find($practiceGrammarId);
            if ($exercise) {
                $isCorrect = $grammarAnswer !== null && $grammarAnswer === $exercise->correct_answer;
                $score += $isCorrect ? 1 : 0;
                $resultRows[] = [
                    'grammar_exercise_id' => $exercise->id,
                    'selected_answer' => $grammarAnswer,
                    'is_correct' => $isCorrect,
                ];
            }
        }

        $request->session()->put('daily_practice_results', [
            'score' => $score,
            'total' => count($questionIds) + ($practiceGrammarId ? 1 : 0),
            'completed_at' => now()->toDateTimeString(),
            'answers' => $resultRows,
        ]);

        return redirect()->route('daily-challenge.show', ['practice' => 1])
            ->with('status', __('app.practice_completed'));
    }

    public function reset(Request $request)
    {
        $user = $request->user();
        if (!$user->is_admin) {
            abort(403);
        }

        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $date = $data['date'] ?? Carbon::now()->toDateString();
        $targetUserId = $data['user_id'] ?? $user->id;

        DailyChallenge::where('user_id', $targetUserId)
            ->whereDate('challenge_date', $date)
            ->delete();

        return back()->with('status', __('app.daily_challenge_reset'));
    }

    private function calculateStreak(int $userId): int
    {
        $completed = DailyChallenge::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->orderByDesc('challenge_date')
            ->pluck('challenge_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->values();

        if ($completed->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $current = Carbon::now()->toDateString();

        foreach ($completed as $date) {
            if ($date !== $current) {
                break;
            }
            $streak += 1;
            $current = Carbon::parse($current)->subDay()->toDateString();
        }

        return $streak;
    }

    private function buildStreakLeaderboard()
    {
        $userIds = DailyChallenge::whereNotNull('completed_at')
            ->distinct('user_id')
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return collect();
        }

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $userIds->map(function ($id) use ($users) {
            $user = $users->get($id);
            if (!$user) {
                return null;
            }
            return [
                'user' => $user,
                'streak' => $this->calculateStreak($id),
            ];
        })
            ->filter()
            ->sortByDesc('streak')
            ->take(10)
            ->values();
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
