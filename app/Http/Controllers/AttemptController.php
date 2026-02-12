<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttemptController extends Controller
{
    public function store(Request $request, Lesson $lesson, FeatureGate $featureGate)
    {
        $user = $request->user();
        $featureKey = $lesson->requiredFeatureKey();
        if ($user && !$user->is_admin && $featureKey) {
            if (!$featureGate->userCan($user, $featureKey)) {
                return redirect()
                    ->route('lessons.show', $lesson)
                    ->with('status', __('app.upgrade_required'));
            }
        }

        $questions = $lesson->questions()->where('mode', 'practice')->get();
        $lesson->setRelation('questions', $questions);
        if ($questions->isEmpty()) {
            return redirect()
                ->route('lessons.show', $lesson)
                ->with('status', __('app.questions_pending'));
        }

        $validator = Validator::make($request->all(), [
            'answers' => ['required', 'array'],
        ], [
            'answers.required' => __('app.complete_all_questions'),
            'answers.array' => __('app.complete_all_questions'),
        ]);

        $validator->after(function ($validator) use ($lesson, $request) {
            if ($lesson->questions->isEmpty()) {
                return;
            }

            $answers = $request->input('answers', []);
            $questionIds = $lesson->questions->pluck('id')->map(fn ($id) => (string) $id);
            $answerKeys = collect(array_keys($answers))->map(fn ($id) => (string) $id);

            if ($questionIds->diff($answerKeys)->isNotEmpty()) {
                $validator->errors()->add('answers', __('app.complete_all_questions'));
                return;
            }

            foreach ($lesson->questions as $question) {
                $value = $answers[$question->id] ?? null;
                if (!$this->isAnswerValid($question, $value)) {
                    $validator->errors()->add('answers', __('app.complete_all_questions'));
                    break;
                }
            }
        });

        $data = $validator->validate();

        $answers = $data['answers'] ?? [];

        $total = $questions->sum(fn (Question $question) => $this->questionMaxScore($question));

        $attempt = DB::transaction(function () use ($lesson, $answers, $questions, $total) {
            $attempt = Attempt::create([
                'user_id' => Auth::id(),
                'lesson_id' => $lesson->id,
                'total' => $total,
                'status' => 'completed',
                'mode' => 'practice',
                'completed_at' => now(),
            ]);

            $score = 0;

            foreach ($questions as $question) {
                $selected = $answers[$question->id] ?? null;
                $evaluation = $this->evaluateAnswer($question, $selected);
                $score += $evaluation['score'];
                $aiExplanation = $evaluation['is_correct'] ? null : $question->ai_explanation;

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'selected_answer' => $evaluation['stored'],
                    'is_correct' => $evaluation['is_correct'],
                    'score' => $evaluation['score'],
                    'max_score' => $evaluation['max_score'],
                    'ai_explanation' => $aiExplanation,
                ]);
            }

            $attempt->update([
                'score' => $score,
            ]);

            return $attempt;
        });

        $allowAutoExplanation = false;

        return redirect()->route('attempts.show', $attempt);
    }

    public function show(Attempt $attempt, FeatureGate $featureGate)
    {
        $this->authorizeAttempt($attempt);
        $attempt->load('lesson', 'answers.question');

        $allowAutoExplanation = false;
        $user = Auth::user();
        if ($user) {
            $plan = $featureGate->currentPlan($user);
            $allowAutoExplanation = $user->is_admin || $plan?->slug === 'pro';
        }

        $nextLesson = null;
        if (Auth::check()) {
            $completedLessonIds = Attempt::where('user_id', Auth::id())
                ->where('status', 'completed')
                ->pluck('lesson_id')
                ->all();

            $nextLesson = Lesson::where('type', $attempt->lesson->type)
                ->whereNotIn('id', $completedLessonIds)
                ->orderBy('id')
                ->first();
        }

        $listeningMockRawScore = $attempt->listeningMockRawScore();
        $listeningMockBandScore = $attempt->listeningMockBandScore();

        return view('attempts.show', [
            'attempt' => $attempt,
            'nextLesson' => $nextLesson,
            'allowAutoExplanation' => $allowAutoExplanation,
            'listeningMockRawScore' => $listeningMockRawScore,
            'listeningMockBandScore' => $listeningMockBandScore,
        ]);
    }

    public function answersStatus(Attempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        $answers = $attempt->answers()
            ->select('id', 'ai_explanation', 'is_correct')
            ->get();

        return response()->json([
            'answers' => $answers,
        ]);
    }

    private function authorizeAttempt(Attempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
    }

    private function questionMaxScore(Question $question): int
    {
        if (($question->type ?? 'mcq') === 'matching') {
            $items = (array) data_get($question->meta, 'items', []);
            return max(1, count($items));
        }
        return 1;
    }

    private function isAnswerValid(Question $question, $value): bool
    {
        $type = $question->type ?? 'mcq';
        if ($type === 'matching') {
            $items = (array) data_get($question->meta, 'items', []);
            if (!is_array($value) || empty($items)) {
                return false;
            }
            $optionLetters = $this->optionLetters($question);
            foreach (range(1, count($items)) as $index) {
                $selected = $value[$index] ?? null;
                if (!is_string($selected) || !in_array(strtoupper($selected), $optionLetters, true)) {
                    return false;
                }
            }
            return true;
        }

        if ($type === 'completion') {
            return is_string($value) && trim($value) !== '';
        }

        $normalized = $this->normalizeAnswer($question, $value);
        return $normalized !== null;
    }

    private function evaluateAnswer(Question $question, $value): array
    {
        $type = $question->type ?? 'mcq';
        $max = $this->questionMaxScore($question);

        if ($type === 'matching') {
            $items = (array) data_get($question->meta, 'items', []);
            $correctMap = $this->parseMatchingMap((string) $question->correct_answer);
            $selectedMap = $this->normalizeMatchingSelection($question, $value);

            $score = 0;
            foreach (range(1, count($items)) as $index) {
                $correct = $correctMap[$index] ?? null;
                $selected = $selectedMap[$index] ?? null;
                if ($correct && $selected && $correct === $selected) {
                    $score += 1;
                }
            }
            $isCorrect = $score === $max;
            return [
                'score' => $score,
                'is_correct' => $isCorrect,
                'stored' => json_encode($selectedMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'max_score' => $max,
            ];
        }

        if ($type === 'completion') {
            $selected = is_string($value) ? trim($value) : '';
            $correctValues = $this->parseCompletionAnswers((string) $question->correct_answer);
            $isCorrect = $selected !== '' && in_array(mb_strtolower($selected), $correctValues, true);
            return [
                'score' => $isCorrect ? 1 : 0,
                'is_correct' => $isCorrect,
                'stored' => $selected,
                'max_score' => $max,
            ];
        }

        $normalized = $this->normalizeAnswer($question, $value);
        $correct = $this->normalizeAnswer($question, $question->correct_answer);
        $isCorrect = $normalized !== null && $correct !== null && $normalized === $correct;

        return [
            'score' => $isCorrect ? 1 : 0,
            'is_correct' => $isCorrect,
            'stored' => $normalized ?? (string) $value,
            'max_score' => $max,
        ];
    }

    private function normalizeAnswer(Question $question, $value): ?string
    {
        $type = $question->type ?? 'mcq';
        if (!is_string($value)) {
            return null;
        }
        $raw = strtoupper(trim($value));
        if ($raw === '') {
            return null;
        }

        if ($type === 'tfng') {
            if (in_array($raw, ['TRUE', 'T'], true)) {
                return 'TRUE';
            }
            if (in_array($raw, ['FALSE', 'F'], true)) {
                return 'FALSE';
            }
            if (in_array($raw, ['NOT GIVEN', 'NOT_GIVEN', 'NG', 'N'], true)) {
                return 'NOT GIVEN';
            }
            return null;
        }

        if (is_numeric($raw)) {
            $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
            $raw = $map[$raw] ?? $raw;
        } else {
            $raw = strtoupper(substr($raw, 0, 1));
        }

        return in_array($raw, ['A', 'B', 'C', 'D'], true) ? $raw : null;
    }

    private function optionLetters(Question $question): array
    {
        $options = is_array($question->options) ? $question->options : [];
        $count = max(1, count($options));
        $letters = [];
        for ($i = 0; $i < $count; $i += 1) {
            $letters[] = chr(65 + $i);
        }
        return $letters;
    }

    private function normalizeMatchingSelection(Question $question, $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $letters = $this->optionLetters($question);
        $selection = [];
        foreach ($value as $index => $selected) {
            $key = (int) $index;
            if ($key <= 0) {
                continue;
            }
            $letter = strtoupper(trim((string) $selected));
            if (in_array($letter, $letters, true)) {
                $selection[$key] = $letter;
            }
        }
        return $selection;
    }

    private function parseMatchingMap(string $value): array
    {
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

        $clean = [];
        foreach ($map as $index => $letter) {
            $key = (int) $index;
            if ($key > 0) {
                $clean[$key] = strtoupper((string) $letter);
            }
        }
        return $clean;
    }

    private function parseCompletionAnswers(string $value): array
    {
        $parts = preg_split('/\\s*\\|\\s*/', $value);
        $parts = array_values(array_filter(array_map('trim', (array) $parts), fn ($part) => $part !== ''));
        return array_map('mb_strtolower', $parts);
    }
}
