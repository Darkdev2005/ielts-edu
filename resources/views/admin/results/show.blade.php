<x-layouts.app :title="$user->name">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $user->name }}</h1>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
        </div>
        <a href="{{ route('admin.results.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_results') }}</a>
    </div>

    <form method="GET" action="{{ route('admin.results.show', $user) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="text-xs text-slate-500">{{ __('app.lesson_type') }}</label>
                <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="reading" @selected(request('type') === 'reading')>{{ __('app.reading') }}</option>
                    <option value="listening" @selected(request('type') === 'listening')>{{ __('app.listening') }}</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">{{ __('app.from') }}</label>
                <input type="date" name="from" value="{{ request('from') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
            </div>
            <div>
                <label class="text-xs text-slate-500">{{ __('app.to') }}</label>
                <input type="date" name="to" value="{{ request('to') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.filter') }}</button>
            <a href="{{ route('admin.results.show', $user) }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.reset') }}</a>
        </div>
    </form>

    <div class="mt-8 grid gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.total_attempts') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats->total_attempts ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.average_score') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">
                {{ $stats->avg_score ? round($stats->avg_score * 100) : 0 }}%
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm text-slate-500">{{ __('app.total_questions') }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats->total_questions ?? 0 }}</div>
        </div>
    </div>

    <div class="mt-10 space-y-4">
        @forelse($attempts as $attempt)
            <a href="{{ route('admin.results.attempt', ['user' => $user->id, 'attempt' => $attempt->id]) }}" class="block rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold text-slate-900">{{ $attempt->lesson->title }}</div>
                        <div class="text-xs text-slate-400">{{ $attempt->completed_at }}</div>
                    </div>
                    <div class="rounded-full bg-slate-900 px-3 py-1 text-xs text-white">
                        {{ $attempt->score }} / {{ $attempt->total }}
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-400">{{ __('app.view_details') }} -></div>
            </a>
        @empty
            <p class="text-slate-500">{{ __('app.no_attempts') }}</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $attempts->links() }}</div>

    <div class="mt-12">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.grammar_attempts') }}</h2>
            <span class="text-xs text-slate-400">{{ $grammarAttempts->total() }}</span>
        </div>
        <div class="mt-4 space-y-4">
            @forelse($grammarAttempts as $grammarAttempt)
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $grammarAttempt->topic?->title ?? __('app.grammar') }}</div>
                            <div class="text-xs text-slate-400">{{ $grammarAttempt->completed_at }}</div>
                        </div>
                        <div class="rounded-full bg-slate-900 px-3 py-1 text-xs text-white">
                            {{ $grammarAttempt->score }} / {{ $grammarAttempt->total }}
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-slate-500">{{ __('app.no_attempts') }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $grammarAttempts->links() }}</div>
    </div>

    <div class="mt-12">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.writing_result') }}</h2>
            <span class="text-xs text-slate-400">{{ $writingSubmissions->total() }}</span>
        </div>
        <div class="mt-4 space-y-4">
            @forelse($writingSubmissions as $submission)
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $submission->task?->title ?? __('app.writing') }}</div>
                            <div class="text-xs text-slate-400">
                                {{ $submission->submitted_at?->format('Y-m-d H:i') ?? $submission->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="rounded-full bg-slate-900 px-3 py-1 text-white">
                                {{ $submission->band_score !== null ? number_format($submission->band_score, 1) : '-' }}
                            </span>
                            <span class="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                {{ strtoupper($submission->status ?? 'queued') }}
                            </span>
                        </div>
                    </div>
                    @if($submission->task?->prompt)
                        <div class="mt-3 text-xs text-slate-500">
                            {{ \Illuminate\Support\Str::limit($submission->task->prompt, 140) }}
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-slate-500">{{ __('app.no_attempts') }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $writingSubmissions->links() }}</div>
    </div>

    <div class="mt-12">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.speaking') }}</h2>
            <span class="text-xs text-slate-400">{{ $speakingSubmissions->total() }}</span>
        </div>
        <div class="mt-4 space-y-4">
            @forelse($speakingSubmissions as $submission)
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-semibold text-slate-900">
                                {{ $submission->prompt?->prompt ? \Illuminate\Support\Str::limit($submission->prompt->prompt, 140) : __('app.speaking') }}
                            </div>
                            <div class="text-xs text-slate-400">
                                {{ $submission->created_at?->format('Y-m-d H:i') }}
                                @if($submission->prompt?->part)
                                    · Part {{ $submission->prompt->part }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="rounded-full bg-slate-900 px-3 py-1 text-white">
                                {{ $submission->band_score !== null ? number_format($submission->band_score, 1) : '-' }}
                            </span>
                            <span class="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                {{ strtoupper($submission->status ?? 'queued') }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-slate-500">{{ __('app.no_attempts') }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $speakingSubmissions->links() }}</div>
    </div>
</x-layouts.app>

