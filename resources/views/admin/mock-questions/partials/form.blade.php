@php($question = $question ?? null)
@php($options = old('options', $question?->options_json ?? []))

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

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label class="block text-sm font-medium">{{ __('app.type') }}</label>
        <select name="question_type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
            @foreach(['mcq', 'tfng', 'ynng', 'completion', 'matching'] as $type)
                <option value="{{ $type }}" @selected(old('question_type', $question?->question_type) === $type)>{{ strtoupper($type) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium">{{ __('app.correct_answer') }}</label>
        <input name="correct_answer" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('correct_answer', $question?->correct_answer) }}" required>
        <p class="mt-1 text-xs text-slate-500">{{ __('app.mock_correct_answer_hint') }}</p>
    </div>
    <div>
        <label class="block text-sm font-medium">{{ __('app.order') }}</label>
        <input type="number" min="1" max="999" name="order_index" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('order_index', $question?->order_index ?? 1) }}" required>
    </div>
</div>

<div>
    <label class="block text-sm font-medium">{{ __('app.prompt') }}</label>
    <textarea name="question_text" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>{{ old('question_text', $question?->question_text) }}</textarea>
</div>

<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">A</label>
        <input name="options[A]" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ $options['A'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">B</label>
        <input name="options[B]" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ $options['B'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">C</label>
        <input name="options[C]" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ $options['C'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">D</label>
        <input name="options[D]" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ $options['D'] ?? '' }}">
    </div>
</div>
