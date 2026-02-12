@php($task = $task ?? null)
<div class="grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="text-sm font-semibold text-slate-600">{{ __('app.title') }}</label>
        <input type="text" name="title" value="{{ old('title', $task?->title) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
        @error('title')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.type') }}</label>
        <select name="task_type" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            @foreach(['task1' => 'Task 1', 'task2' => 'Task 2'] as $value => $label)
                <option value="{{ $value }}" @selected(old('task_type', $task?->task_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.difficulty_label') }}</label>
        <select name="difficulty" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            @foreach(['A1','A2','B1','B2','C1','C2'] as $level)
                <option value="{{ $level }}" @selected(old('difficulty', $task?->difficulty) === $level)>{{ $level }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.question_mode') }}</label>
        <select name="mode" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            @php($selectedMode = old('mode', $task?->mode ?? 'practice'))
            <option value="practice" @selected($selectedMode === 'practice')>{{ __('app.practice_questions') }}</option>
            <option value="mock" @selected($selectedMode === 'mock')>{{ __('app.mock_questions') }}</option>
        </select>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.writing_time_limit_label', ['count' => '']) }}</label>
        <input type="number" name="time_limit_minutes" value="{{ old('time_limit_minutes', $task?->time_limit_minutes) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.writing_min_words_label', ['count' => '']) }}</label>
        <input type="number" name="min_words" value="{{ old('min_words', $task?->min_words) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.writing_max_words_label', ['count' => '']) }}</label>
        <input type="number" name="max_words" value="{{ old('max_words', $task?->max_words) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-semibold text-slate-600">{{ __('app.writing_prompt') }}</label>
        <textarea name="prompt" rows="6" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('prompt', $task?->prompt) }}</textarea>
        @error('prompt')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <div class="md:col-span-2 flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $task?->is_active ?? true))>
        <label for="is_active" class="text-sm text-slate-600">{{ __('app.active') }}</label>
    </div>
</div>
