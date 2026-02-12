<x-layouts.app :title="__('app.edit_topic')">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.edit_topic') }}</h1>
        </div>
        <a href="{{ route('admin.grammar.topics.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.grammar.topics.update', $topic) }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">{{ __('app.topic_key') }}</label>
            <input name="topic_key" value="{{ old('topic_key', $topic->topic_key) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
            <p class="mt-1 text-xs text-slate-400">{{ __('app.topic_key_hint') }}</p>
            @error('topic_key')
                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
            @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.title_uz') }}</label>
                <input name="title_uz" value="{{ old('title_uz', $topic->title_uz) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
                @error('title_uz')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.title_en') }}</label>
                <input name="title_en" value="{{ old('title_en', $topic->title_en) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
                @error('title_en')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.title_ru') }}</label>
                <input name="title_ru" value="{{ old('title_ru', $topic->title_ru) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
                @error('title_ru')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">{{ __('app.description_uz') }}</label>
                <textarea name="description_uz" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('description_uz', $topic->description_uz) }}</textarea>
                @error('description_uz')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.description_en') }}</label>
                <textarea name="description_en" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('description_en', $topic->description_en) }}</textarea>
                @error('description_en')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.description_ru') }}</label>
                <textarea name="description_ru" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('description_ru', $topic->description_ru) }}</textarea>
                @error('description_ru')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('app.cefr_level') }}</label>
                <select name="cefr_level" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.optional') }}</option>
                    @foreach(['A1','A2','B1','B2','C1'] as $level)
                        <option value="{{ $level }}" @selected(old('cefr_level', $topic->cefr_level) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                @error('cefr_level')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('app.sort_order') }}</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $topic->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" />
                @error('sort_order')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.save') }}</button>
            <a href="{{ route('admin.grammar.topics.index') }}" class="text-sm hover:underline">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
