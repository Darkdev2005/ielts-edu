<x-layouts.app :title="__('app.edit_rule')">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.edit_rule') }}</h1>
            <p class="text-sm text-slate-500">{{ $topic->title }}</p>
        </div>
        <a href="{{ route('admin.grammar.rules.index', $topic) }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.grammar.rules.update', [$topic, $rule]) }}" enctype="multipart/form-data" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.rule_key') }}</label>
                <input name="rule_key" value="{{ old('rule_key', $rule->rule_key) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
                <p class="mt-1 text-xs text-slate-400">{{ __('app.rule_key_hint') }}</p>
                @error('rule_key')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.rule_type') }}</label>
                <select name="rule_type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    @foreach(['core','usage','note','exception'] as $type)
                        <option value="{{ $type }}" @selected(old('rule_type', $rule->rule_type) === $type)>{{ strtoupper($type) }}</option>
                    @endforeach
                </select>
                @error('rule_type')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.cefr_level') }}</label>
                <select name="cefr_level" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.optional') }}</option>
                    @foreach(['A1','A2','B1','B2','C1'] as $level)
                        <option value="{{ $level }}" @selected(old('cefr_level', $rule->cefr_level) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                @error('cefr_level')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.rule_text_uz') }}</label>
                <textarea name="rule_text_uz" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('rule_text_uz', $rule->rule_text_uz) }}</textarea>
                @error('rule_text_uz')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.rule_text_en') }}</label>
                <textarea name="rule_text_en" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('rule_text_en', $rule->rule_text_en) }}</textarea>
                @error('rule_text_en')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.rule_text_ru') }}</label>
                <textarea name="rule_text_ru" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('rule_text_ru', $rule->rule_text_ru) }}</textarea>
                @error('rule_text_ru')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('app.formula') }}</label>
            <textarea name="formula" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('formula', $rule->formula) }}</textarea>
            @error('formula')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.example_uz') }}</label>
                <textarea name="example_uz" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('example_uz', $rule->example_uz) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.example_en') }}</label>
                <textarea name="example_en" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('example_en', $rule->example_en) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.example_ru') }}</label>
                <textarea name="example_ru" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('example_ru', $rule->example_ru) }}</textarea>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('app.negative_example') }}</label>
                <textarea name="negative_example" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('negative_example', $rule->negative_example) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.common_mistake') }}</label>
                <textarea name="common_mistake" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('common_mistake', $rule->common_mistake) }}</textarea>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.correct_form') }}</label>
            <textarea name="correct_form" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('correct_form', $rule->correct_form) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('app.rule_image') }}</label>
            <input type="file" name="image" accept="image/*" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" />
            <p class="mt-2 text-xs text-slate-500">{{ __('app.rule_image_hint') }}</p>
            @if($rule->image_path)
                <label class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300" />
                    <span>{{ __('app.remove_image') }}</span>
                </label>
            @endif
            @error('image')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.sort_order') }}</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $rule->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            @error('sort_order')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.save') }}</button>
            <a href="{{ route('admin.grammar.rules.index', $topic) }}" class="text-sm hover:underline">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
