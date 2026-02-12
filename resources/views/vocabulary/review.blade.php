<x-layouts.app :title="__('app.review')">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.vocabulary') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $list->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.review_instructions') }}</p>
        </div>
    </div>

    @if(!$item)
        <div class="mt-8 rounded-2xl border border-dashed border-slate-200 bg-white/90 p-6 text-slate-500">
            <div class="font-semibold text-slate-700">{{ __('app.no_items_due') }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __('app.no_items_due_help') }}</div>
            <form method="POST" action="{{ route('vocabulary.reset', $list) }}" class="mt-4">
                @csrf
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.reset_progress') }}
                </button>
            </form>
        </div>
    @else
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-xs text-slate-400">{{ $item->part_of_speech }}</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $item->term }}</div>
            @if($item->definition)
                <div class="mt-3 text-sm text-slate-600">{{ $item->definition }}</div>
            @endif
            @php($locale = app()->getLocale())
            @if(($locale === 'uz' && $item->definition_uz) || ($locale === 'ru' && $item->definition_ru))
                <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <summary class="cursor-pointer font-semibold text-slate-700">
                        {{ __('app.translation') }}
                    </summary>
                    <div class="mt-2 text-slate-500">
                        @if($locale === 'uz')
                            {{ $item->definition_uz }}
                        @else
                            {{ $item->definition_ru }}
                        @endif
                    </div>
                </details>
            @endif
            @if($item->example)
                <div class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    “{{ $item->example }}”
                </div>
            @endif
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-4">
            @foreach([
                0 => __('app.again'),
                1 => __('app.hard'),
                2 => __('app.good'),
                3 => __('app.easy'),
            ] as $value => $label)
                <form method="POST" action="{{ route('vocabulary.grade', [$list, $item]) }}">
                    @csrf
                    <input type="hidden" name="quality" value="{{ $value }}">
                    <button class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50">
                        {{ $label }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</x-layouts.app>
