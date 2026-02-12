<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonQuestions;
use App\Models\Lesson;
use App\Models\MockQuestion;
use App\Models\SpeakingPrompt;
use App\Models\WritingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index()
    {
        $query = Lesson::withCount('questions')->latest();
        $type = request('type');
        if (in_array($type, ['reading', 'listening'], true)) {
            $query->where('type', $type);
        }
        $lessons = $query->paginate(20)->withQueryString();

        return view('admin.lessons.index', [
            'lessons' => $lessons,
            'type' => $type,
        ]);
    }

    public function mockQuestions()
    {
        $writingMockCount = Schema::hasColumn('writing_tasks', 'mode')
            ? WritingTask::where('mode', 'mock')->count()
            : WritingTask::count();

        $speakingMockCount = Schema::hasColumn('speaking_prompts', 'mode')
            ? SpeakingPrompt::where('mode', 'mock')->count()
            : SpeakingPrompt::count();

        return view('admin.mock-questions.index', [
            'readingMockCount' => MockQuestion::whereHas('section.test', fn ($query) => $query->where('module', 'reading'))
                ->count(),
            'listeningMockCount' => MockQuestion::whereHas('section.test', fn ($query) => $query->where('module', 'listening'))
                ->count(),
            'writingMockCount' => $writingMockCount,
            'speakingMockCount' => $speakingMockCount,
        ]);
    }

    public function create()
    {
        return view('admin.lessons.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateLesson($request);

        $lesson = Lesson::create(array_merge($data, [
            'created_by' => $request->user()->id,
        ]));

        if (!empty($lesson->content_text)) {
            GenerateLessonQuestions::dispatch($lesson->id);
        }

        return redirect()->route('admin.lessons.edit', $lesson);
    }

    public function edit(Lesson $lesson)
    {
        $lesson->load('questions');

        return view('admin.lessons.edit', compact('lesson'));
    }

    public function update(Request $request, Lesson $lesson)
    {
        $data = $this->validateLesson($request);
        $lesson->update($data);

        if ($request->boolean('regenerate_questions') && !empty($lesson->content_text)) {
            GenerateLessonQuestions::dispatch($lesson->id);
        }

        return redirect()->route('admin.lessons.edit', $lesson);
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()
            ->route('admin.lessons.index')
            ->with('status', __('app.lesson_deleted'));
    }

    private function validateLesson(Request $request): array
    {
        $type = $request->input('type');

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:reading,listening'],
            'content_text' => ['nullable', 'string'],
            'audio_url' => [
                Rule::requiredIf($type === 'listening'),
                Rule::when($type === 'listening', ['url']),
            ],
            'difficulty' => ['required', 'in:A1,A2,B1,B2'],
        ]);
    }
}
