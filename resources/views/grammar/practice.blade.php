<x-layouts.app :title="$topic->title . ' · ' . __('app.practice')">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ __('app.grammar') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $topic->title }}</h1>
            @if($topic->description)
                <p class="mt-2 text-sm text-slate-500">{{ $topic->description }}</p>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
                <div class="text-white/70">{{ __('app.questions_label') }}</div>
                <div class="text-2xl font-semibold">{{ $topic->exercises->count() }}</div>
            </div>
            @if($topic->cefr_level)
                <div class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 text-sm text-slate-600 shadow-sm">
                    <div class="text-xs uppercase text-slate-400">{{ __('app.cefr_level') }}</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $topic->cefr_level }}</div>
                </div>
            @endif
        </div>
    </div>

    @if($topic->exercises->isEmpty())
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 text-sm text-slate-600 shadow-sm">
            {{ __('app.no_exercises') }}
        </div>
        <a href="{{ route('grammar.show', $topic) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('app.review_rules') }}
        </a>
    @else
        @if($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <div class="font-semibold">{{ __('app.fix_following') }}</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('grammar.attempts.store', $topic) }}" class="mt-8 space-y-6" data-grammar-stepper>
            @csrf
            <div class="space-y-3 hidden" data-stepper-ui>
                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-slate-400">
                    <span>{{ __('app.question_progress_label') }}</span>
                    <span class="text-sm font-semibold text-slate-700 normal-case" data-step-progress></span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-slate-900 transition-all" data-step-progress-bar style="width: 0%;"></div>
                </div>
            </div>

            @foreach($topic->exercises as $index => $exercise)
                @php
                    $help = $helpByExercise->get($exercise->id);
                    $type = strtolower($exercise->exercise_type ?? $exercise->type ?? 'mcq');
                    $typeLabel = match ($type) {
                        'gap' => __('app.exercise_type_gap'),
                        'tf' => __('app.exercise_type_tf'),
                        'reorder' => __('app.exercise_type_reorder'),
                        default => __('app.multiple_choice'),
                    };
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm" data-question-step data-step-index="{{ $index }}">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-400">Q{{ $index + 1 }}</div>
                        <div class="text-xs text-slate-400">{{ $typeLabel }}</div>
                    </div>
                    <div class="mb-4 text-lg font-semibold text-slate-900">{{ $exercise->question ?? $exercise->prompt }}</div>
                    @if($type === 'mcq')
                        @php
                            $options = (array) ($exercise->options ?? []);
                            $isIndexed = array_keys($options) === range(0, count($options) - 1);
                            if ($isIndexed) {
                                $options = [
                                    'A' => $options[0] ?? '',
                                    'B' => $options[1] ?? '',
                                    'C' => $options[2] ?? '',
                                    'D' => $options[3] ?? '',
                                ];
                            }
                            $options = array_filter($options, static fn ($value) => trim((string) $value) !== '');
                        @endphp
                        <div class="grid gap-3">
                            @foreach($options as $label => $option)
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                    <input
                                        type="radio"
                                        name="answers[{{ $exercise->id }}]"
                                        value="{{ $label }}"
                                        class="peer sr-only"
                                        @checked(old('answers.'.$exercise->id) === $label)
                                        @if($loop->first) required @endif
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                        {{ $label }}
                                    </span>
                                    <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif($type === 'tf')
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach(['true' => __('app.true_label'), 'false' => __('app.false_label')] as $value => $label)
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                    <input
                                        type="radio"
                                        name="answers[{{ $exercise->id }}]"
                                        value="{{ $value }}"
                                        class="peer sr-only"
                                        @checked(old('answers.'.$exercise->id) === $value)
                                        @if($loop->first) required @endif
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                        {{ strtoupper(substr($value, 0, 1)) }}
                                    </span>
                                    <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif($type === 'reorder')
                        <textarea
                            name="answers[{{ $exercise->id }}]"
                            rows="2"
                            required
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                            placeholder="{{ __('app.reorder_placeholder') }}"
                        >{{ old('answers.'.$exercise->id) }}</textarea>
                    @else
                        <input
                            type="text"
                            name="answers[{{ $exercise->id }}]"
                            required
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                            placeholder="{{ __('app.gap_placeholder') }}"
                            value="{{ old('answers.'.$exercise->id) }}"
                        >
                    @endif

                    <div class="mt-5" data-stepper-controls-slot></div>

                    <div
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"
                        data-grammar-ai-help
                        data-exercise-id="{{ $exercise->id }}"
                        data-create-url="{{ route('grammar.exercises.ai-help.store', $exercise) }}"
                        data-status-base="{{ url('/grammar/ai-help') }}"
                        data-help-id="{{ $help?->id }}"
                        data-status="{{ $help?->status ?? 'idle' }}"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-xs font-semibold uppercase text-slate-500">{{ __('app.ask_ai_title') }}</div>
                            <button type="button" data-grammar-ai-help-submit class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                <span data-grammar-ai-help-spinner class="hidden h-3 w-3 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                                <span data-grammar-ai-help-button-text>{{ __('app.ask_ai') }}</span>
                            </button>
                        </div>
                        <textarea
                            data-grammar-ai-help-input
                            rows="2"
                            class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                            placeholder="{{ __('app.ask_ai_placeholder') }}"
                        >{{ $help?->user_prompt }}</textarea>
                        <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" data-grammar-ai-help-response>
                            @if($help && $help->status === 'done')
                                {{ $help->ai_response }}
                            @elseif($help && $help->status === 'failed')
                                {{ __('app.ai_help_failed') }}
                            @else
                                {{ __('app.ask_ai_hint') }}
                            @endif
                        </div>
                        <div class="mt-2 text-xs text-slate-400" data-grammar-ai-help-status>
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

            <div class="flex flex-wrap items-center justify-between gap-3 hidden" data-stepper-ui data-stepper-controls>
                <button type="button" class="rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" data-step-prev>
                    {{ __('app.previous_question') }}
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" class="rounded-xl border border-slate-900 px-5 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50" data-step-next>
                        {{ __('app.next_question') }}
                    </button>
                    <button class="rounded-xl bg-slate-900 px-6 py-3 text-white shadow-lg" data-step-submit>
                        {{ __('app.submit_answers') }}
                    </button>
                </div>
            </div>
            <button class="rounded-xl bg-slate-900 px-6 py-3 text-white shadow-lg" data-stepper-fallback>
                {{ __('app.submit_answers') }}
            </button>
        </form>
    @endif

    <script type="application/json" id="grammar-i18n">
        {!! json_encode([
            'ai_help_pending' => __('app.ai_help_pending'),
            'ai_help_failed' => __('app.ai_help_failed'),
            'ai_help_done' => __('app.ai_help_done'),
            'ask_ai_hint' => __('app.ask_ai_hint'),
            'select_option_required' => __('app.select_option_required'),
            'ask_ai' => __('app.ask_ai'),
            'daily_limit_reached' => __('app.daily_limit_reached'),
            'question_progress' => __('app.question_progress', ['current' => ':current', 'total' => ':total']),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    <div
        data-grammar-ai-texts
        data-pending="{{ __('app.ai_help_pending') }}"
        data-failed="{{ __('app.ai_help_failed') }}"
        data-done="{{ __('app.ai_help_done') }}"
        data-empty="{{ __('app.ask_ai_hint') }}"
        data-ask="{{ __('app.ask_ai') }}"
        data-daily-limit="{{ __('app.daily_limit_reached') }}"
    ></div>

    <script>
        (() => {
            const textSource = document.querySelector('[data-grammar-ai-texts]');
            const texts = {
                pending: textSource?.dataset?.pending || 'AI is preparing an answer...',
                failed: textSource?.dataset?.failed || 'AI could not respond.',
                done: textSource?.dataset?.done || 'AI response',
                empty: textSource?.dataset?.empty || 'Ask why an option is correct.',
                ask: textSource?.dataset?.ask || 'Ask AI',
            };

            const renderAiHelpResponse = (container, raw) => {
                const el = container.querySelector('[data-grammar-ai-help-response]');
                if (!el) return;
                const text = (raw || '').trim();
                el.innerHTML = '';
                if (!text) return;

                const lines = text.split(/\r\n|\r|\n/).map((line) => line.trim()).filter(Boolean);
                const hasStructure = lines.some((line) => line.startsWith('-') || /^tip:/i.test(line));
                if (!hasStructure) {
                    el.textContent = text;
                    return;
                }

                const title = lines.shift() || '';
                const bullets = [];
                let tip = null;

                lines.forEach((line) => {
                    if (/^tip:/i.test(line)) {
                        tip = line.replace(/^tip:/i, '').trim();
                        return;
                    }
                    if (line.startsWith('-')) {
                        bullets.push(line.replace(/^-+\s*/, '').trim());
                        return;
                    }
                    bullets.push(line);
                });

                if (title) {
                    const titleEl = document.createElement('div');
                    titleEl.className = 'mt-1 text-sm font-semibold text-slate-800';
                    titleEl.textContent = title;
                    el.appendChild(titleEl);
                }

                if (bullets.length) {
                    const list = document.createElement('ul');
                    list.className = 'mt-2 list-disc pl-4 text-sm text-slate-700';
                    bullets.forEach((bullet) => {
                        const item = document.createElement('li');
                        item.textContent = bullet;
                        list.appendChild(item);
                    });
                    el.appendChild(list);
                }

                if (tip !== null) {
                    const tipEl = document.createElement('div');
                    tipEl.className = 'mt-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-700';
                    const label = document.createElement('span');
                    label.className = 'font-semibold';
                    label.textContent = 'Tip:';
                    tipEl.appendChild(label);
                    tipEl.appendChild(document.createTextNode(` ${tip}`));
                    el.appendChild(tipEl);
                }
            };

            const setStatus = (container, state, message) => {
                const statusEl = container.querySelector('[data-grammar-ai-help-status]');
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
                const button = container.querySelector('[data-grammar-ai-help-submit]');
                const spinner = container.querySelector('[data-grammar-ai-help-spinner]');
                const buttonText = container.querySelector('[data-grammar-ai-help-button-text]');
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

                container.dataset.status = payload.status;

                if (payload.status === 'done') {
                    renderAiHelpResponse(container, payload.ai_response || '');
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

            const containers = Array.from(document.querySelectorAll('[data-grammar-ai-help]'));

            const dailyLimitMessage = textSource?.dataset?.dailyLimit || '';

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

                if (payload?.upgrade_prompt || (dailyLimitMessage && message === dailyLimitMessage)) {
                    window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: texts.ask || 'AI' } }));
                }
            };

            containers.forEach((container) => {
                const button = container.querySelector('[data-grammar-ai-help-submit]');
                const input = container.querySelector('[data-grammar-ai-help-input]');
                const responseEl = container.querySelector('[data-grammar-ai-help-response]');

                const submit = async () => {
                    if (container.dataset.pending === '1') {
                        return;
                    }

                    const createUrl = container.dataset.createUrl;
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
                            await handleErrorResponse(response, responseEl, container);
                            return;
                        }

                        const payload = await response.json();
                        container.dataset.helpId = payload.id;
                        container.dataset.status = payload.status;
                        if (payload.limit_notice) {
                            container.dataset.limitNotice = payload.limit_notice;
                        }
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

                if (container.dataset.status === 'done' && responseEl) {
                    renderAiHelpResponse(container, responseEl.textContent);
                }

                if (['queued', 'processing'].includes(container.dataset.status)) {
                    setLoading(container, true);
                    startPolling(container);
                }
            });

            const i18nEl = document.getElementById('grammar-i18n');
            const i18n = i18nEl ? JSON.parse(i18nEl.textContent) : {};
            const requiredMessage = i18n.select_option_required || '';
            const progressTemplate = i18n.question_progress || 'Question :current of :total';

            const answerInputs = Array.from(document.querySelectorAll('[name^="answers["]'));
            answerInputs.forEach((input) => {
                input.addEventListener('invalid', (event) => {
                    if (requiredMessage) {
                        event.target.setCustomValidity(requiredMessage);
                    }
                });

                if (input.type === 'radio') {
                    input.addEventListener('change', (event) => {
                        const name = event.target.name;
                        answerInputs
                            .filter((item) => item.name === name)
                            .forEach((item) => item.setCustomValidity(''));
                    });
                } else {
                    input.addEventListener('input', (event) => {
                        event.target.setCustomValidity('');
                    });
                }
            });

            const stepperForm = document.querySelector('[data-grammar-stepper]');
            if (!stepperForm) {
                return;
            }

            const steps = Array.from(stepperForm.querySelectorAll('[data-question-step]'));
            const stepperUi = Array.from(stepperForm.querySelectorAll('[data-stepper-ui]'));
            const fallbackSubmit = stepperForm.querySelector('[data-stepper-fallback]');
            const controls = stepperForm.querySelector('[data-stepper-controls]');
            const controlSlots = Array.from(stepperForm.querySelectorAll('[data-stepper-controls-slot]'));
            const prevButton = stepperForm.querySelector('[data-step-prev]');
            const nextButton = stepperForm.querySelector('[data-step-next]');
            const submitButton = stepperForm.querySelector('[data-step-submit]');
            const progressEl = stepperForm.querySelector('[data-step-progress]');
            const progressBar = stepperForm.querySelector('[data-step-progress-bar]');

            if (!steps.length) {
                if (prevButton) prevButton.classList.add('hidden');
                if (nextButton) nextButton.classList.add('hidden');
                return;
            }

            stepperUi.forEach((el) => el.classList.remove('hidden'));
            if (fallbackSubmit) {
                fallbackSubmit.classList.add('hidden');
            }

            const inputsForStep = (step) =>
                Array.from(step.querySelectorAll('[name^="answers["]'));

            const isAnswered = (step) => {
                const inputs = inputsForStep(step);
                if (!inputs.length) {
                    return true;
                }

                const first = inputs[0];
                if (first.type === 'radio' || first.type === 'checkbox') {
                    return inputs.some((input) => input.checked);
                }

                return inputs.some((input) => (input.value || '').trim() !== '');
            };

            const formatProgress = (current, total) =>
                progressTemplate
                    .replace(':current', String(current))
                    .replace(':total', String(total));

            const updateProgress = (index) => {
                const total = steps.length;
                const current = Math.min(index + 1, total);
                if (progressEl) {
                    progressEl.textContent = formatProgress(current, total);
                }
                if (progressBar) {
                    progressBar.style.width = `${Math.round((current / total) * 100)}%`;
                }
            };

            const showStep = (index) => {
                steps.forEach((step, stepIndex) => {
                    step.classList.toggle('hidden', stepIndex !== index);
                });

                if (controls && controlSlots[index]) {
                    controlSlots[index].appendChild(controls);
                }

                if (prevButton) {
                    prevButton.classList.toggle('hidden', index === 0);
                }

                if (nextButton) {
                    nextButton.classList.toggle('hidden', index >= steps.length - 1);
                }

                if (submitButton) {
                    submitButton.classList.toggle('hidden', index < steps.length - 1);
                }

                updateProgress(index);
            };

            const firstIncompleteIndex = () => {
                for (let i = 0; i < steps.length; i += 1) {
                    if (!isAnswered(steps[i])) {
                        return i;
                    }
                }
                return -1;
            };

            let currentIndex = firstIncompleteIndex();
            if (currentIndex === -1) {
                currentIndex = steps.length - 1;
            }
            showStep(currentIndex);

            const requireAnswerForStep = (step) => {
                if (isAnswered(step)) {
                    return true;
                }

                const inputs = inputsForStep(step);
                const target = inputs[0];
                if (!target) {
                    return true;
                }

                if (requiredMessage) {
                    target.setCustomValidity(requiredMessage);
                }
                target.reportValidity();
                return false;
            };

            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    currentIndex = Math.max(0, currentIndex - 1);
                    showStep(currentIndex);
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    if (!requireAnswerForStep(steps[currentIndex])) {
                        return;
                    }
                    currentIndex = Math.min(steps.length - 1, currentIndex + 1);
                    showStep(currentIndex);
                });
            }

            stepperForm.addEventListener('submit', (event) => {
                if (stepperForm.checkValidity()) {
                    return;
                }

                event.preventDefault();
                const firstIncomplete = firstIncompleteIndex();
                if (firstIncomplete !== -1) {
                    currentIndex = firstIncomplete;
                    showStep(currentIndex);
                    requireAnswerForStep(steps[currentIndex]);
                }
            });
        })();
    </script>
</x-layouts.app>
