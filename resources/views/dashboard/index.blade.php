<x-layouts.app :title="__('app.dashboard')">
    @php
        $primaryUrl = route('grammar.index');
        $primaryLabel = __('app.start_grammar');
        $primarySub = null;
        $primarySubLabel = null;

        if (($lessonWeaknesses ?? collect())->isNotEmpty()) {
            $weakLesson = $lessonWeaknesses->first();
            $primarySub = $weakLesson->title;
            $primarySubLabel = __('app.weak_lesson_focus');
        } elseif ($lastAttempt?->lesson) {
            $primarySub = $lastAttempt->lesson->title ?? null;
            $primarySubLabel = __('app.continue_last_lesson');
        }
    @endphp

    <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-emerald-50 p-6 text-slate-900 shadow-lg">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                    {{ __('app.dashboard') }}
                </div>
                <h1 class="mt-4 text-3xl font-semibold">{{ __('app.your_progress') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ __('app.dashboard_intro') }}</p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a href="{{ $primaryUrl }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm">
                        {{ $primaryLabel }}
                    </a>
                    <a href="{{ route('lessons.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">
                        {{ __('app.browse_lessons') }}
                    </a>
                    @if($primarySub)
                        <span class="text-xs text-slate-400">
                            {{ $primarySubLabel }}: {{ $primarySub }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs text-slate-500">{{ __('app.total_attempts') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $stats->total_attempts ?? 0 }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs text-slate-500">{{ __('app.average_score') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $stats->avg_score ? round($stats->avg_score * 100) : 0 }}%</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs text-slate-500">{{ __('app.grammar_best_score') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $grammarBestScore ? round($grammarBestScore * 100) : 0 }}%</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs text-slate-500">{{ __('app.vocab_mastered_words') }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $vocabMastered }}</div>
                </div>
            </div>
        </div>
    </div>

    @php
        $activePlan = $currentPlan ?? auth()->user()?->currentPlan;
        $planSlug = $activePlan?->slug;
        $planName = $activePlan?->name;
        $planBadgeClass = $planSlug === 'pro'
            ? 'bg-rose-600 text-white'
            : 'bg-emerald-600 text-white';
        $planPillClass = $planSlug === 'pro'
            ? 'border-rose-200 bg-rose-50 text-rose-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
        $planCardClass = $planSlug === 'pro'
            ? 'border-rose-200 bg-gradient-to-br from-rose-50 via-white to-amber-50'
            : 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50';
        $planGlowClass = $planSlug === 'pro'
            ? 'from-rose-300/50 via-amber-200/30 to-orange-200/30'
            : 'from-emerald-300/50 via-cyan-200/30 to-amber-200/30';
        $planIconClass = $planSlug === 'pro'
            ? 'bg-rose-600 text-white'
            : 'bg-emerald-600 text-white';
        $planFeatures = [];
        if ($planSlug === 'plus') {
            $planFeatures = [
                __('app.writing_ai'),
                __('app.reading_full'),
                __('app.listening_full'),
                __('app.ai_explanation_full'),
                __('app.analytics_full'),
                __('app.mock_tests'),
            ];
        } elseif ($planSlug === 'pro') {
            $planFeatures = [
                __('app.speaking_ai'),
                __('app.reading_full'),
                __('app.listening_full'),
                __('app.mock_tests'),
                __('app.ai_explanation_full'),
                __('app.reading').' B2+',
                __('app.listening').' B2+',
            ];
        }
    @endphp

    @if($planSlug && $planSlug !== 'free')
        <div class="mt-6 relative overflow-hidden rounded-2xl border p-5 shadow-sm {{ $planCardClass }}">
            <div class="pointer-events-none absolute -top-20 -right-16 h-44 w-44 rounded-full bg-gradient-to-br {{ $planGlowClass }} blur-2xl"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl shadow-sm {{ $planIconClass }}">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.97a1 1 0 0 0 .95.69h4.178c.969 0 1.371 1.24.588 1.81l-3.38 2.455a1 1 0 0 0-.364 1.118l1.286 3.97c.3.921-.755 1.688-1.538 1.118l-3.38-2.455a1 1 0 0 0-1.175 0l-3.38 2.455c-.783.57-1.838-.197-1.538-1.118l1.286-3.97a1 1 0 0 0-.364-1.118L2 9.397c-.783-.57-.38-1.81.588-1.81h4.178a1 1 0 0 0 .95-.69l1.286-3.97z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.plan') }}</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">
                            {{ __('app.plan_active_title', ['plan' => strtoupper((string) $planName)]) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ __('app.plan_active_hint') }}</div>
                    </div>
                </div>
                <span class="rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $planBadgeClass }}">
                    {{ strtoupper((string) $planName) }}
                </span>
            </div>
            @if(!empty($planFeatures))
                <div class="relative mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    @foreach($planFeatures as $featureLabel)
                        <span class="rounded-full border px-3 py-1 {{ $planPillClass }}">
                            {{ $featureLabel }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('app.grammar_topics') }}</h2>
            <a href="{{ route('grammar.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">
                {{ __('app.browse_grammar') }}
            </a>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @forelse($dashboardGrammarTopics as $topic)
                @php
                    $progress = $dashboardTopicProgress[$topic->id]['percent'] ?? null;
                    $progress = $progress !== null ? max(0, min(100, (int) $progress)) : null;
                    $isRecommended = $recommendedTopicId && $topic->id === $recommendedTopicId;
                    $progressTrackClass = 'bg-slate-200';
                    $progressBarClass = 'bg-emerald-500';
                    if ($progress !== null) {
                        if ($progress >= 75) {
                            $progressTrackClass = 'bg-emerald-100';
                            $progressBarClass = 'bg-emerald-500';
                        } elseif ($progress >= 40) {
                            $progressTrackClass = 'bg-amber-100';
                            $progressBarClass = 'bg-amber-400';
                        } else {
                            $progressTrackClass = 'bg-rose-100';
                            $progressBarClass = 'bg-rose-400';
                        }
                    }
                @endphp
                <a href="{{ route('grammar.show', $topic) }}" class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase text-slate-400">{{ $topic->cefr_level }}</span>
                        @if($isRecommended)
                            <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">
                                {{ __('app.recommended') }}
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 text-sm font-semibold text-slate-900">{{ $topic->title }}</div>
                    @if($topic->description)
                        <div class="mt-1 text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($topic->description, 70) }}</div>
                    @endif
                    <div class="mt-3">
                        @if($progress !== null)
                            <div class="flex items-center justify-between text-[10px] text-slate-400">
                                <span>{{ __('app.grammar') }}</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full {{ $progressTrackClass }}">
                                <div class="h-1.5 rounded-full {{ $progressBarClass }}" @style(['width' => $progress.'%'])></div>
                            </div>
                        @else
                            <div class="text-[10px] text-slate-400">{{ __('app.start_grammar') }}</div>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-4 text-sm text-slate-500">
                    {{ __('app.no_grammar_topics') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">{{ __('app.modules') }}</h2>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ route('lessons.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.reading') }}</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.reading') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
            </a>
            <a href="{{ route('lessons.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.listening') }}</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.listening') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
            </a>
            <a href="{{ route('vocabulary.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.vocabulary') }}</div>
                <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.vocabulary') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.words') }}</div>
            </a>
            <a href="{{ route('writing.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing') }}</div>
                        <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.writing') }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ __('app.writing_intro') }}</div>
                    </div>
                    @if(empty($canWriting))
                        <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">{{ __('app.free_preview') }}</span>
                    @endif
                </div>
            </a>
            @if(empty($canSpeaking))
                <button type="button"
                    class="relative rounded-2xl border border-rose-200 bg-rose-50/60 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    @click="$dispatch('open-paywall', { feature: '{{ __('app.speaking') }}' })"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase text-rose-700">{{ __('app.speaking') }}</div>
                            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.speaking') }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ __('app.speaking_intro') }}</div>
                        </div>
                        <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-semibold text-white">PRO</span>
                    </div>
                </button>
            @else
                <a href="{{ route('speaking.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.speaking') }}</div>
                    <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.speaking') }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ __('app.speaking_intro') }}</div>
                </a>
            @endif
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.personal_study_plan') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.weekly_targets') }}</div>
            </div>
            <form method="POST" action="{{ route('dashboard.study-plan.update') }}" class="flex flex-wrap items-end gap-2 text-xs">
                @csrf
                <label class="flex flex-col gap-1 text-slate-500">
                    {{ __('app.lessons') }}
                    <input type="number" name="lessons_target" min="1" max="20" value="{{ $studyPlan->lessons_target }}" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-slate-900">
                </label>
                <label class="flex flex-col gap-1 text-slate-500">
                    {{ __('app.grammar') }}
                    <input type="number" name="grammar_target" min="1" max="20" value="{{ $studyPlan->grammar_target }}" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-slate-900">
                </label>
                <label class="flex flex-col gap-1 text-slate-500">
                    {{ __('app.vocabulary') }}
                    <input type="number" name="vocab_target" min="1" max="20" value="{{ $studyPlan->vocab_target }}" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-slate-900">
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                    {{ __('app.save_plan') }}
                </button>
            </form>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('app.lessons') }}</span>
                    <span>{{ $studyProgress['lessons']['done'] }} / {{ $studyProgress['lessons']['target'] }}</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-200">
                    <div class="h-2 rounded-full bg-emerald-500" @style(['width' => $studyProgress['lessons']['percent'].'%'])></div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('app.grammar') }}</span>
                    <span>{{ $studyProgress['grammar']['done'] }} / {{ $studyProgress['grammar']['target'] }}</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-200">
                    <div class="h-2 rounded-full bg-indigo-500" @style(['width' => $studyProgress['grammar']['percent'].'%'])></div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('app.vocabulary') }}</span>
                    <span>{{ $studyProgress['vocab']['done'] }} / {{ $studyProgress['vocab']['target'] }}</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-200">
                    <div class="h-2 rounded-full bg-amber-500" @style(['width' => $studyProgress['vocab']['percent'].'%'])></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.grammar_attempts') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $grammarStats->total_attempts ?? 0 }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.grammar_attempts_hint') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.grammar_average_score') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">
                {{ $grammarStats->avg_score ? round($grammarStats->avg_score * 100) : 0 }}%
            </div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.grammar_average_score_hint') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.grammar_topics_practiced') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $grammarTopicsPracticed }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.grammar_topics_total', ['count' => $grammarTopicsTotal]) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.grammar_best_score') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $grammarBestScore ? round($grammarBestScore * 100) : 0 }}%</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.grammar_best_score_hint') }}</div>
        </div>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.vocab_total_words') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $vocabTotalWords }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.vocab_reviewed_words', ['count' => $vocabReviewed]) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.vocab_new_words') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $vocabNew }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.vocab_new_words_hint') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.vocab_due_words') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $vocabDue }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.vocab_due_words_hint') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.vocab_mastered_words') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $vocabMastered }}</div>
            <div class="mt-2 text-xs text-slate-400">{{ __('app.vocab_mastered_words_hint') }}</div>
        </div>
    </div>

    <div class="mt-10">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ __('app.recent_attempts') }}</h2>
            <a href="{{ route('lessons.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">{{ __('app.browse_lessons') }}</a>
        </div>
        <div class="mt-3 rounded-2xl border border-slate-200 bg-white/95 shadow-sm">
            <div class="max-h-[320px] overflow-y-auto">
                @forelse($recentAttempts as $attempt)
                    <a href="{{ route('attempts.show', $attempt) }}" class="block border-b border-slate-100 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $attempt->lesson->title }}</div>
                                <div class="mt-1 text-[11px] text-slate-400">{{ $attempt->completed_at }}</div>
                            </div>
                            <div class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                                {{ $attempt->score }} / {{ $attempt->total }}
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-4 text-sm text-slate-500">{{ __('app.no_attempts') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($canAnalyticsFull)
    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.lesson_weaknesses') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.lesson_weaknesses_hint') }}</div>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-400">{{ __('app.weak_period') }}</span>
                <a href="{{ route('dashboard', ['weak_days' => 7]) }}"
                   class="rounded-full px-3 py-1 text-xs font-semibold {{ ($weakDays ?? 30) === 7 ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
                    {{ __('app.last_7_days') }}
                </a>
                <a href="{{ route('dashboard', ['weak_days' => 30]) }}"
                   class="rounded-full px-3 py-1 text-xs font-semibold {{ ($weakDays ?? 30) === 30 ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
                    {{ __('app.last_30_days') }}
                </a>
                @if(($lessonWeaknesses ?? collect())->isNotEmpty())
                    <button
                        type="button"
                        x-data
                        x-on:click="$dispatch('open-modal', 'weak-lessons')"
                        class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700"
                    >
                        {{ __('app.practice_now') }}
                    </button>
                @endif
                <a href="{{ route('mistakes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.review_mistakes') }}</a>
            </div>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($lessonWeaknesses as $lesson)
                <a href="{{ route('lessons.show', $lesson->id) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-slate-300 hover:bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $lesson->title }}</div>
                            <div class="text-xs text-slate-400">
                                {{ $lesson->type === 'reading' ? __('app.reading') : __('app.listening') }} · {{ $lesson->difficulty }} · {{ __('app.last_attempted') }}:
                                {{ $lesson->last_attempted_at ? \Illuminate\Support\Carbon::parse($lesson->last_attempted_at)->format('Y-m-d H:i') : '-' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                                {{ __('app.mistakes_count', ['count' => $lesson->wrong_answers]) }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ __('app.accuracy') }} {{ $lesson->accuracy }}%
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-sm text-slate-500">{{ __('app.no_mistakes') }}</div>
            @endforelse
        </div>

        @if(($weakAccuracyTrend ?? collect())->isNotEmpty() && ($lessonWeaknesses ?? collect())->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-900">{{ __('app.accuracy_trend') }}</div>
                    <div class="text-xs text-slate-400">{{ __('app.last_attempted') }}: {{ $weakDays ?? 30 }} {{ __('app.days') }}</div>
                </div>
                <div class="mt-4 flex items-end gap-2 overflow-x-auto pb-2">
                    @php
                        $maxAcc = 100;
                    @endphp
                    @foreach($weakAccuracyTrend as $day)
                        @php
                            $height = (int) round(($day['accuracy'] / $maxAcc) * 80) + 8;
                        @endphp
                        <div class="flex w-12 flex-col items-center gap-1">
                            <div class="w-full rounded-lg bg-rose-100" @style(['height' => $height.'px'])>
                                <div class="h-full w-full rounded-lg bg-rose-400"></div>
                            </div>
                            <div class="text-[10px] text-slate-400">{{ $day['label'] }}</div>
                            <div class="text-[10px] text-slate-500">{{ $day['accuracy'] }}%</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.practice_insights') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.practice_insights_hint') }}</div>
            </div>
        </div>

        @php
            $questionTypeLabels = [
                'mcq' => __('app.question_type_mcq'),
                'tfng' => __('app.question_type_tfng'),
                'completion' => __('app.question_type_completion'),
                'matching' => __('app.question_type_matching'),
            ];
        @endphp

        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-900">{{ __('app.question_type_accuracy') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.question_type_accuracy_hint') }}</div>

                <div class="mt-4 space-y-4">
                    @forelse($questionTypeStats as $module => $rows)
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase text-slate-400">
                                {{ $module === 'reading' ? __('app.reading') : __('app.listening') }}
                            </div>
                            <div class="mt-3 space-y-3">
                                @foreach($rows as $row)
                                    @php
                                        $typeKey = $row['question_type'] ?? 'mcq';
                                        $label = $questionTypeLabels[$typeKey] ?? strtoupper((string) $typeKey);
                                        $accuracy = (int) ($row['accuracy'] ?? 0);
                                        $items = (int) ($row['items'] ?? 0);
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-slate-500">
                                            <span class="font-medium text-slate-700">{{ $label }}</span>
                                            <span>{{ $accuracy }}%</span>
                                        </div>
                                        <div class="mt-1 h-2 rounded-full bg-slate-200">
                                            <div class="h-2 rounded-full bg-emerald-500" @style(['width' => $accuracy.'%'])></div>
                                        </div>
                                        <div class="mt-1 text-[10px] text-slate-400">
                                            {{ __('app.question_type_items', ['count' => $items]) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-500">{{ __('app.no_practice_data') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-900">{{ __('app.topic_growth') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.topic_growth_hint') }}</div>

                <div class="mt-4 space-y-3">
                    @forelse($topicGrowth as $topic)
                        @php
                            $delta = (int) ($topic['delta'] ?? 0);
                            $deltaLabel = ($delta > 0 ? '+' : '').$delta.'%';
                            $deltaClass = $delta > 0
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : ($delta < 0 ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-100 text-slate-600');
                            $moduleLabel = ($topic['module'] ?? '') === 'reading' ? __('app.reading') : __('app.listening');
                            $difficulty = $topic['difficulty'] ?? '';
                        @endphp
                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $topic['title'] }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ $moduleLabel }}@if($difficulty) · {{ $difficulty }}@endif
                                    </div>
                                </div>
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $deltaClass }}">
                                    {{ $deltaLabel }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span>{{ __('app.latest_accuracy') }}: {{ $topic['latest_accuracy'] }}%</span>
                                <span>{{ __('app.previous_accuracy') }}: {{ $topic['previous_accuracy'] }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-500">{{ __('app.no_practice_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @else
        <div class="mt-10 rounded-2xl border border-rose-200 bg-rose-50/60 p-6 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-rose-700">{{ __('app.analytics_full') }}</div>
                    <div class="mt-1 text-xs text-rose-600">{{ __('app.analytics_full_hint') }}</div>
                </div>
                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-semibold text-white">PLUS</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white"
                    @click="$dispatch('open-paywall', { feature: '{{ __('app.analytics_full') }}' })"
                >
                    {{ __('app.start_plus') }}
                </button>
                <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('app.view_plans') }}
                </a>
            </div>
        </div>
    @endif

    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.continue_last_lesson') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.continue_last_lesson_hint') }}</div>
            </div>
            @if(($lessonWeaknesses ?? collect())->isNotEmpty())
                @php
                    $weakLesson = $lessonWeaknesses->first();
                @endphp
                <a href="{{ route('lessons.show', $weakLesson->id) }}" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.continue_weak_lesson') }}
                </a>
            @elseif($lastAttempt?->lesson)
                <a href="{{ route('lessons.show', $lastAttempt->lesson) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.continue') }}
                </a>
            @else
                <a href="{{ route('lessons.index') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.start_learning') }}
                </a>
            @endif
        </div>
        @if(($lessonWeaknesses ?? collect())->isNotEmpty())
            @php
                $weakLesson = $lessonWeaknesses->first();
            @endphp
            <div class="mt-4 rounded-xl border border-rose-100 bg-rose-50 p-4 text-sm text-rose-700">
                <div class="font-semibold text-rose-900">{{ __('app.weak_lesson_focus') }}</div>
                <div class="text-xs text-rose-600">{{ $weakLesson->title }} · {{ __('app.mistakes_count', ['count' => $weakLesson->wrong_answers]) }}</div>
            </div>
        @elseif($lastAttempt?->lesson)
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <div class="font-semibold text-slate-900">{{ $lastAttempt->lesson->title }}</div>
                <div class="text-xs text-slate-400">{{ __('app.last_attempted') }}: {{ $lastAttempt->completed_at }}</div>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.review_mistakes') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.review_mistakes_hint') }}</div>
            </div>
            <a href="{{ route('mistakes.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                {{ __('app.view_details') }}
            </a>
        </div>
    </div>

    <div class="mt-12 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.vocab_progress') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.reviews') }}: {{ $weeklyVocabReviews }}</div>
            </div>
            <a href="{{ route('vocabulary.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.vocabulary') }}</a>
        </div>

        @php
            $max = max(1, $vocabProgressDays->max('count'));
        @endphp
        <div class="mt-6 grid grid-cols-7 items-end gap-2">
            @foreach($vocabProgressDays as $day)
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

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">{{ __('app.grammar_progress') }}</div>
                <div class="text-xs text-slate-400">{{ __('app.grammar_attempts_week') }}: {{ $weeklyGrammarAttempts }}</div>
            </div>
            <a href="{{ route('grammar.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.grammar') }}</a>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
            <span class="inline-flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                0–39%
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                40–74%
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                75%+
            </span>
        </div>

        @php
            $maxGrammar = max(1, $grammarProgressDays->max('count'));
        @endphp
        <div class="mt-6 grid grid-cols-7 items-end gap-2">
            @foreach($grammarProgressDays as $day)
                @php
                    $height = (int) round(($day['count'] / $maxGrammar) * 80) + 8;
                    $percent = $maxGrammar > 0 ? (int) round(($day['count'] / $maxGrammar) * 100) : 0;
                    $trackClass = $percent >= 75
                        ? 'bg-emerald-100'
                        : ($percent >= 40 ? 'bg-amber-100' : 'bg-rose-100');
                    $barClass = $percent >= 75
                        ? 'bg-emerald-500'
                        : ($percent >= 40 ? 'bg-amber-400' : 'bg-rose-400');
                @endphp
                <div class="flex flex-col items-center gap-2">
                    <div class="w-full rounded-lg {{ $trackClass }}" @style(['height' => $height.'px'])>
                        <div class="h-full w-full rounded-lg {{ $barClass }}"></div>
                    </div>
                    <div class="text-[10px] text-slate-400">{{ $day['label'] }}</div>
                    <div class="text-[10px] text-slate-500">{{ $percent }}%</div>
                </div>
            @endforeach
        </div>
    </div>

    @if($canAnalyticsFull)
    <x-modal name="weak-lessons" :show="false" maxWidth="2xl">
        <div class="px-6 py-5">
            <div class="text-sm font-semibold text-slate-900">{{ __('app.practice_now') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.lesson_weaknesses_hint') }}</div>
        </div>
        <div class="max-h-[60vh] space-y-3 overflow-y-auto px-6 pb-6">
            @foreach($lessonWeaknesses as $lesson)
                <a href="{{ route('lessons.show', $lesson->id) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 hover:border-slate-300 hover:bg-white">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $lesson->title }}</div>
                            <div class="text-xs text-slate-400">
                                {{ $lesson->type === 'reading' ? __('app.reading') : __('app.listening') }}
                                · {{ $lesson->difficulty }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                                {{ __('app.mistakes_count', ['count' => $lesson->wrong_answers]) }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ __('app.accuracy') }} {{ $lesson->accuracy }}%
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" x-data x-on:click="$dispatch('close-modal', 'weak-lessons')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600">
                    {{ __('app.close') }}
                </button>
            </div>
        </div>
    </x-modal>
    @endif
</x-layouts.app>
