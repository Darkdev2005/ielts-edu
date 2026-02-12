<x-layouts.app :title="__('app.mock_tests')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.mock_tests') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.mock_tests_intro') }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">{{ __('app.total_lessons') }}</div>
            <div class="text-2xl font-semibold">{{ $mockListeningLessons->count() }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($mockListeningLessons as $lesson)
            @php($requiredLabel = $lesson->requiredPlanLabel())
            @php($isLocked = $requiredLabel === 'PRO'
                ? empty($canListeningPro)
                : ($requiredLabel === 'PLUS' ? empty($canListeningFull) : false))
            @if($isLocked)
                <button type="button"
                    class="group relative rounded-2xl border border-rose-200 bg-rose-50/60 p-4 text-left shadow-sm transition hover:-translate-y-1 hover:border-rose-300 hover:shadow-lg sm:p-5"
                    @click="$dispatch('open-paywall', { feature: '{{ __('app.upgrade_required') }}' })"
                >
            @else
                <a href="{{ route('lessons.mock', $lesson) }}" class="group rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg sm:p-5">
            @endif
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $lesson->title }}</h2>
                    <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs uppercase text-slate-600">mock</span>
                        @if($isLocked && $requiredLabel)
                            <span class="rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ $requiredLabel }}</span>
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">
                    {{ $lesson->mock_content_text ? \Illuminate\Support\Str::limit($lesson->mock_content_text, 110) : __('app.mock_preview') }}
                </p>
                <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $lesson->difficulty }}</span>
                        <span class="text-xs text-slate-400">{{ __('app.cefr_level') }}</span>
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ $lesson->mock_questions_count ?? 0 }} {{ __('app.questions_label') }}
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $isLocked ? __('app.upgrade_required') : __('app.start_mock') }}</span>
                    <span class="group-hover:text-slate-700">&rarr;</span>
                </div>
            @if($isLocked)
                </button>
            @else
                </a>
            @endif
        @empty
            <p class="text-slate-500">{{ __('app.no_mock_lessons') }}</p>
        @endforelse
    </div>
</x-layouts.app>
