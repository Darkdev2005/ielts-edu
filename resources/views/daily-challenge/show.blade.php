<x-layouts.app :title="__('app.daily_challenge')">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.daily_challenge') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.daily_challenge_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.daily_challenge_hint') }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-900 px-5 py-4 text-sm text-white">
                <div class="text-white/70">{{ __('app.streak') }}</div>
                <div class="text-2xl font-semibold">{{ $streak }}</div>
            </div>
            @if($practiceMode)
                <div class="rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-sm text-slate-600 shadow-sm">
                    <div class="text-xs uppercase text-slate-400">{{ __('app.practice_mode') }}</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $practiceScore ?? 0 }} / {{ $practiceTotal ?? $questions->count() }}</div>
                    <div class="mt-1 text-xs text-slate-400">{{ __('app.practice_mode') }}</div>
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-sm text-slate-600 shadow-sm">
                    <div class="text-xs uppercase text-slate-400">{{ __('app.score') }}</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $challenge->score }} / {{ $challenge->total }}</div>
                    <div class="mt-1 text-xs text-slate-400">
                        {{ $challenge->completed_at ? __('app.daily_challenge_completed') : __('app.daily_challenge_not_completed') }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(!$practiceMode)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">{{ __('app.today') }}</div>
                <div class="text-xs text-slate-500">{{ $challenge->challenge_date->format('Y-m-d') }}</div>
            </div>
            <div class="mt-2 text-xs text-slate-400">
                {{ __('app.challenge_items', ['count' => $challenge->total]) }}
            </div>
        </div>
    @endif

    @if($practiceMode)
        <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 text-blue-800">
            <div class="text-sm font-semibold">{{ __('app.practice_mode') }}</div>
            <div class="mt-2 text-xs">
                {{ __('app.score') }}: {{ $practiceScore ?? 0 }} / {{ $practiceTotal ?? $questions->count() }}
                <a href="{{ route('daily-challenge.show') }}" class="ml-2 text-blue-700 underline">{{ __('app.back_to_challenge') }}</a>
            </div>
        </div>
    @endif

    @if($challenge->completed_at && !$practiceMode)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">{{ __('app.practice_mistakes') }}</div>
            <p class="mt-2 text-xs text-slate-500">{{ __('app.practice_mistakes_help') }}</p>
            @if($mistakeCount > 0)
                <form method="POST" action="{{ route('daily-challenge.practice') }}" class="mt-3">
                    @csrf
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                        {{ __('app.practice_mistakes') }} ({{ $mistakeCount }})
                    </button>
                </form>
            @else
                <div class="mt-3 text-xs text-slate-500">{{ __('app.practice_no_mistakes') }}</div>
            @endif
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">{{ __('app.daily_challenge_leaderboard_today') }}</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($todayLeaderboard as $entry)
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $entry->user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $entry->score }} / {{ $entry->total }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">{{ __('app.no_leaderboard_yet') }}</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">{{ __('app.daily_challenge_leaderboard_streak') }}</div>
            <div class="mt-4 space-y-3 text-sm text-slate-600">
                @forelse($streakLeaders as $entry)
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $entry['user']->name }}</div>
                        <div class="text-xs text-slate-500">{{ $entry['streak'] }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">{{ __('app.no_leaderboard_yet') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @if(auth()->user()->is_admin)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-sm font-semibold text-slate-900">{{ __('app.daily_challenge_reset') }}</div>
            <p class="mt-2 text-xs text-slate-500">{{ __('app.daily_challenge_reset_help') }}</p>
            <form method="POST" action="{{ route('admin.daily-challenge.reset') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                @csrf
                <input
                    type="date"
                    name="date"
                    value="{{ $challenge->challenge_date->format('Y-m-d') }}"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm sm:w-auto"
                >
                <input
                    type="number"
                    name="user_id"
                    placeholder="{{ __('app.daily_challenge_reset_user') }}"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm sm:w-48"
                >
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.reset') }}
                </button>
            </form>
        </div>
    @endif

    @if($challenge->completed_at && !$practiceMode)
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800">
            {{ __('app.daily_challenge_completed') }} · {{ __('app.score') }}: {{ $challenge->score }} / {{ $challenge->total }}
        </div>
    @endif

    @php
        $formAction = $practiceMode ? route('daily-challenge.practice.submit') : route('daily-challenge.submit', $challenge);
        $inputsDisabled = $practiceMode ? (bool) $practiceCompletedAt : (bool) $challenge->completed_at;
        $showResults = $practiceMode ? (bool) $practiceCompletedAt : (bool) $challenge->completed_at;
    @endphp

    <form method="POST" action="{{ $formAction }}" class="mt-6 space-y-6">
        @csrf
        @php
            $answersByQuestion = $answers->whereNotNull('question_id')->keyBy('question_id');
            $grammarAnswer = $answers->firstWhere('grammar_exercise_id');
        @endphp

        @foreach($questions as $index => $question)
            @php
                $answer = $answersByQuestion->get($question->id);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-400">Q{{ $index + 1 }}</div>
                    <div class="text-xs text-slate-400">{{ __('app.multiple_choice') }}</div>
                </div>
                <div class="mb-4 text-lg font-semibold text-slate-900">{{ $question->prompt }}</div>
                <div class="grid gap-3">
                    @foreach($question->options as $i => $option)
                        @php
                            $label = chr(65 + $i);
                        @endphp
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                            <input
                                type="radio"
                                name="answers[{{ $question->id }}]"
                                value="{{ $label }}"
                                class="peer sr-only"
                                @checked($answer && $answer->selected_answer === $label)
                                @disabled($inputsDisabled)
                            >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                {{ $label }}
                            </span>
                            <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @if($answer && $showResults)
                    <div class="mt-3 text-xs text-slate-500">
                        {{ __('app.correct') }}: {{ $question->correct_answer }}
                        · {{ $answer->is_correct ? __('app.correct') : __('app.incorrect') }}
                    </div>
                @endif
                @php
                    $help = $helpByQuestion->get($question->id);
                @endphp
                <div
                    class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    data-ai-help
                    data-create-url="{{ route('questions.ai-help.store', $question) }}"
                    data-status-base="{{ url('/ai-help') }}"
                    data-help-id="{{ $help?->id }}"
                    data-status="{{ $help?->status ?? 'idle' }}"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs font-semibold uppercase text-slate-500">{{ __('app.ask_ai_title') }}</div>
                        <button type="button" data-ai-help-submit class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                            <span data-ai-help-spinner class="hidden h-3 w-3 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                            <span data-ai-help-button-text>{{ __('app.ask_ai') }}</span>
                        </button>
                    </div>
                    <textarea
                        data-ai-help-input
                        rows="2"
                        class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                        placeholder="{{ __('app.ask_ai_placeholder') }}"
                    >{{ $help?->user_prompt }}</textarea>
                    <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" data-ai-help-response>
                        @if($help && $help->status === 'done')
                            {{ $help->ai_response }}
                        @elseif($help && $help->status === 'failed')
                            {{ __('app.ai_help_failed') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-slate-400" data-ai-help-status>
                        @if($help && in_array($help->status, ['queued', 'processing'], true))
                            {{ __('app.ai_help_pending') }}
                        @elseif($help && $help->status === 'failed')
                            {{ $help->error_message }}
                        @elseif($help && $help->status === 'done')
                            {{ __('app.ai_help_done') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        @if($grammarExercise)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-400">{{ __('app.grammar') }}</div>
                    <div class="text-xs text-slate-400">{{ __('app.multiple_choice') }}</div>
                </div>
                <div class="mb-4 text-lg font-semibold text-slate-900">{{ $grammarExercise->question ?? $grammarExercise->prompt }}</div>
                <div class="grid gap-3">
                    @php
                        $options = (array) ($grammarExercise->options ?? []);
                        $isIndexed = array_keys($options) === range(0, count($options) - 1);
                        if ($isIndexed) {
                            $options = [
                                'A' => $options[0] ?? '',
                                'B' => $options[1] ?? '',
                                'C' => $options[2] ?? '',
                                'D' => $options[3] ?? '',
                            ];
                        }
                    @endphp
                    @foreach($options as $label => $option)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                            <input
                                type="radio"
                                name="grammar_answer"
                                value="{{ $label }}"
                                class="peer sr-only"
                                @checked($grammarAnswer && $grammarAnswer->selected_answer === $label)
                                @disabled($inputsDisabled)
                            >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                {{ $label }}
                            </span>
                            <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @if($grammarAnswer && $showResults)
                    <div class="mt-3 text-xs text-slate-500">
                        {{ __('app.correct') }}: {{ $grammarExercise->correct_answer }}
                        · {{ $grammarAnswer->is_correct ? __('app.correct') : __('app.incorrect') }}
                    </div>
                @endif
                @php
                    $grammarHelp = $helpByGrammarExercise->get($grammarExercise->id);
                @endphp
                <div
                    class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    data-ai-help
                    data-create-url="{{ route('grammar.exercises.ai-help.store', $grammarExercise) }}"
                    data-status-base="{{ url('/grammar/ai-help') }}"
                    data-help-id="{{ $grammarHelp?->id }}"
                    data-status="{{ $grammarHelp?->status ?? 'idle' }}"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs font-semibold uppercase text-slate-500">{{ __('app.ask_ai_title') }}</div>
                        <button type="button" data-ai-help-submit class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                            <span data-ai-help-spinner class="hidden h-3 w-3 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                            <span data-ai-help-button-text>{{ __('app.ask_ai') }}</span>
                        </button>
                    </div>
                    <textarea
                        data-ai-help-input
                        rows="2"
                        class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                        placeholder="{{ __('app.ask_ai_placeholder') }}"
                    >{{ $grammarHelp?->user_prompt }}</textarea>
                    <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" data-ai-help-response>
                        @if($grammarHelp && $grammarHelp->status === 'done')
                            {{ $grammarHelp->ai_response }}
                        @elseif($grammarHelp && $grammarHelp->status === 'failed')
                            {{ __('app.ai_help_failed') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-slate-400" data-ai-help-status>
                        @if($grammarHelp && in_array($grammarHelp->status, ['queued', 'processing'], true))
                            {{ __('app.ai_help_pending') }}
                        @elseif($grammarHelp && $grammarHelp->status === 'failed')
                            {{ $grammarHelp->error_message }}
                        @elseif($grammarHelp && $grammarHelp->status === 'done')
                            {{ __('app.ai_help_done') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if(!$inputsDisabled)
            <button class="rounded-xl bg-slate-900 px-6 py-3 text-white shadow-lg">
                {{ __('app.submit_answers') }}
            </button>
        @endif
    </form>

    <div
        data-ai-texts
        data-pending="{{ __('app.ai_help_pending') }}"
        data-failed="{{ __('app.ai_help_failed') }}"
        data-done="{{ __('app.ai_help_done') }}"
        data-empty="{{ __('app.ask_ai_hint') }}"
        data-ask="{{ __('app.ask_ai') }}"
        data-daily-limit="{{ __('app.daily_limit_reached') }}"
    ></div>

    <script>
        (() => {
            const textSource = document.querySelector('[data-ai-texts]');
            const texts = {
                pending: textSource?.dataset?.pending || 'AI is preparing an answer...',
                failed: textSource?.dataset?.failed || 'AI could not respond.',
                done: textSource?.dataset?.done || 'AI response',
                empty: textSource?.dataset?.empty || 'Ask why an option is correct.',
                ask: textSource?.dataset?.ask || 'Ask AI',
                dailyLimit: textSource?.dataset?.dailyLimit || '',
            };

            const setStatus = (container, state, message) => {
                const statusEl = container.querySelector('[data-ai-help-status]');
                if (!statusEl) return;
                statusEl.textContent = message || '';
                statusEl.classList.remove('text-rose-600', 'text-emerald-600', 'text-slate-400');
                if (state === 'failed') {
                    statusEl.classList.add('text-rose-600');
                } else if (state === 'done') {
                    statusEl.classList.add('text-emerald-600');
                } else {
                    statusEl.classList.add('text-slate-400');
                }
            };

            const setLoading = (container, isLoading) => {
                const button = container.querySelector('[data-ai-help-submit]');
                const spinner = container.querySelector('[data-ai-help-spinner]');
                const buttonText = container.querySelector('[data-ai-help-button-text]');
                if (!button || !spinner || !buttonText) return;

                if (isLoading) {
                    container.dataset.pending = '1';
                    button.disabled = true;
                    button.classList.add('opacity-60', 'cursor-not-allowed');
                    spinner.classList.remove('hidden');
                    buttonText.textContent = texts.pending;
                } else {
                    container.dataset.pending = '0';
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                    spinner.classList.add('hidden');
                    buttonText.textContent = texts.ask;
                }
            };

            const startPolling = (container) => {
                if (container._aiPoll) return;

                const poll = async () => {
                    const helpId = container.dataset.helpId;
                    if (!helpId || !['queued', 'processing'].includes(container.dataset.status)) return;

                    try {
                        const statusUrl = `${container.dataset.statusBase}/${helpId}`;
                        const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const payload = await response.json();
                        updateFromStatus(container, payload);
                    } catch (e) {
                        // Ignore transient errors.
                    }
                };

                container._aiPoll = setInterval(poll, 3000);
                poll();
            };

            const updateFromStatus = (container, payload) => {
                if (!payload || !payload.status) return;

                const responseEl = container.querySelector('[data-ai-help-response]');
                container.dataset.status = payload.status;

                if (payload.status === 'done') {
                    responseEl.textContent = payload.ai_response || '';
                    const limitNotice = payload.limit_notice || container.dataset.limitNotice;
                    const doneMessage = limitNotice ? `${texts.done} - ${limitNotice}` : texts.done;
                    setStatus(container, 'done', doneMessage);
                    setLoading(container, false);
                    if (container._aiPoll) {
                        clearInterval(container._aiPoll);
                        container._aiPoll = null;
                    }
                    return;
                }

                if (payload.status === 'failed') {
                    responseEl.textContent = texts.failed;
                    setStatus(container, 'failed', payload.error_message || texts.failed);
                    setLoading(container, false);
                    if (container._aiPoll) {
                        clearInterval(container._aiPoll);
                        container._aiPoll = null;
                    }
                    return;
                }

                setStatus(container, 'pending', texts.pending);
                setLoading(container, true);
            };

            const containers = Array.from(document.querySelectorAll('[data-ai-help]'));

            const handleErrorResponse = async (response, responseEl, container) => {
                let payload = null;
                try {
                    payload = await response.json();
                } catch (e) {
                    payload = null;
                }

                const message = payload?.message || texts.failed;
                responseEl.textContent = message;
                setStatus(container, 'failed', message);
                setLoading(container, false);

                if (payload?.upgrade_prompt || (texts.dailyLimit && message === texts.dailyLimit)) {
                    window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: texts.ask || 'AI' } }));
                }
            };

            containers.forEach((container) => {
                const button = container.querySelector('[data-ai-help-submit]');
                const input = container.querySelector('[data-ai-help-input]');

                const submit = async () => {
                    if (container.dataset.pending === '1') {
                        return;
                    }

                    const createUrl = container.dataset.createUrl;
                    const responseEl = container.querySelector('[data-ai-help-response]');
                    const message = (input.value || '').trim();

                    if (!message) {
                        setStatus(container, 'empty', texts.empty);
                        return;
                    }

                    setLoading(container, true);
                    responseEl.textContent = texts.pending;
                    setStatus(container, 'pending', texts.pending);

                    try {
                        const response = await fetch(createUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            },
                            body: JSON.stringify({ prompt: message }),
                        });

                        if (!response.ok) {
                            throw new Error('Request failed');
                        }

                        const payload = await response.json();
                        container.dataset.helpId = payload.id;
                        container.dataset.status = payload.status;
                        startPolling(container);
                    } catch (e) {
                        responseEl.textContent = texts.failed;
                        setStatus(container, 'failed', texts.failed);
                        setLoading(container, false);
                    }
                };

                button.addEventListener('click', submit);
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        submit();
                    }
                });

                if (['queued', 'processing'].includes(container.dataset.status)) {
                    setLoading(container, true);
                    startPolling(container);
                }
            });
        })();
    </script>
</x-layouts.app>
