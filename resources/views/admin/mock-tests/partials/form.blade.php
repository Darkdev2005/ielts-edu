@php($test = $test ?? null)

@if($errors->any())
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
        <div class="font-semibold">{{ __('app.fix_following') }}</div>
        <ul class="mt-2 list-disc pl-5 text-xs">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label class="block text-sm font-medium">{{ __('app.type') }}</label>
    <select name="module" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
        <option value="reading" @selected(old('module', $test?->module) === 'reading')>{{ __('app.reading') }}</option>
        <option value="listening" @selected(old('module', $test?->module) === 'listening')>{{ __('app.listening') }}</option>
    </select>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.title') }}</label>
    <input name="title" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('title', $test?->title) }}" required>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.description') }}</label>
    <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('description', $test?->description) }}</textarea>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium">{{ __('app.time_limit_seconds') }}</label>
        <input name="time_limit" type="number" min="60" max="7200" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('time_limit', $test?->time_limit ?? 3600) }}" required>
    </div>
    <div>
        <label class="block text-sm font-medium">{{ __('app.total_questions') }}</label>
        <input name="total_questions" type="number" min="1" max="200" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('total_questions', $test?->total_questions ?? 40) }}" required>
    </div>
</div>

<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $test?->is_active ?? true))>
    <span>{{ __('app.active') }}</span>
</label>
