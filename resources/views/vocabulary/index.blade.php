<x-layouts.app :title="__('app.vocabulary')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.vocabulary') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.vocabulary') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.vocabulary_intro') }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($lists as $list)
            <a href="{{ route('vocabulary.show', $list) }}" class="group rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $list->title }}</h2>
                    @if($list->level)
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $list->level }}</span>
                    @endif
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($list->description, 120) }}</p>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $list->items_count }} {{ __('app.words') }}</span>
                    <span class="group-hover:text-slate-700">→</span>
                </div>
            </a>
        @empty
            <p class="text-slate-500">{{ __('app.no_vocab_lists') }}</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $lists->links() }}</div>
</x-layouts.app>
