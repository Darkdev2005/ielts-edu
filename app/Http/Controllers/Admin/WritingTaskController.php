<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WritingTask;
use Illuminate\Http\Request;

class WritingTaskController extends Controller
{
    public function index()
    {
        $mode = request('mode');
        $query = WritingTask::orderByDesc('id');
        if (in_array($mode, ['practice', 'mock'], true)) {
            $query->where('mode', $mode);
        }

        $tasks = $query->paginate(20)->withQueryString();

        return view('admin.writing.index', [
            'tasks' => $tasks,
            'mode' => $mode,
        ]);
    }

    public function create()
    {
        return view('admin.writing.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = $request->user()->id;
        $data['mode'] = $data['mode'] ?? 'practice';

        WritingTask::create($data);

        return redirect()->route('admin.writing.index')->with('status', __('app.saved'));
    }

    public function edit(WritingTask $task)
    {
        return view('admin.writing.edit', [
            'task' => $task,
        ]);
    }

    public function update(Request $request, WritingTask $task)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['mode'] = $data['mode'] ?? 'practice';
        $task->update($data);

        return redirect()->route('admin.writing.index')->with('status', __('app.saved'));
    }

    public function destroy(WritingTask $task)
    {
        $task->delete();

        return redirect()->route('admin.writing.index')->with('status', __('app.deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', 'in:task1,task2'],
            'prompt' => ['required', 'string'],
            'difficulty' => ['required', 'in:A1,A2,B1,B2,C1,C2'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'min_words' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'max_words' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:practice,mock'],
        ]);
    }
}
