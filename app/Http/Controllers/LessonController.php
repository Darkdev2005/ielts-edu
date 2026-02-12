<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\AiRequest;
use App\Models\Lesson;
use App\Models\QuestionAIHelp;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $type = request('type');

        $readingQuery = Lesson::withCount([
            'questions as questions_count' => fn ($query) => $query->where('mode', 'practice'),
        ])
            ->where('type', 'reading');

        $listeningQuery = Lesson::withCount([
            'questions as questions_count' => fn ($query) => $query->where('mode', 'practice'),
        ])
            ->where('type', 'listening');

        if ($type === 'reading') {
            $listeningQuery->whereRaw('1 = 0');
        } elseif ($type === 'listening') {
            $readingQuery->whereRaw('1 = 0');
        }

        $readingLessons = $readingQuery
            ->orderBy('id')
            ->paginate(12, ['*'], 'reading_page');

        $listeningLessons = $listeningQuery
            ->orderBy('id')
            ->paginate(12, ['*'], 'listening_page');

        $progress = $this->lessonProgressData();

        $user = Auth::user();
        $canReadingFull = false;
        $canListeningFull = false;
        $canReadingPro = false;
        $canListeningPro = false;
        $canWriting = false;
        $canSpeaking = false;
        if ($user) {
            $canReadingFull = $user->is_admin || $featureGate->userCan($user, 'reading_full');
            $canListeningFull = $user->is_admin || $featureGate->userCan($user, 'listening_full');
            $canReadingPro = $user->is_admin || $featureGate->userCan($user, 'reading_pro');
            $canListeningPro = $user->is_admin || $featureGate->userCan($user, 'listening_pro');
            $canWriting = $user->is_admin || $featureGate->userCan($user, 'writing_ai');
            $canSpeaking = $user->is_admin || $featureGate->userCan($user, 'speaking_ai');
        }

        return view('lessons.index', [
            'readingLessons' => $readingLessons,
            'listeningLessons' => $listeningLessons,
            'recommendedLessonIds' => $progress['recommendedLessonIds'],
            'canReadingFull' => $canReadingFull,
            'canListeningFull' => $canListeningFull,
            'canReadingPro' => $canReadingPro,
            'canListeningPro' => $canListeningPro,
            'canWriting' => $canWriting,
            'canSpeaking' => $canSpeaking,
            'activeType' => $type,
        ]);
    }

    public function mockIndex(FeatureGate $featureGate)
    {
        return redirect()->route('mock.index');
    }

    public function show(Lesson $lesson, FeatureGate $featureGate)
    {
        return $this->renderLesson($lesson, $featureGate);
    }

    public function mock(Lesson $lesson, FeatureGate $featureGate)
    {
        return redirect()
            ->route('mock.index')
            ->with('status', __('app.mock_test').' → '.__('app.mock_tests'));
    }

    private function lessonProgressData(): array
    {
        if (!Auth::check()) {
            return [
                'completedLessonIds' => [],
                'recommendedLessonIds' => [],
            ];
        }

        $orderedLessons = Lesson::orderBy('id')->get(['id', 'type']);
        if ($orderedLessons->isEmpty()) {
            return [
                'completedLessonIds' => [],
                'recommendedLessonIds' => [],
            ];
        }

        $completedLessonIds = Attempt::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->all();

        $completedLookup = array_flip($completedLessonIds);
        $nextByType = [];
        $recommendedLessonIds = [];

        foreach ($orderedLessons as $lesson) {
            if (isset($completedLookup[$lesson->id])) {
                continue;
            }

            if (!isset($nextByType[$lesson->type])) {
                $nextByType[$lesson->type] = $lesson->id;
                $recommendedLessonIds[] = $lesson->id;
                continue;
            }
        }

        return [
            'completedLessonIds' => $completedLessonIds,
            'recommendedLessonIds' => $recommendedLessonIds,
        ];
    }

    private function renderLesson(Lesson $lesson, FeatureGate $featureGate)
    {
        $progress = $this->lessonProgressData();
        $completedLessonIds = $progress['completedLessonIds'];
        $recommendedLessonIds = $progress['recommendedLessonIds'];

        if (Auth::check()
            && !in_array($lesson->id, $completedLessonIds, true)
            && !in_array($lesson->id, $recommendedLessonIds, true)
            && !empty($recommendedLessonIds)
        ) {
            return redirect()
                ->route('lessons.index')
                ->with('status', __('app.complete_previous_lesson_first'));
        }

        $user = Auth::user();
        $featureKey = $lesson->requiredFeatureKey();
        if ($user && !$user->is_admin && $featureKey) {
            if (!$featureGate->userCan($user, $featureKey)) {
                return view('lessons.locked', [
                    'lesson' => $lesson,
                    'featureLabel' => __('app.upgrade_required'),
                ]);
            }
        }

        $questions = $lesson->questions()->where('mode', 'practice')->get();
        $lesson->setRelation('questions', $questions);

        $helpByQuestion = collect();
        if (auth()->check() && $lesson->questions->isNotEmpty()) {
            $helps = QuestionAIHelp::where('user_id', auth()->id())
                ->whereIn('question_id', $lesson->questions->pluck('id'))
                ->orderByDesc('id')
                ->get();
            $this->syncAiHelpsCollection($helps);
            $helpByQuestion = $helps->unique('question_id')->keyBy('question_id');
        }

        return view('lessons.show', [
            'lesson' => $lesson,
            'helpByQuestion' => $helpByQuestion,
        ]);
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
