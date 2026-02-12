<x-layouts.app :title="__('app.lessons')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                {{ __('app.lessons') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.choose_lesson') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.lessons_intro') }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">{{ __('app.total_lessons') }}</div>
            <div class="text-2xl font-semibold">{{ $readingLessons->total() + $listeningLessons->total() }}</div>
        </div>
    </div>

    
    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="#listening-section" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.listening') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.listening') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
        </a>
        <a href="#reading-section" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.reading') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.reading') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
        </a>
        <a href="{{ route('writing.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.writing') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.writing_intro') }}</div>
        </a>
        <a href="{{ route('speaking.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.speaking') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.speaking') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.speaking_intro') }}</div>
        </a>
    </div>

    <div class="mt-10" id="listening-section">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('app.listening') }}</h2>
            <span class="text-xs text-slate-500">{{ $listeningLessons->total() }} {{ __('app.lessons') }}</span>
        </div>
        <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse($listeningLessons as $lesson)
                @php($isRecommended = in_array($lesson->id, $recommendedLessonIds ?? [], true))
                @php($requiredLabel = $lesson->requiredPlanLabel())
                @php($isLocked = $requiredLabel === 'PRO'
                    ? empty($canListeningPro)
                    : ($requiredLabel === 'PLUS' ? empty($canListeningFull) : false))
                @if($isLocked)
                    <button type="button"
                        class="group relative rounded-2xl border border-rose-200 bg-rose-50/60 p-4 text-left shadow-sm transition hover:-translate-y-1 hover:border-rose-300 hover:shadow-lg sm:p-5 {{ $isRecommended ? 'ring-2 ring-emerald-400 shadow-lg' : '' }}"
                        @click="$dispatch('open-paywall', { feature: '{{ __('app.upgrade_required') }}' })"
                    >
                @else
                    <a href="{{ route('lessons.show', $lesson) }}" class="group rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg sm:p-5 {{ $isRecommended ? 'ring-2 ring-emerald-400 bg-emerald-50/60 shadow-lg' : '' }}">
                @endif
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $lesson->title }}</h2>
                        <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs uppercase text-slate-600">{{ $lesson->type }}</span>
                            @if($isLocked && $requiredLabel)
                                <span class="rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ $requiredLabel }}</span>
                            @endif
                            @if($isRecommended)
                                <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ __('app.recommended') }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">{{ $lesson->content_text ? \Illuminate\Support\Str::limit($lesson->content_text, 110) : __('app.listening_preview') }}</p>
                    <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $lesson->difficulty }}</span>
                            <span class="text-xs text-slate-400">{{ __('app.cefr_level') }}</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ $lesson->questions_count }} {{ __('app.questions_label') }}
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                        <span>{{ $isLocked ? __('app.upgrade_required') : __('app.ready_to_practice') }}</span>
                        <span class="group-hover:text-slate-700">&rarr;</span>
                    </div>
                @if($isLocked)
                    </button>
                @else
                    </a>
                @endif
            @empty
                <p class="text-slate-500">{{ __('app.no_lessons') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $listeningLessons->links() }}</div>
    </div>
    <div class="mt-10" id="reading-section">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('app.reading') }}</h2>
            <span class="text-xs text-slate-500">{{ $readingLessons->total() }} {{ __('app.lessons') }}</span>
        </div>
        <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse($readingLessons as $lesson)
                @php($isRecommended = in_array($lesson->id, $recommendedLessonIds ?? [], true))
                @php($requiredLabel = $lesson->requiredPlanLabel())
                @php($isLocked = $requiredLabel === 'PRO'
                    ? empty($canReadingPro)
                    : ($requiredLabel === 'PLUS' ? empty($canReadingFull) : false))
                @if($isLocked)
                    <button type="button"
                        class="group relative rounded-2xl border border-rose-200 bg-rose-50/60 p-4 text-left shadow-sm transition hover:-translate-y-1 hover:border-rose-300 hover:shadow-lg sm:p-5 {{ $isRecommended ? 'ring-2 ring-emerald-400 shadow-lg' : '' }}"
                        @click="$dispatch('open-paywall', { feature: '{{ __('app.upgrade_required') }}' })"
                    >
                @else
                    <a href="{{ route('lessons.show', $lesson) }}" class="group rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg sm:p-5 {{ $isRecommended ? 'ring-2 ring-emerald-400 bg-emerald-50/60 shadow-lg' : '' }}">
                @endif
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $lesson->title }}</h2>
                        <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs uppercase text-slate-600">{{ $lesson->type }}</span>
                            @if($isLocked && $requiredLabel)
                                <span class="rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ $requiredLabel }}</span>
                            @endif
                            @if($isRecommended)
                                <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ __('app.recommended') }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">{{ $lesson->content_text ? \Illuminate\Support\Str::limit($lesson->content_text, 110) : __('app.listening_preview') }}</p>
                    <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $lesson->difficulty }}</span>
                            <span class="text-xs text-slate-400">{{ __('app.cefr_level') }}</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ $lesson->questions_count }} {{ __('app.questions_label') }}
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                        <span>{{ $isLocked ? __('app.upgrade_required') : __('app.ready_to_practice') }}</span>
                        <span class="group-hover:text-slate-700">&rarr;</span>
                    </div>
                @if($isLocked)
                    </button>
                @else
                    </a>
                @endif
            @empty
                <p class="text-slate-500">{{ __('app.no_lessons') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $readingLessons->links() }}</div>
    </div>
</x-layouts.app>
