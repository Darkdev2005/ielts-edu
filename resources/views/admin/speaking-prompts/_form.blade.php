@php($prompt = $prompt ?? null)
<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="text-sm font-semibold text-slate-600">Part</label>
        <select name="part" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            @foreach([1,2,3] as $part)
                <option value="{{ $part }}" @selected(old('part', $prompt?->part ?? 1) == $part)>Part {{ $part }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.question_mode') }}</label>
        @php($selectedMode = old('mode', $prompt?->mode ?? 'practice'))
        <select name="mode" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            <option value="practice" @selected($selectedMode === 'practice')>{{ __('app.practice_questions') }}</option>
            <option value="mock" @selected($selectedMode === 'mock')>{{ __('app.mock_questions') }}</option>
        </select>
    </div>
    <div>
        <label class="text-sm font-semibold text-slate-600">{{ __('app.difficulty_label') }}</label>
        <input type="text" name="difficulty" value="{{ old('difficulty', $prompt?->difficulty) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2" placeholder="A1/B1">
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-semibold text-slate-600">{{ __('app.prompt') }}</label>
        <textarea name="prompt" rows="5" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('prompt', $prompt?->prompt) }}</textarea>
    </div>
    <div class="md:col-span-2 flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $prompt?->is_active ?? true))>
        <label for="is_active" class="text-sm text-slate-600">{{ __('app.active') }}</label>
    </div>
</div>
