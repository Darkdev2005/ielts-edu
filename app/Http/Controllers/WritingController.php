<?php

namespace App\Http\Controllers;

use App\Models\WritingSubmission;
use App\Models\WritingTask;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WritingController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $user = Auth::user();
        $canWriting = $user && ($user->is_admin || $featureGate->userCan($user, 'writing_ai'));
        $freePreviewIds = WritingTask::freePreviewIds('practice');

        $tasks = WritingTask::where('is_active', true)
            ->where('mode', 'practice')
            ->orderByDesc('id')
            ->paginate(12);

        return view('writing.index', [
            'tasks' => $tasks,
            'canWriting' => $canWriting,
            'freePreviewIds' => $freePreviewIds,
        ]);
    }

    public function show(WritingTask $task, FeatureGate $featureGate)
    {
        abort_if(!$task->is_active || $task->mode !== 'practice', 404);

        $user = Auth::user();
        $canWriting = $user && ($user->is_admin || $featureGate->userCan($user, 'writing_ai'));
        $freePreviewIds = WritingTask::freePreviewIds('practice');
        $isPreview = in_array($task->id, $freePreviewIds, true);

        if (!$canWriting && !$isPreview) {
            return view('writing.locked');
        }

        $latestSubmission = WritingSubmission::where('user_id', Auth::id())
            ->where('writing_task_id', $task->id)
            ->latest()
            ->first();

        return view('writing.show', [
            'task' => $task,
            'latestSubmission' => $latestSubmission,
            'isPreview' => $isPreview,
        ]);
    }
}
