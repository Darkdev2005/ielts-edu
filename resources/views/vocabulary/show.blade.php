<x-layouts.app :title="$list->title">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.vocabulary') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $list->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $list->description }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">{{ __('app.words_due') }}</div>
            <div class="text-2xl font-semibold">{{ $queueCount }}</div>
            <div class="mt-1 text-xs text-white/60">
                {{ __('app.new_words') }}: {{ $newCount }} · {{ __('app.review_words') }}: {{ $dueCount }}
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('vocabulary.review', $list) }}" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">
            {{ __('app.start_review') }}
        </a>
        @if($missingTranslations > 0)
            <form method="POST" action="{{ route('vocabulary.translate', $list) }}">
                @csrf
                <input type="hidden" name="limit" value="40">
                <button class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    {{ __('app.vocab_translate_action') }} ({{ $missingTranslations }})
                </button>
            </form>
        @endif
        <span class="text-sm text-slate-500">{{ $list->items_count }} {{ __('app.words') }}</span>
    </div>

    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.progress_last_7_days') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.reviews') }}: {{ $weeklyReviews }}</div>
            </div>
        </div>

        @php
            $max = max(1, $progressDays->max('count'));
        @endphp
        <div class="mt-6 grid grid-cols-7 items-end gap-2">
            @foreach($progressDays as $day)
                @php
                    $height = (int) round(($day['count'] / $max) * 80) + 8;
                @endphp
                <div class="flex flex-col items-center gap-2">
                    <div class="w-full rounded-lg bg-amber-100" @style(['height' => $height.'px'])>
                        <div class="h-full w-full rounded-lg bg-amber-400"></div>
                    </div>
                    <div class="text-[10px] text-slate-400">{{ $day['label'] }}</div>
                    <div class="text-[10px] text-slate-500">{{ $day['count'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.vocabulary_table') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.vocabulary_table_hint') }}</div>
            </div>
            <div class="text-xs text-slate-400">
                {{ __('app.total') }}: {{ $items->total() }}
            </div>
        </div>

        <div class="mt-4 md:hidden space-y-3">
            @forelse($items as $item)
                @php
                    $uz = $item->definition_uz ?: $item->definition;
                    $ru = $item->definition_ru ?: $item->definition;
                @endphp
                <div class="rounded-xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-semibold text-slate-900">{{ $item->term }}</div>
                        @if($item->part_of_speech)
                            <div class="inline-flex rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                {{ $item->part_of_speech }}
                            </div>
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-slate-500">
                        {{ __('app.pronunciation') }}: <span class="text-slate-700">{{ $item->pronunciation ?: '-' }}</span>
                    </div>
                    <div class="mt-3">
                        <div class="text-[10px] uppercase text-slate-400">{{ __('app.translation_uz') }}</div>
                        <div class="text-sm text-slate-700">{{ $uz ?: '-' }}</div>
                    </div>
                    <div class="mt-2">
                        <div class="text-[10px] uppercase text-slate-400">{{ __('app.translation_ru') }}</div>
                        <div class="text-sm text-slate-700">{{ $ru ?: '-' }}</div>
                    </div>
                    @if($item->example)
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="font-semibold text-slate-600">{{ __('app.example') }}:</span> {{ $item->example }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white/90 p-4 text-center text-sm text-slate-500">
                    {{ __('app.no_words') }}
                </div>
            @endforelse
        </div>

        <div class="mt-4 hidden md:block overflow-x-auto">
            <table class="min-w-[900px] w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-semibold">{{ __('app.term') }}</th>
                        <th class="px-3 py-2 font-semibold">{{ __('app.pronunciation') }}</th>
                        <th class="px-3 py-2 font-semibold">{{ __('app.translation_uz') }}</th>
                        <th class="px-3 py-2 font-semibold">{{ __('app.translation_ru') }}</th>
                        <th class="px-3 py-2 font-semibold">{{ __('app.example') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        @php
                            $uz = $item->definition_uz ?: $item->definition;
                            $ru = $item->definition_ru ?: $item->definition;
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-3 py-3 align-top">
                                <div class="font-semibold text-slate-900">{{ $item->term }}</div>
                                @if($item->part_of_speech)
                                    <div class="mt-1 inline-flex rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                        {{ $item->part_of_speech }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 align-top text-slate-600">
                                {{ $item->pronunciation ?: '-' }}
                            </td>
                            <td class="px-3 py-3 align-top text-slate-700">
                                {{ $uz ?: '-' }}
                            </td>
                            <td class="px-3 py-3 align-top text-slate-700">
                                {{ $ru ?: '-' }}
                            </td>
                            <td class="px-3 py-3 align-top text-slate-500">
                                {{ $item->example ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">
                                {{ __('app.no_words') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->onEachSide(0)->links('pagination::simple-tailwind') }}
        </div>
    </div>
</x-layouts.app>
