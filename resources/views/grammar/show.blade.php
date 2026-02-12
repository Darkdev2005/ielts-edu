<x-layouts.app :title="$topic->title">
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-8 shadow-sm">
        <div class="absolute -right-12 -top-12 h-52 w-52 rounded-full bg-emerald-100/60 blur-2xl"></div>
        <div class="absolute -bottom-20 left-1/2 h-48 w-48 -translate-x-1/2 rounded-full bg-blue-100/60 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                    {{ __('app.grammar') }}
                </div>
                <h1 class="mt-4 text-4xl font-semibold text-slate-900 md:text-5xl">{{ $topic->title }}</h1>
                @if($topic->description)
                    <p class="mt-3 text-base text-slate-600 md:text-lg leading-relaxed">{{ $topic->description }}</p>
                @endif
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="#rules" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">{{ __('app.rules') }}</a>
                    <a href="{{ route('grammar.practice', $topic) }}" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('app.practice_now') }}</a>
                </div>
            </div>
            @if($topic->cefr_level)
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white/90 px-6 py-5 text-base text-slate-700 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-lg font-semibold text-white shadow-sm">
                        {{ $topic->cefr_level }}
                    </div>
                    <div>
                        <div class="text-xs uppercase text-slate-400">{{ __('app.cefr_level') }}</div>
                        <div class="text-base font-semibold text-slate-900">{{ __('app.cefr_level') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr,1fr]">
        <div class="space-y-4">
            <div id="rules" class="flex items-center justify-between">
                <div class="text-lg font-semibold text-slate-900">{{ __('app.rules') }}</div>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $topic->rules->count() }} {{ __('app.questions_label') }}</span>
            </div>
            @if(auth()->check())
                <div
                    class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm"
                    data-topic-ai-help
                    data-create-url="{{ route('grammar.topics.ai-help.store', $topic) }}"
                    data-status-base="{{ route('grammar.topics.ai-help.show', ['help' => 0]) }}"
                    data-help-id="{{ $topicHelp?->id }}"
                    data-status="{{ $topicHelp?->status ?? 'idle' }}"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs font-semibold uppercase text-slate-500">{{ __('app.ask_ai_title') }}</div>
                        <button type="button" data-topic-ai-help-submit class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                            <span data-topic-ai-help-spinner class="hidden h-3 w-3 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                            <span data-topic-ai-help-button-text>{{ __('app.ask_ai') }}</span>
                        </button>
                    </div>
                    <textarea
                        data-topic-ai-help-input
                        rows="2"
                        class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                        placeholder="{{ __('app.ask_ai_placeholder') }}"
                    >{{ $topicHelp?->user_prompt }}</textarea>
                    <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" data-topic-ai-help-response>
                        @if($topicHelp && $topicHelp->status === 'done')
                            {{ $topicHelp->ai_response }}
                        @elseif($topicHelp && $topicHelp->status === 'failed')
                            {{ __('app.ai_help_failed') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-slate-400" data-topic-ai-help-status>
                        @if($topicHelp && in_array($topicHelp->status, ['queued', 'processing'], true))
                            {{ __('app.ai_help_pending') }}
                        @elseif($topicHelp && $topicHelp->status === 'failed')
                            {{ $topicHelp->error_message }}
                        @elseif($topicHelp && $topicHelp->status === 'done')
                            {{ __('app.ai_help_done') }}
                        @else
                            {{ __('app.ask_ai_hint') }}
                        @endif
                    </div>
                </div>
            @endif

            @if($topic->rules->isNotEmpty())
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($topic->rules as $rule)
                @php
                    $payload = (array) ($rule->content_json ?? []);
                    $locale = app()->getLocale();
                    $ruleText = trim((string) ($rule->{'rule_text_'.$locale} ?? ''));
                    if ($ruleText === '') {
                        $ruleText = trim((string) ($rule->rule_text_uz ?? ''));
                    }
                    if ($ruleText === '') {
                        $ruleText = trim((string) ($payload['rule_'.$locale] ?? ''));
                    }
                    if ($ruleText === '') {
                        $ruleText = trim((string) ($payload['rule_uz'] ?? ''));
                    }
                    if ($ruleText === '') {
                        $ruleText = trim((string) $rule->content);
                    }

                    $formula = trim((string) ($rule->formula ?? $payload['formula'] ?? ''));
                    $examples = trim((string) ($rule->{'example_'.$locale} ?? ''));
                    if ($examples === '') {
                        $examples = trim((string) ($rule->example_uz ?? ''));
                    }
                    if ($examples === '') {
                        $examples = trim((string) ($payload['example_'.$locale] ?? ''));
                    }
                    if ($examples === '') {
                        $examples = trim((string) ($payload['example_uz'] ?? ''));
                    }

                    $exampleNegative = trim((string) ($rule->negative_example ?? $payload['example_negative'] ?? $payload['example_nega'] ?? ''));
                    $commonMistake = trim((string) ($rule->common_mistake ?? $payload['common_mistake'] ?? $payload['common_mist'] ?? ''));
                    $correctForm = trim((string) ($rule->correct_form ?? $payload['correct_form'] ?? ''));

                    $primaryText = $ruleText !== '' ? $ruleText : trim((string) $rule->content);
                    $plain = \Illuminate\Support\Str::of(strip_tags($primaryText))->squish();
                    $preview = $plain->limit(140);

                    $cardTitle = $plain->isNotEmpty() ? $plain->limit(80) : '';
                    if ($cardTitle === '') {
                        $cardTitle = trim((string) $rule->title);
                    }
                    if ($cardTitle === '') {
                        $cardTitle = trim((string) ($rule->rule_key ?? ''));
                    }
                    if ($cardTitle === '') {
                        $cardTitle = __('app.rule');
                    }

                    $typeRaw = (string) ($rule->rule_type ?? '');
                    $typeLabels = [
                        'core' => __('app.rule_type_core'),
                        'usage' => __('app.rule_type_usage'),
                        'note' => __('app.rule_type_note'),
                        'exception' => __('app.rule_type_exception'),
                    ];
                    $typeLabel = $typeLabels[$typeRaw] ?? ($typeRaw !== '' ? $typeRaw : __('app.rule'));
                    $accents = [
                        ['stripe' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-700', 'glow' => 'from-emerald-400/20 to-transparent'],
                        ['stripe' => 'bg-sky-500', 'badge' => 'bg-sky-100 text-sky-700', 'glow' => 'from-sky-400/20 to-transparent'],
                        ['stripe' => 'bg-amber-500', 'badge' => 'bg-amber-100 text-amber-700', 'glow' => 'from-amber-400/20 to-transparent'],
                        ['stripe' => 'bg-rose-500', 'badge' => 'bg-rose-100 text-rose-700', 'glow' => 'from-rose-400/20 to-transparent'],
                    ];
                    $accent = $accents[$loop->index % count($accents)];
                @endphp
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-2 hover:ring-slate-100 [&_img]:hidden">
                    <div class="absolute inset-x-0 top-0 h-1 {{ $accent['stripe'] }}"></div>
                    <div class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-gradient-to-br {{ $accent['glow'] }}"></div>
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl text-base font-semibold {{ $accent['badge'] }}">
                            {{ $loop->iteration }}
                         </div>
                         <div class="flex-1">
                             <div class="flex items-center justify-between gap-2">
                                 <div class="text-lg font-semibold text-slate-900">{{ $cardTitle }}</div>
                                 <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold uppercase text-slate-400">{{ $typeLabel }}</span>
                             </div>
                             @if($plain->isNotEmpty())
                                 <div class="mt-2 text-sm text-slate-600">{{ $preview }}</div>
                             @endif
                         </div>
                    </div>
                         @if($plain->isNotEmpty())
                         <details class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/80 p-4 text-base text-slate-700">
                             <summary class="cursor-pointer text-sm font-semibold uppercase tracking-wide text-slate-500">
                                 {{ __('app.view_details') }}
                             </summary>
                             <div class="mt-3 space-y-3">
                                 @if($ruleText !== '')
                                     <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                         <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_rule') }}</div>
                                        <div class="mt-2">{!! nl2br(e($ruleText)) !!}</div>
                                    </div>
                                @endif
                                @if($formula !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_formula') }}</div>
                                        <div class="mt-2">{!! nl2br(e($formula)) !!}</div>
                                    </div>
                                @endif
                                @if($examples !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_examples') }}</div>
                                        <div class="mt-2">{!! nl2br(e($examples)) !!}</div>
                                    </div>
                                @endif
                                @if($exampleNegative !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_example_negative') }}</div>
                                        <div class="mt-2">{!! nl2br(e($exampleNegative)) !!}</div>
                                    </div>
                                @endif
                                @if($commonMistake !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_common_mistake') }}</div>
                                        <div class="mt-2">{!! nl2br(e($commonMistake)) !!}</div>
                                    </div>
                                @endif
                                @if($correctForm !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.rule_detail_correct_form') }}</div>
                                        <div class="mt-2">{!! nl2br(e($correctForm)) !!}</div>
                                    </div>
                                @endif
                                @if($ruleText === '' && trim((string) $rule->content) !== '')
                                    <div class="rounded-2xl border border-slate-100 bg-white p-4 leading-relaxed">
                                        {!! nl2br(e($rule->content)) !!}
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 text-sm text-slate-600 shadow-sm">
                    {{ __('app.no_rules') }}
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="text-lg font-semibold text-slate-900">{{ __('app.practice') }}</div>
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                <div class="text-sm font-semibold uppercase text-slate-400">{{ __('app.ready_to_practice') }}</div>
                <div class="mt-3 text-2xl font-semibold text-slate-900">{{ $topic->exercises_count }} {{ __('app.questions_label') }}</div>
                <p class="mt-2 text-sm text-slate-600">{{ __('app.practice_now') }}</p>
                @if($topic->exercises_count > 0)
                    <a href="{{ route('grammar.practice', $topic) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg hover:bg-slate-800">
                        {{ __('app.practice_now') }}
                    </a>
                @else
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ __('app.no_exercises') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if(auth()->check())
        <div
            data-topic-ai-texts
            data-pending="{{ __('app.ai_help_pending') }}"
            data-failed="{{ __('app.ai_help_failed') }}"
            data-done="{{ __('app.ai_help_done') }}"
            data-empty="{{ __('app.ask_ai_hint') }}"
            data-ask="{{ __('app.ask_ai') }}"
            data-daily-limit="{{ __('app.daily_limit_reached') }}"
        ></div>

        <script>
            (() => {
                const container = document.querySelector('[data-topic-ai-help]');
                if (!container) return;

                const textSource = document.querySelector('[data-topic-ai-texts]');
                const texts = {
                    pending: textSource?.dataset?.pending || 'AI is preparing an answer...',
                    failed: textSource?.dataset?.failed || 'AI could not respond.',
                    done: textSource?.dataset?.done || 'AI response',
                    empty: textSource?.dataset?.empty || 'Ask why an option is correct.',
                    ask: textSource?.dataset?.ask || 'Ask AI',
                    dailyLimit: textSource?.dataset?.dailyLimit || '',
                };
                const responseEl = container.querySelector('[data-topic-ai-help-response]');

                const renderAiHelpResponse = (el, raw) => {
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

                const setStatus = (state, message) => {
                    const statusEl = container.querySelector('[data-topic-ai-help-status]');
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

                const setLoading = (isLoading) => {
                    const button = container.querySelector('[data-topic-ai-help-submit]');
                    const spinner = container.querySelector('[data-topic-ai-help-spinner]');
                    const buttonText = container.querySelector('[data-topic-ai-help-button-text]');
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

                const updateFromStatus = (payload) => {
                    if (!payload || !payload.status) return;
                    container.dataset.status = payload.status;

                    if (payload.status === 'done') {
                        renderAiHelpResponse(responseEl, payload.ai_response || '');
                        const limitNotice = payload.limit_notice || container.dataset.limitNotice;
                        const doneMessage = limitNotice ? `${texts.done} - ${limitNotice}` : texts.done;
                        setStatus('done', doneMessage);
                        setLoading(false);
                        if (container._aiPoll) {
                            clearInterval(container._aiPoll);
                            container._aiPoll = null;
                        }
                        return;
                    }

                    if (payload.status === 'failed') {
                        responseEl.textContent = texts.failed;
                        setStatus('failed', payload.error_message || texts.failed);
                        setLoading(false);
                        if (container._aiPoll) {
                            clearInterval(container._aiPoll);
                            container._aiPoll = null;
                        }
                        return;
                    }

                    setStatus('pending', texts.pending);
                    setLoading(true);
                };

                const startPolling = () => {
                    if (container._aiPoll) return;

                    const poll = async () => {
                        const helpId = container.dataset.helpId;
                        if (!helpId || !['queued', 'processing'].includes(container.dataset.status)) return;

                        try {
                            const statusUrl = container.dataset.statusBase.replace(/\/0$/, `/${helpId}`);
                            const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                            if (!response.ok) return;
                            const payload = await response.json();
                            updateFromStatus(payload);
                        } catch (e) {
                            // ignore
                        }
                    };

                    container._aiPoll = setInterval(poll, 3000);
                    poll();
                };

                const button = container.querySelector('[data-topic-ai-help-submit]');
                const input = container.querySelector('[data-topic-ai-help-input]');

                const handleErrorResponse = async (response, responseEl) => {
                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (e) {
                        payload = null;
                    }

                    const message = payload?.message || texts.failed;
                    responseEl.textContent = message;
                    setStatus('failed', message);
                    setLoading(false);

                    if (payload?.upgrade_prompt || (texts.dailyLimit && message === texts.dailyLimit)) {
                        window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: texts.ask || 'AI' } }));
                    }
                };

                const submit = async () => {
                    if (container.dataset.pending === '1') {
                        return;
                    }

                    const createUrl = container.dataset.createUrl;
                    const message = (input.value || '').trim();

                    if (!message) {
                        setStatus('empty', texts.empty);
                        return;
                    }

                    setLoading(true);
                    responseEl.textContent = texts.pending;
                    setStatus('pending', texts.pending);

                    try {
                        const response = await fetch(createUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content'),
                            },
                            body: JSON.stringify({ prompt: message }),
                        });

                        if (!response.ok) {
                            await handleErrorResponse(response, responseEl);
                            return;
                        }

                        const payload = await response.json();
                        container.dataset.helpId = payload.id;
                        container.dataset.status = payload.status;
                        if (payload.limit_notice) {
                            container.dataset.limitNotice = payload.limit_notice;
                        }
                        startPolling();
                    } catch (e) {
                        responseEl.textContent = texts.failed;
                        setStatus('failed', texts.failed);
                        setLoading(false);
                    }
                };

                button?.addEventListener('click', submit);
                input?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        submit();
                    }
                });

                if (container.dataset.status === 'done' && responseEl) {
                    renderAiHelpResponse(responseEl, responseEl.textContent);
                }

                if (['queued', 'processing'].includes(container.dataset.status)) {
                    setLoading(true);
                    startPolling();
                }
            })();
        </script>
    @endif
</x-layouts.app>

