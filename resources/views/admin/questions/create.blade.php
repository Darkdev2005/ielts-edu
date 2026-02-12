<x-layouts.app :title="__('app.create_question')">
    <div class="flex flex-col gap-2">
        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
            {{ __('app.admin') }}
        </div>
        <h1 class="text-3xl font-semibold">{{ __('app.create_question') }}</h1>
        <p class="text-sm text-slate-500">{{ $lesson->title }}</p>
    </div>

    <form method="POST" action="{{ route('admin.questions.store', $lesson) }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium">Question type</label>
            @php($selectedType = old('type', 'mcq'))
            <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                <option value="mcq" @selected($selectedType === 'mcq')>MCQ</option>
                <option value="tfng" @selected($selectedType === 'tfng')>TFNG</option>
                <option value="completion" @selected($selectedType === 'completion')>Completion</option>
                <option value="matching" @selected($selectedType === 'matching')>Matching</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.prompt') }}</label>
            <textarea name="prompt" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>{{ old('prompt') }}</textarea>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            @foreach(['A','B','C','D'] as $i => $letter)
                <div>
                    <label class="block text-sm font-medium">{{ __('app.option_'.strtolower($letter)) }}</label>
                    <input name="options[]" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('options.'.$i) }}">
                </div>
            @endforeach
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('app.correct_answer') }}</label>
            <input name="correct_answer" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" value="{{ old('correct_answer') }}" required>
            <p class="mt-1 text-xs text-slate-500">MCQ: A/B/C/D. TFNG: TRUE/FALSE/NOT GIVEN. Matching: 1:A|2:C|3:B. Completion: answer or a|b</p>
        </div>

        <div>
            <label class="block text-sm font-medium">Matching items</label>
            <textarea name="matching_items" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" placeholder="Item 1 | Item 2 | Item 3">{{ old('matching_items') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Matching uchun itemlar: yangi qator yoki | bilan ajrating.</p>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('app.ai_explanation') }}</label>
            <textarea name="ai_explanation" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('ai_explanation') }}</textarea>
        </div>

        <div class="flex items-center gap-4">
            <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">{{ __('app.create') }}</button>
            <a href="{{ route('admin.questions.index', $lesson) }}" class="text-sm hover:underline">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
