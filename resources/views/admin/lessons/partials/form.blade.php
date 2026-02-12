@php($lesson = $lesson ?? null)
@if($errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        <div class="font-medium">{{ __('app.fix_following') }}</div>
        <ul class="mt-1 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label class="block text-sm font-medium">{{ __('app.title') }}</label>
    <input name="title" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('title', $lesson?->title) }}" required>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.type') }}</label>
    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
        @foreach(['reading' => __('app.reading'), 'listening' => __('app.listening')] as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $lesson?->type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.difficulty') }}</label>
    <select name="difficulty" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
        @foreach(['A1','A2','B1','B2'] as $level)
            <option value="{{ $level }}" @selected(old('difficulty', $lesson?->difficulty) === $level)>{{ $level }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.reading_content') }}</label>
    <textarea name="content_text" rows="6" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('content_text', $lesson?->content_text) }}</textarea>
    <p class="mt-1 text-xs text-slate-500">{{ __('app.create_lesson_help') }}</p>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.audio_url') }}</label>
    <input name="audio_url" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('audio_url', $lesson?->audio_url) }}">
</div>

@if($lesson)
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="regenerate_questions" value="1">
        {{ __('app.regenerate_questions') }}
    </label>
@endif
