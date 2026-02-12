<?php

namespace App\Http\Controllers;

use App\Models\GrammarAttempt;
use App\Models\GrammarTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GrammarAttemptController extends Controller
{
    public function store(Request $request, GrammarTopic $topic)
    {
        $topic->load([
            'exercises' => fn ($query) => $this->applyPracticeExerciseFilter($query)->orderBy('sort_order')->orderBy('id'),
        ]);

        if ($topic->exercises->isEmpty()) {
            return redirect()
                ->route('grammar.show', $topic)
                ->withErrors(['answers' => __('app.no_exercises')]);
        }

        $validator = Validator::make($request->all(), [
            'answers' => ['required', 'array'],
        ], [
            'answers.required' => __('app.complete_all_questions'),
            'answers.array' => __('app.complete_all_questions'),
            'answers.*.required' => __('app.complete_all_questions'),
        ]);

        $validator->after(function ($validator) use ($topic, $request) {
            if ($topic->exercises->isEmpty()) {
                return;
            }

            $answers = $request->input('answers', []);
            $exerciseIds = $topic->exercises->pluck('id')->map(fn ($id) => (string) $id);
            $answerKeys = collect(array_keys($answers))->map(fn ($id) => (string) $id);

            if ($exerciseIds->diff($answerKeys)->isNotEmpty()) {
                $validator->errors()->add('answers', __('app.complete_all_questions'));
                return;
            }

            $exerciseLookup = $topic->exercises->keyBy('id');

            foreach ($exerciseIds as $exerciseId) {
                $exercise = $exerciseLookup->get((int) $exerciseId);
                $value = $answers[$exerciseId] ?? null;
                if (!$exercise || !$this->isAnswerValid($exercise, $value)) {
                    $validator->errors()->add('answers', __('app.complete_all_questions'));
                    break;
                }
            }
        });

        $data = $validator->validate();

        $answers = $data['answers'] ?? [];

        $attempt = DB::transaction(function () use ($topic, $answers) {
            $attempt = GrammarAttempt::create([
                'user_id' => Auth::id(),
                'grammar_topic_id' => $topic->id,
                'total' => $topic->exercises->count(),
                'completed_at' => now(),
            ]);

            $score = 0;

            foreach ($topic->exercises as $exercise) {
                $selected = $this->normalizeSubmittedAnswer($exercise, $answers[$exercise->id] ?? null);
                $isCorrect = $this->isCorrectAnswer($exercise, $selected);
                $score += $isCorrect ? 1 : 0;

                $attempt->answers()->create([
                    'grammar_exercise_id' => $exercise->id,
                    'selected_answer' => $selected,
                    'is_correct' => $isCorrect,
                ]);
            }

            $attempt->update(['score' => $score]);

            return $attempt;
        });

        return redirect()->route('grammar.attempts.show', $attempt);
    }

    public function show(GrammarAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $attempt->load('topic', 'answers.exercise');

        $nextTopic = null;
        if (Auth::check()) {
            $completedTopicIds = GrammarAttempt::where('user_id', Auth::id())
                ->distinct()
                ->pluck('grammar_topic_id')
                ->all();

            $nextTopic = GrammarTopic::whereNotIn('id', $completedTopicIds)
                ->orderBy('id')
                ->first();
        }

        return view('grammar.attempt', [
            'attempt' => $attempt,
            'nextTopic' => $nextTopic,
        ]);
    }

    public function answersStatus(GrammarAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $answers = $attempt->answers()
            ->select('id', 'ai_explanation', 'is_correct')
            ->get();

        return response()->json([
            'answers' => $answers,
        ]);
    }

    private function authorizeAttempt(GrammarAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
    }

    private function applyPracticeExerciseFilter($query)
    {
        $allowedTypes = ['mcq', 'gap', 'tf', 'reorder'];

        return $query->where(function ($builder) use ($allowedTypes) {
            $builder->whereNull('exercise_type')
                ->orWhereIn('exercise_type', $allowedTypes)
                ->orWhereIn('type', $allowedTypes);
        })->whereHas('rule', function ($builder) {
            $builder->whereNull('rule_key')->orWhere('rule_key', '!=', '__unmapped__');
        });
    }

    private function isAnswerValid($exercise, $value): bool
    {
        $type = $this->resolveExerciseType($exercise);
        $normalized = $this->normalizeSubmittedAnswer($exercise, $value);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        if ($type === 'mcq') {
            return in_array($normalized, ['A', 'B', 'C', 'D'], true);
        }

        if ($type === 'tf') {
            return in_array($normalized, ['true', 'false'], true);
        }

        return true;
    }

    private function isCorrectAnswer($exercise, ?string $selected): bool
    {
        if ($selected === null || $selected === '') {
            return false;
        }

        $type = $this->resolveExerciseType($exercise);
        $correct = (string) ($exercise->correct_answer ?? '');

        if ($type === 'mcq') {
            return strtoupper($selected) === strtoupper($correct);
        }

        if ($type === 'tf') {
            return strtolower($selected) === strtolower($correct);
        }

        if ($type === 'gap') {
            $expected = $this->normalizeGapCorrectAnswers($correct);
            $normalized = $this->normalizeTextAnswer($selected);
            return $normalized !== '' && in_array($normalized, $expected, true);
        }

        if ($type === 'reorder') {
            $expected = $this->normalizeReorderCorrectAnswer($correct);
            return $expected !== '' && $this->normalizeTextAnswer($selected) === $expected;
        }

        return false;
    }

    private function normalizeSubmittedAnswer($exercise, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $type = $this->resolveExerciseType($exercise);
        $raw = trim((string) $value);

        if ($type === 'mcq') {
            if (is_numeric($raw)) {
                $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
                return $map[$raw] ?? strtoupper(substr($raw, 0, 1));
            }

            return strtoupper(substr($raw, 0, 1));
        }

        if ($type === 'tf') {
            return strtolower($raw);
        }

        return $raw;
    }

    private function resolveExerciseType($exercise): string
    {
        $type = strtolower((string) ($exercise->exercise_type ?? $exercise->type ?? 'mcq'));
        return $type !== '' ? $type : 'mcq';
    }

    private function normalizeTextAnswer(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        return mb_strtolower($value);
    }

    private function normalizeGapCorrectAnswers(string $correctRaw): array
    {
        $parts = preg_split('/\s*\|\s*/u', trim($correctRaw));
        $parts = $parts ?: [$correctRaw];

        $normalized = array_filter(array_map(function ($value) {
            $value = $this->normalizeTextAnswer($value);
            return $value !== '' ? $value : null;
        }, $parts));

        return array_values(array_unique($normalized));
    }

    private function normalizeReorderCorrectAnswer(string $correctRaw): string
    {
        $value = trim($correctRaw);
        if (str_contains($value, '|')) {
            $tokens = array_map('trim', explode('|', $value));
            $value = implode(' ', array_filter($tokens, static fn ($token) => $token !== ''));
        }

        return $this->normalizeTextAnswer($value);
    }
}
