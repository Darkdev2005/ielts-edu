<x-layouts.app :title="__('app.edit_exercise')">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.edit_exercise') }}</h1>
            <p class="text-sm text-slate-500">{{ $topic->title }}</p>
        </div>
        <a href="{{ route('admin.grammar.exercises.index', $topic) }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.grammar.exercises.update', [$topic, $exercise]) }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">{{ __('app.rule') }}</label>
            <select name="grammar_rule_id" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                @foreach($rules as $rule)
                    <option value="{{ $rule->id }}" @selected(old('grammar_rule_id', $exercise->grammar_rule_id) == $rule->id)>
                        {{ $rule->rule_key }} · {{ \Illuminate\Support\Str::limit($rule->rule_text_uz ?? $rule->title, 60) }}
                    </option>
                @endforeach
            </select>
            @error('grammar_rule_id')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.exercise_type') }}</label>
            <select name="exercise_type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                @foreach(['mcq','gap','tf','reorder'] as $type)
                    <option value="{{ $type }}" @selected(old('exercise_type', $exercise->exercise_type ?? $exercise->type) === $type)>{{ strtoupper($type) }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('app.exercise_type_hint') }}</p>
            @error('exercise_type')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.question') }}</label>
            <textarea name="question" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('question', $exercise->question ?? $exercise->prompt) }}</textarea>
            @error('question')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @php($options = (array) ($exercise->options ?? []))
            <div>
                <label class="block text-sm font-medium">{{ __('app.option_a') }}</label>
                <input name="option_a" value="{{ old('option_a', $options['A'] ?? ($options[0] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.option_b') }}</label>
                <input name="option_b" value="{{ old('option_b', $options['B'] ?? ($options[1] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.option_c') }}</label>
                <input name="option_c" value="{{ old('option_c', $options['C'] ?? ($options[2] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.option_d') }}</label>
                <input name="option_d" value="{{ old('option_d', $options['D'] ?? ($options[3] ?? '')) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            </div>
        </div>
        @error('options')
            <div class="text-xs text-rose-600">{{ $message }}</div>
        @enderror
        <div>
            <label class="block text-sm font-medium">{{ __('app.correct_answer') }}</label>
            <input name="correct_answer" value="{{ old('correct_answer', $exercise->correct_answer) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            <p class="mt-1 text-xs text-slate-400">{{ __('app.correct_answer_hint') }}</p>
            @error('correct_answer')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.explanation_uz') }}</label>
                <textarea name="explanation_uz" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('explanation_uz', $exercise->explanation_uz) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.explanation_en') }}</label>
                <textarea name="explanation_en" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('explanation_en', $exercise->explanation_en) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.explanation_ru') }}</label>
                <textarea name="explanation_ru" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('explanation_ru', $exercise->explanation_ru) }}</textarea>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('app.cefr_level') }}</label>
                <select name="cefr_level" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.optional') }}</option>
                    @foreach(['A1','A2','B1','B2','C1'] as $level)
                        <option value="{{ $level }}" @selected(old('cefr_level', $exercise->cefr_level) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.sort_order') }}</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $exercise->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.save') }}</button>
            <a href="{{ route('admin.grammar.exercises.index', $topic) }}" class="text-sm hover:underline">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
