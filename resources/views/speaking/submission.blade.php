<x-layouts.app :title="__('app.speaking')">
    @php($accuracyPercent = data_get($submission->ai_feedback_json, 'accuracy_percent'))
    @php($accuracyPercent = is_numeric($accuracyPercent)
        ? (int) round((float) $accuracyPercent)
        : (is_numeric($submission->band_score) ? (int) round((((float) $submission->band_score) / 9) * 100) : null))
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-purple-700">
                {{ __('app.speaking') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">Part {{ $submission->prompt?->part }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">Foiz</div>
            <div class="text-2xl font-semibold" data-accuracy-percent>
                {{ is_numeric($accuracyPercent) ? ((int) round($accuracyPercent)).'%' : '-' }}
            </div>
        </div>
    </div>

    <div
        class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm"
        data-speaking-config
        data-status-value="{{ $submission->status }}"
        data-status-url="{{ route('speaking.submissions.status', $submission) }}"
        data-feedback-ready="{{ __('app.writing_feedback_ready') }}"
        data-feedback-failed="{{ __('app.writing_feedback_failed') }}"
        data-feedback-pending="{{ __('app.writing_feedback_pending') }}"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_status') }}</div>
            <div class="text-xs text-slate-400">{{ $submission->created_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="mt-3 text-sm" data-status>
            @if($submission->status === 'done')
                <span class="text-emerald-700">{{ __('app.writing_feedback_ready') }}</span>
            @elseif($submission->status === 'failed')
                <span class="text-rose-600">{{ __('app.writing_feedback_failed') }}</span>
            @else
                <span class="text-amber-600">{{ __('app.writing_feedback_pending') }}</span>
            @endif
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">Prompt</div>
            <div class="mt-3 text-sm text-slate-700">{{ $submission->prompt?->prompt }}</div>
            <div class="mt-4 text-xs text-slate-400">Difficulty: {{ $submission->prompt?->difficulty }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_your_response') }}</div>
            <div class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $submission->response_text }}</div>
            @if($submission->audio_path)
                <div class="mt-4 text-xs font-semibold uppercase text-slate-400">Audio</div>
                <audio class="mt-2 w-full" controls src="{{ asset('storage/'.$submission->audio_path) }}"></audio>
            @endif
            @if($submission->transcript_text)
                <div class="mt-4 text-xs font-semibold uppercase text-slate-400">Transcript</div>
                <div class="mt-2 text-sm text-slate-700">{{ $submission->transcript_text }}</div>
            @elseif($submission->has_audio)
                <div class="mt-4 text-xs text-slate-400">Transcript pending...</div>
            @endif
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_ai_feedback') }}</div>
        <div class="mt-4 text-sm text-slate-700" data-feedback>
            @if($submission->status === 'done')
                @php($summaryText = null)
                @php($criteria = $submission->ai_feedback_json['criteria'] ?? [])
                @php($criteriaNotes = collect($criteria)->pluck('notes')->filter()->values())
                @php($rawFeedback = (string) $submission->ai_feedback)
                @php($fallbackSummary = null)
                @php($fallbackNotes = collect())
                @if(!$summaryText && preg_match('/"summary"\s*:\s*"([^"]+)"/', $rawFeedback, $summaryMatch))
                    @php($fallbackSummary = $summaryMatch[1] ?? null)
                @endif
                @if($criteriaNotes->isEmpty() && preg_match_all('/"notes"\s*:\s*"([^"]+)"/', $rawFeedback, $noteMatches))
                    @php($fallbackNotes = collect($noteMatches[1] ?? [])->filter()->values())
                @endif
                @if($criteriaNotes->isNotEmpty())
                    @php($taskNote = $criteria['task_response']['notes'] ?? null)
                    @php($coherenceNote = $criteria['coherence_cohesion']['notes'] ?? null)
                    @php($grammarNote = $criteria['grammar_range_accuracy']['notes'] ?? null)
                    @php($lexicalNote = $criteria['lexical_resource']['notes'] ?? null)
                    @php($fluencyNote = $criteria['fluency_coherence']['notes'] ?? null)
                    @php($summaryParts = collect([$taskNote, $coherenceNote ?: $fluencyNote, $grammarNote ?: $lexicalNote])->filter()->take(2))
                    {{ $summaryParts->implode(' ') }}
                @elseif($fallbackSummary)
                    {{ trim(str_replace(['\\n', '\\"'], [' ', '"'], $fallbackSummary)) }}
                @elseif($fallbackNotes->isNotEmpty())
                    {{ $fallbackNotes->take(2)->implode(' ') }}
                @else
                    {{ $rawFeedback && str_contains($rawFeedback, '{') ? __('app.writing_feedback_ready') : ($submission->ai_feedback ?: __('app.writing_feedback_ready')) }}
                @endif
            @elseif($submission->status === 'failed')
                {{ $submission->ai_error ?: __('app.writing_feedback_failed') }}
            @else
                {{ __('app.writing_feedback_pending') }}
            @endif
        </div>
        @if($submission->status === 'done' && $submission->ai_feedback_json)
            <textarea class="hidden" data-feedback-raw>@json($submission->ai_feedback_json)</textarea>
        @elseif($submission->status === 'done' && $submission->ai_feedback)
            <textarea class="hidden" data-feedback-raw>{{ $submission->ai_feedback }}</textarea>
        @endif

        @if($submission->ai_feedback_json)
            @php($criteria = $submission->ai_feedback_json['criteria'] ?? [])
            <div class="mt-6 border-t border-slate-100 pt-4">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing_criteria') }}</div>
                <div class="mt-3 space-y-3 text-sm text-slate-700" data-criteria>
                    @foreach($criteria as $key => $item)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <div class="font-semibold">{{ \Illuminate\Support\Str::headline($key) }}</div>
                            <div class="mt-1 text-xs text-slate-600">{{ $item['notes'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @php($strengths = $submission->ai_feedback_json['strengths'] ?? [])
            @php($weaknesses = $submission->ai_feedback_json['weaknesses'] ?? [])
            @php($nextSteps = $submission->ai_feedback_json['next_steps'] ?? [])
            @php($corrections = $submission->ai_feedback_json['corrections'] ?? [])
            @php($examples = $submission->ai_feedback_json['examples'] ?? [])
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-sm text-emerald-900" data-speaking-strengths>
                    <div class="text-xs font-semibold uppercase text-emerald-700">Kuchli tomonlar</div>
                    @if(!empty($strengths))
                        <ul class="mt-2 space-y-1 text-xs text-emerald-800">
                            @foreach($strengths as $item)
                                <li>- {{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-2 text-xs text-emerald-700">Ma’lumot yo‘q.</div>
                    @endif
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-3 text-sm text-amber-900" data-speaking-weaknesses>
                    <div class="text-xs font-semibold uppercase text-amber-700">Zaif tomonlar</div>
                    @if(!empty($weaknesses))
                        <ul class="mt-2 space-y-1 text-xs text-amber-800">
                            @foreach($weaknesses as $item)
                                <li>- {{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-2 text-xs text-amber-700">Ma’lumot yo‘q.</div>
                    @endif
                </div>
                <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-3 text-sm text-sky-900" data-speaking-next-steps>
                    <div class="text-xs font-semibold uppercase text-sky-700">Mashq uchun</div>
                    @if(!empty($nextSteps))
                        <ul class="mt-2 space-y-1 text-xs text-sky-800">
                            @foreach($nextSteps as $item)
                                <li>- {{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-2 text-xs text-sky-700">Ma’lumot yo‘q.</div>
                    @endif
                </div>
            </div>
            @if(!empty($corrections) || !empty($examples))
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900">
                        <div class="text-xs font-semibold uppercase text-slate-500">Corrections</div>
                        @if(!empty($corrections))
                            <ul class="mt-2 space-y-2 text-xs text-slate-700">
                                @foreach($corrections as $item)
                                    <li>
                                        <div class="font-semibold">{{ $item['issue'] ?? 'Issue' }}</div>
                                        <div class="text-slate-500">Before: {{ $item['before'] ?? '-' }}</div>
                                        <div class="text-slate-700">After: {{ $item['after'] ?? '-' }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-slate-500">Ma’lumot yo‘q.</div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900">
                        <div class="text-xs font-semibold uppercase text-slate-500">Examples</div>
                        @if(!empty($examples))
                            <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                @foreach($examples as $item)
                                    <li>- {{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-slate-500">Ma’lumot yo‘q.</div>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

    <div class="mt-6">
        <a href="{{ route('speaking.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            {{ __('app.back_to_list') }}
        </a>
    </div>

    <script>
        (() => {
            const configEl = document.querySelector('[data-speaking-config]');
            const statusEl = document.querySelector('[data-status]');
            const feedbackEl = document.querySelector('[data-feedback]');
            const accuracyEl = document.querySelector('[data-accuracy-percent]');
            const criteriaWrapper = document.querySelector('[data-criteria]');
            const rawFeedbackEl = document.querySelector('[data-feedback-raw]');
            const strengthsBox = document.querySelector('[data-speaking-strengths]');
            const weaknessesBox = document.querySelector('[data-speaking-weaknesses]');
            const nextStepsBox = document.querySelector('[data-speaking-next-steps]');
            const config = {
                status: configEl?.dataset.statusValue || '',
                statusUrl: configEl?.dataset.statusUrl || '',
                feedbackReady: configEl?.dataset.feedbackReady || '',
                feedbackFailed: configEl?.dataset.feedbackFailed || '',
                feedbackPending: configEl?.dataset.feedbackPending || '',
            };

            const toNumberOrNull = (value) => {
                if (value === null || value === undefined || value === '') {
                    return null;
                }
                const num = Number(value);
                return Number.isFinite(num) ? num : null;
            };

            const renderAccuracy = (accuracyPercent, overallBand = null) => {
                if (!accuracyEl) return;
                if (typeof accuracyPercent === 'number' && Number.isFinite(accuracyPercent)) {
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, Math.round(accuracyPercent)))}%`;
                    return;
                }

                if (typeof overallBand === 'number' && Number.isFinite(overallBand)) {
                    const converted = Math.round((overallBand / 9) * 100);
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, converted))}%`;
                    return;
                }

                accuracyEl.textContent = '-';
            };

            const renderParsedFeedback = (parsed) => {
                if (!parsed) return;
                const feedbackCard = feedbackEl?.closest('.rounded-2xl');
                let criteriaContainer = criteriaWrapper;
                if (!criteriaContainer && feedbackCard && parsed.criteria) {
                    const block = document.createElement('div');
                    block.className = 'mt-6 border-t border-slate-100 pt-4';
                    block.innerHTML = `
                        <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing_criteria') }}</div>
                        <div class="mt-3 space-y-3 text-sm text-slate-700" data-criteria></div>
                    `;
                    feedbackCard.appendChild(block);
                    criteriaContainer = block.querySelector('[data-criteria]');
                }
                if (feedbackEl && parsed.summary) {
                    feedbackEl.textContent = parsed.summary;
                } else if (feedbackEl && parsed.criteria) {
                    const summaryNotes = [];
                    const criteriaOrder = ['task_response', 'coherence_cohesion', 'grammar_range_accuracy', 'lexical_resource', 'fluency_coherence'];
                    criteriaOrder.forEach((key) => {
                        const note = parsed.criteria?.[key]?.notes;
                        if (note) summaryNotes.push(note);
                    });
                    const noteChunks = summaryNotes.filter(Boolean).slice(0, 2);
                    if (noteChunks.length) {
                        feedbackEl.textContent = noteChunks.join(' ');
                    }
                }
                renderAccuracy(toNumberOrNull(parsed.accuracy_percent), toNumberOrNull(parsed.overall_band));
                if (criteriaContainer && parsed.criteria) {
                    criteriaContainer.innerHTML = '';
                    Object.entries(parsed.criteria).forEach(([key, item]) => {
                        const div = document.createElement('div');
                        div.className = 'rounded-xl border border-slate-100 bg-slate-50 px-3 py-2';
                        div.innerHTML = `
                            <div class="font-semibold">${key.replace(/_/g, ' ')}</div>
                            <div class="mt-1 text-xs text-slate-600">${item.notes ?? ''}</div>
                        `;
                        criteriaContainer.appendChild(div);
                    });
                }

                const renderList = (wrapper, items, emptyText) => {
                    if (!wrapper) return;
                    while (wrapper.children.length > 1) {
                        wrapper.removeChild(wrapper.lastElementChild);
                    }
                    if (items && items.length) {
                        const ul = document.createElement('ul');
                        ul.className = 'mt-2 space-y-1 text-xs';
                        items.forEach((item) => {
                            const li = document.createElement('li');
                            li.textContent = `- ${item}`;
                            ul.appendChild(li);
                        });
                        wrapper.appendChild(ul);
                    } else {
                        const div = document.createElement('div');
                        div.className = 'mt-2 text-xs';
                        div.textContent = emptyText || 'Ma’lumot yo‘q.';
                        wrapper.appendChild(div);
                    }
                };

                if (parsed.strengths || parsed.weaknesses || parsed.next_steps) {
                    renderList(strengthsBox, parsed.strengths || [], 'Ma’lumot yo‘q.');
                    renderList(weaknessesBox, parsed.weaknesses || [], 'Ma’lumot yo‘q.');
                    renderList(nextStepsBox, parsed.next_steps || [], 'Ma’lumot yo‘q.');
                }
            };

            const tryParse = (text) => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return null;
                }
            };

            const extractSummaryFromText = (text) => {
                if (!text) return null;
                const match = text.match(/\"summary\"\\s*:\\s*\"([^\"]+)\"/);
                if (match && match[1]) {
                    return match[1].replace(/\\n/g, ' ').replace(/\\"/g, '"').trim();
                }
                return null;
            };

            const extractAccuracyPercentFromText = (text) => {
                if (!text) return null;
                const match = text.match(/\"accuracy_percent\"\\s*:\\s*([0-9]+(?:\\.[0-9]+)?)/);
                if (!match || !match[1]) return null;
                const value = Number(match[1]);
                return Number.isFinite(value) ? value : null;
            };

            const extractOverallBandFromText = (text) => {
                if (!text) return null;
                const match = text.match(/\"overall_band\"\\s*:\\s*([0-9]+(?:\\.[0-9]+)?)/);
                if (!match || !match[1]) return null;
                const value = Number(match[1]);
                return Number.isFinite(value) ? value : null;
            };

            if (config.status === 'done' || config.status === 'failed') {
                const rawText = rawFeedbackEl?.value || '';
                const parsed = tryParse(rawText || feedbackEl?.textContent || '');
                if (parsed) {
                    renderParsedFeedback(parsed);
                    return;
                }
                if (rawText && feedbackEl) {
                    const summary = extractSummaryFromText(rawText);
                    feedbackEl.textContent = summary || config.feedbackReady;
                    renderAccuracy(
                        extractAccuracyPercentFromText(rawText),
                        extractOverallBandFromText(rawText)
                    );
                    return;
                }
                if (config.status === 'done') {
                    // No payload yet, keep polling until it appears.
                    config.status = 'running';
                } else {
                    return;
                }
            }

            const poll = async () => {
                try {
                    if (!config.statusUrl) return;
                    const res = await fetch(config.statusUrl, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (!data.status) return;
                    if (data.status === 'done') {
                        const hasJson = !!data.ai_feedback_json;
                        const hasText = !!(data.ai_feedback && String(data.ai_feedback).trim().length);
                        if (!hasJson && !hasText) {
                            statusEl.textContent = config.feedbackPending;
                            return;
                        }
                        statusEl.textContent = config.feedbackReady;
                        if (feedbackEl) {
                            feedbackEl.textContent = data.ai_feedback || config.feedbackReady;
                        }
                        if (data.ai_feedback_json) {
                            renderParsedFeedback(data.ai_feedback_json);
                        } else if (data.ai_feedback) {
                            const parsed = tryParse(data.ai_feedback);
                            if (parsed) {
                                renderParsedFeedback(parsed);
                            } else {
                                const summary = extractSummaryFromText(data.ai_feedback);
                                if (summary) {
                                    feedbackEl.textContent = summary;
                                }
                                renderAccuracy(
                                    extractAccuracyPercentFromText(data.ai_feedback),
                                    extractOverallBandFromText(data.ai_feedback)
                                );
                            }
                        }
                        if (typeof data.accuracy_percent === 'number') {
                            renderAccuracy(data.accuracy_percent);
                        }
                        clearInterval(window.__speakingPoll);
                    } else if (data.status === 'failed') {
                        statusEl.textContent = config.feedbackFailed;
                        feedbackEl.textContent = data.ai_error || config.feedbackFailed;
                        clearInterval(window.__speakingPoll);
                    } else {
                        statusEl.textContent = config.feedbackPending;
                    }
                } catch (e) {
                    // ignore
                }
            };

            window.__speakingPoll = setInterval(poll, 3000);
            poll();
        })();
    </script>
</x-layouts.app>
