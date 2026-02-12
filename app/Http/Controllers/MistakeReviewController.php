<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\AttemptAnswer;
use Illuminate\Http\Request;

class MistakeReviewController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $lessonId = $request->input('lesson');

        $answersQuery = AttemptAnswer::with(['question', 'attempt.lesson'])
            ->whereHas('attempt', fn ($q) => $q->where('user_id', $userId))
            ->where('is_correct', false);

        if ($lessonId) {
            $answersQuery->whereHas('attempt.lesson', fn ($q) => $q->where('id', $lessonId));
        }

        $answers = $answersQuery
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        $lessons = Lesson::whereHas('attempts', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('title')
            ->get(['id', 'title', 'type']);

        return view('mistakes.index', compact('answers', 'lessons', 'lessonId'));
    }
}
