<x-layouts.app :title="__('app.vocab_translate_title')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.vocabulary') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.vocab_translate_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.vocab_translate_intro') }}</p>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="text-sm font-semibold text-slate-900">{{ __('app.vocab_quick_translate_title') }}</div>
        <p class="mt-1 text-xs text-slate-500">{{ __('app.vocab_quick_translate_hint') }}</p>

        <form method="POST" action="{{ route('vocabulary.translate.quick') }}" class="mt-4 grid gap-3 md:grid-cols-[1.2fr_1fr_auto] md:items-end">
            @csrf
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.vocab_quick_term') }}</label>
                <input
                    type="text"
                    name="term"
                    required
                    value="{{ old('term', $quickTerm ?? '') }}"
                    placeholder="{{ __('app.vocab_quick_placeholder') }}"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"
                >
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.vocab_quick_example') }}</label>
                <input
                    type="text"
                    name="example"
                    value="{{ old('example', $quickExample ?? '') }}"
                    placeholder="{{ __('app.vocab_quick_example_placeholder') }}"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700"
                >
            </div>
            <div>
                <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-slate-800">
                    {{ __('app.vocab_quick_submit') }}
                </button>
            </div>
        </form>

        @if(!empty($quickError))
            <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {{ $quickError }}
            </div>
        @endif

        @if(!empty($quickResult))
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.translation_uz') }}</div>
                    <div class="mt-2 text-sm text-slate-700">{{ $quickResult['uz'] ?: '-' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.translation_ru') }}</div>
                    <div class="mt-2 text-sm text-slate-700">{{ $quickResult['ru'] ?: '-' }}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($lists as $list)
            @php
                $missing = (int) ($missingCounts[$list->id] ?? 0);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $list->title }}</h2>
                    @if($list->level)
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $list->level }}</span>
                    @endif
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($list->description, 120) }}</p>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $list->items_count }} {{ __('app.words') }}</span>
                    <span>{{ __('app.vocab_translate_missing_label') }}: {{ $missing }}</span>
                </div>
                <div class="mt-4">
                    @if($missing > 0)
                        <form method="POST" action="{{ route('vocabulary.translate.form') }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="list_id" value="{{ $list->id }}">
                            <label class="text-xs text-slate-500">{{ __('app.vocab_translate_limit') }}</label>
                            <input
                                type="number"
                                name="limit"
                                min="1"
                                max="50"
                                value="40"
                                class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-700"
                            >
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-slate-800">
                                {{ __('app.vocab_translate_start') }}
                            </button>
                        </form>
                        <div class="mt-2 text-xs text-slate-400">{{ __('app.vocab_translate_note') }}</div>
                    @else
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                            {{ __('app.vocab_translate_none') }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-slate-500">{{ __('app.no_vocab_lists') }}</p>
        @endforelse
    </div>
</x-layouts.app>
