<?php

namespace App\Http\Controllers;

use App\Models\MockAnswer;
use App\Models\MockAttempt;
use App\Models\MockQuestion;
use App\Models\MockTest;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MockTestController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $user = Auth::user();
        $freePreviewTestIds = $this->freePreviewTestIds();
        $tests = MockTest::query()
            ->where('is_active', true)
            ->withCount(['sections', 'attempts'])
            ->orderBy('module')
            ->orderBy('id')
            ->get()
            ->groupBy('module');

        $latestAttempts = MockAttempt::query()
            ->where('user_id', Auth::id())
            ->with('test')
            ->latest('id')
            ->limit(10)
            ->get();

        $moduleAccess = [
            'reading' => $this->canAccessModule($user, $featureGate, 'reading'),
            'listening' => $this->canAccessModule($user, $featureGate, 'listening'),
        ];

        return view('mock.index', [
            'testsByModule' => $tests,
            'latestAttempts' => $latestAttempts,
            'moduleAccess' => $moduleAccess,
            'freePreviewTestIds' => $freePreviewTestIds,
        ]);
    }

    public function start(MockTest $mockTest, FeatureGate $featureGate)
    {
        abort_if(!$mockTest->is_active, 404);

        if (!$this->canAccessTest(Auth::user(), $featureGate, $mockTest)) {
            return redirect()
                ->route('mock.index')
                ->with('status', __('app.upgrade_required'));
        }

        $attempt = MockAttempt::query()
            ->where('user_id', Auth::id())
            ->where('mock_test_id', $mockTest->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if (!$attempt) {
            $attempt = MockAttempt::create([
                'user_id' => Auth::id(),
                'mock_test_id' => $mockTest->id,
                'started_at' => now(),
                'mode' => 'mock',
                'status' => 'in_progress',
            ]);
        }

        return redirect()->route('mock.attempts.show', $attempt);
    }

    public function showAttempt(MockAttempt $attempt, FeatureGate $featureGate)
    {
        $this->authorizeAttempt($attempt);
        $attempt->loadMissing('test');
        if (!$attempt->test || !$this->canAccessTest(Auth::user(), $featureGate, $attempt->test)) {
            return redirect()
                ->route('mock.index')
                ->with('status', __('app.upgrade_required'));
        }
        $attempt->load([
            'test.sections' => fn ($query) => $query->orderBy('section_number'),
            'test.sections.questions' => fn ($query) => $query->orderBy('order_index')->orderBy('id'),
            'answers',
        ]);

        if ($attempt->status === 'completed') {
            return redirect()->route('mock.attempts.result', $attempt);
        }

        $remainingSeconds = $this->remainingSeconds($attempt);
        if ($remainingSeconds <= 0) {
            $this->finalizeAttempt($attempt, []);
            return redirect()->route('mock.attempts.result', $attempt);
        }

        $answers = $attempt->answers->keyBy('mock_question_id');

        return view('mock.attempt', [
            'attempt' => $attempt,
            'test' => $attempt->test,
            'sections' => $attempt->test->sections,
            'answers' => $answers,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    public function submit(Request $request, MockAttempt $attempt, FeatureGate $featureGate)
    {
        $this->authorizeAttempt($attempt);
        $attempt->load('test.sections.questions');
        if (!$attempt->test || !$this->canAccessTest(Auth::user(), $featureGate, $attempt->test)) {
            return redirect()
                ->route('mock.index')
                ->with('status', __('app.upgrade_required'));
        }

        if ($attempt->status === 'completed') {
            return redirect()->route('mock.attempts.result', $attempt);
        }

        $remainingSeconds = $this->remainingSeconds($attempt);
        $answers = $remainingSeconds > 0 ? (array) $request->input('answers', []) : [];

        $this->finalizeAttempt($attempt, $answers);

        return redirect()->route('mock.attempts.result', $attempt);
    }

    public function result(MockAttempt $attempt, FeatureGate $featureGate)
    {
        $this->authorizeAttempt($attempt);
        $attempt->loadMissing('test');
        if (!$attempt->test || !$this->canAccessTest(Auth::user(), $featureGate, $attempt->test)) {
            return redirect()
                ->route('mock.index')
                ->with('status', __('app.upgrade_required'));
        }
        $attempt->load([
            'test.sections' => fn ($query) => $query->orderBy('section_number'),
            'test.sections.questions' => fn ($query) => $query->orderBy('order_index')->orderBy('id'),
            'answers',
        ]);

        if ($attempt->status !== 'completed') {
            return redirect()->route('mock.attempts.show', $attempt);
        }

        $answers = $attempt->answers->keyBy('mock_question_id');

        return view('mock.result', [
            'attempt' => $attempt,
            'test' => $attempt->test,
            'sections' => $attempt->test->sections,
            'answers' => $answers,
        ]);
    }

    private function finalizeAttempt(MockAttempt $attempt, array $inputAnswers): void
    {
        DB::transaction(function () use ($attempt, $inputAnswers): void {
            $attempt->loadMissing('test.sections.questions');

            $questions = $attempt->test->sections
                ->flatMap(fn ($section) => $section->questions)
                ->values();

            foreach ($questions as $question) {
                $rawAnswer = $inputAnswers[$question->id] ?? null;
                $normalized = $this->normalizeUserAnswer($question, $rawAnswer);
                $isCorrect = $normalized !== '' && $this->answersMatch($question, $normalized);

                MockAnswer::updateOrCreate(
                    [
                        'mock_attempt_id' => $attempt->id,
                        'mock_question_id' => $question->id,
                    ],
                    [
                        'user_answer' => $normalized !== '' ? $normalized : null,
                        'is_correct' => $isCorrect,
                    ]
                );
            }

            $rawScore = $attempt->answers()->where('is_correct', true)->count();
            $band = MockAttempt::bandFromRawScore((string) $attempt->test->module, $rawScore);

            $attempt->update([
                'score_raw' => $rawScore,
                'band_score' => $band,
                'status' => 'completed',
                'ended_at' => now(),
            ]);
        });
    }

    private function answersMatch(MockQuestion $question, string $userAnswer): bool
    {
        $correct = trim((string) $question->correct_answer);
        if ($question->question_type === 'mcq') {
            return strtoupper($userAnswer) === strtoupper($correct);
        }

        if ($question->question_type === 'tfng' || $question->question_type === 'ynng') {
            return strtoupper($userAnswer) === strtoupper($correct);
        }

        return mb_strtolower($userAnswer) === mb_strtolower($correct);
    }

    private function normalizeUserAnswer(MockQuestion $question, mixed $value): string
    {
        $answer = trim((string) $value);
        if ($answer === '') {
            return '';
        }

        if ($question->question_type === 'mcq') {
            $answer = strtoupper(substr($answer, 0, 1));
            return in_array($answer, ['A', 'B', 'C', 'D'], true) ? $answer : '';
        }

        if ($question->question_type === 'tfng') {
            $answer = strtoupper($answer);
            $answer = str_replace(['NOT GIVEN', 'NG'], 'NOT_GIVEN', $answer);
            if ($answer === 'T') {
                $answer = 'TRUE';
            } elseif ($answer === 'F') {
                $answer = 'FALSE';
            }
            return in_array($answer, ['TRUE', 'FALSE', 'NOT_GIVEN'], true) ? $answer : '';
        }

        if ($question->question_type === 'ynng') {
            $answer = strtoupper($answer);
            $answer = str_replace(['NOT GIVEN', 'NG'], 'NOT_GIVEN', $answer);
            if ($answer === 'Y') {
                $answer = 'YES';
            } elseif ($answer === 'N') {
                $answer = 'NO';
            }
            return in_array($answer, ['YES', 'NO', 'NOT_GIVEN'], true) ? $answer : '';
        }

        return $answer;
    }

    private function remainingSeconds(MockAttempt $attempt): int
    {
        $startedAt = $attempt->started_at;
        if (!$startedAt) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($startedAt);
        $remaining = (int) $attempt->test->time_limit - $elapsed;

        return max(0, $remaining);
    }

    private function authorizeAttempt(MockAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
    }

    private function canAccessModule($user, FeatureGate $featureGate, string $module): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_admin || $user->is_super_admin) {
            return true;
        }

        if ($featureGate->userCan($user, 'mock_tests')) {
            return true;
        }

        if ($module === 'reading') {
            return $featureGate->userCan($user, 'reading_full');
        }

        if ($module === 'listening') {
            return $featureGate->userCan($user, 'listening_full');
        }

        return false;
    }

    private function canAccessTest($user, FeatureGate $featureGate, MockTest $mockTest): bool
    {
        if ($this->canAccessModule($user, $featureGate, (string) $mockTest->module)) {
            return true;
        }

        $freePreviewTestIds = $this->freePreviewTestIds();

        return (int) ($freePreviewTestIds[$mockTest->module] ?? 0) === (int) $mockTest->id;
    }

    private function freePreviewTestIds(): array
    {
        return MockTest::query()
            ->where('is_active', true)
            ->whereIn('module', ['reading', 'listening'])
            ->orderBy('id')
            ->get(['id', 'module'])
            ->groupBy('module')
            ->map(fn ($tests) => (int) $tests->first()->id)
            ->all();
    }
}
