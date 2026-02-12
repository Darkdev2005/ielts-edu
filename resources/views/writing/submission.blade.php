<x-layouts.app :title="__('app.writing_result')">
    @php($accuracyPercent = $displayAccuracyPercent ?? data_get($submission->ai_feedback_json, 'accuracy_percent'))
    @php($accuracyPercent = is_numeric($accuracyPercent)
        ? $accuracyPercent
        : data_get($submission->ai_feedback_json, 'health_index.overall_percent'))
    @php($accuracyPercent = is_numeric($accuracyPercent)
        ? (int) round((float) $accuracyPercent)
        : (is_numeric($submission->band_score) ? (int) round((((float) $submission->band_score) / 9) * 100) : null))
    @php($diagnostic = data_get($submission->ai_feedback_json, 'diagnostic', []))
    @php($errorMap = data_get($submission->ai_feedback_json, 'error_map', []))
    @php($improvementPlan = data_get($submission->ai_feedback_json, 'improvement_plan', []))
    @php($healthIndex = data_get($submission->ai_feedback_json, 'health_index.overall_percent'))
    @php($healthLabel = data_get($submission->ai_feedback_json, 'health_index.label'))
    @php($isDiagnosticMode = !empty($diagnostic))
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ __('app.writing') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.writing_result') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $submission->task?->title }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">Writing Health</div>
            <div class="text-2xl font-semibold" data-accuracy-percent>
                {{ is_numeric($accuracyPercent) ? ((int) round($accuracyPercent)).'%' : '-' }}
            </div>
        </div>
    </div>

    <div
        class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm"
        data-writing-config
        data-status-value="{{ $submission->status }}"
        data-status-url="{{ route('writing.submissions.status', $submission) }}"
        data-initial-accuracy="{{ is_numeric($accuracyPercent) ? (int) round((float) $accuracyPercent) : '' }}"
        data-feedback-ready="{{ __('app.writing_feedback_ready') }}"
        data-feedback-failed="{{ __('app.writing_feedback_failed') }}"
        data-criteria-label="{{ __('app.writing_criteria') }}"
        data-strengths-label="{{ __('app.writing_strengths') }}"
        data-weaknesses-label="{{ __('app.writing_weaknesses') }}"
        data-improvements-label="{{ __('app.writing_improvements') }}"
        data-checklist-label="{{ __('app.writing_checklist') }}"
        data-no-strengths="{{ __('app.writing_no_strengths') }}"
        data-no-weaknesses="{{ __('app.writing_no_weaknesses') }}"
        data-no-improvements="{{ __('app.writing_no_improvements') }}"
        data-no-checklist="{{ __('app.writing_no_checklist') }}"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_status') }}</div>
            <div class="text-xs text-slate-400">{{ $submission->submitted_at?->format('Y-m-d H:i') }}</div>
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

    @if(!empty($diagnostic))
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @php($blocks = [
                'task_response' => ['title' => 'Task Response', 'color' => 'emerald'],
                'coherence_cohesion' => ['title' => 'Coherence & Cohesion', 'color' => 'sky'],
                'lexical_resource' => ['title' => 'Lexical Resource', 'color' => 'amber'],
                'grammar_accuracy' => ['title' => 'Grammar Accuracy', 'color' => 'rose'],
                'sentence_variety' => ['title' => 'Sentence Variety', 'color' => 'violet'],
            ])
            @foreach($blocks as $key => $meta)
                @php($percent = (int) data_get($diagnostic, $key.'.percent', 0))
                @php($notes = data_get($diagnostic, $key.'.notes', []))
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $meta['title'] }}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">{{ $percent }}%</div>
                    @if(!empty($notes))
                        <ul class="mt-3 space-y-1 text-xs text-slate-600">
                            @foreach(array_slice((array) $notes, 0, 2) as $note)
                                <li>- {{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-700">Writing Health Index</div>
                    <div class="rounded-lg bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                        {{ is_numeric($healthIndex) ? ((int) $healthIndex).'%' : '-' }}
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    {{ $healthLabel ?: 'Developing' }}
                </div>

                @php($progress = $progressSnapshot ?? ['available' => false])
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <div class="text-xs font-semibold uppercase text-slate-500">Progress monitoring</div>
                    @if(data_get($progress, 'available'))
                        @php($delta = data_get($progress, 'delta', []))
                        @php($curr = data_get($progress, 'current', []))
                        <div class="mt-3 grid gap-2 text-xs text-slate-700">
                            <div>Grammar: {{ $curr['grammar_accuracy'] ?? 0 }}% ({{ ($delta['grammar_accuracy'] ?? 0) >= 0 ? '+' : '' }}{{ $delta['grammar_accuracy'] ?? 0 }})</div>
                            <div>Vocabulary: {{ $curr['lexical_resource'] ?? 0 }}% ({{ ($delta['lexical_resource'] ?? 0) >= 0 ? '+' : '' }}{{ $delta['lexical_resource'] ?? 0 }})</div>
                            <div>Task Response: {{ $curr['task_response'] ?? 0 }}% ({{ ($delta['task_response'] ?? 0) >= 0 ? '+' : '' }}{{ $delta['task_response'] ?? 0 }})</div>
                        </div>
                    @else
                        <div class="mt-2 text-xs text-slate-500">Next submissiondan boshlab progress grafigi ko'rinadi.</div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="text-sm font-semibold text-slate-700">Error Breakdown</div>
                @php($grammarMap = (array) data_get($errorMap, 'grammar', []))
                @php($vocabularyMap = (array) data_get($errorMap, 'vocabulary', []))
                @php($structureMap = (array) data_get($errorMap, 'structure', []))
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                        <div class="font-semibold uppercase">Grammar</div>
                        <div class="mt-1 text-lg font-bold">{{ array_sum(array_map('intval', $grammarMap)) }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <div class="font-semibold uppercase">Vocabulary</div>
                        <div class="mt-1 text-lg font-bold">{{ array_sum(array_map('intval', $vocabularyMap)) }}</div>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                        <div class="font-semibold uppercase">Structure flags</div>
                        <div class="mt-1 text-lg font-bold">
                            {{ collect($structureMap)->filter(fn ($v) => $v === true || (is_numeric($v) && (int) $v > 0))->count() }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase text-slate-500">Grammar details</div>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700">
                            @forelse($grammarMap as $key => $value)
                                @if((int) $value > 0)
                                    <li>- {{ \Illuminate\Support\Str::headline((string) $key) }}: {{ (int) $value }}</li>
                                @endif
                            @empty
                                <li>- No data</li>
                            @endforelse
                        </ul>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-slate-500">Vocabulary details</div>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700">
                            @forelse($vocabularyMap as $key => $value)
                                @if((int) $value > 0)
                                    <li>- {{ \Illuminate\Support\Str::headline((string) $key) }}: {{ (int) $value }}</li>
                                @endif
                            @empty
                                <li>- No data</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-semibold uppercase text-emerald-700">Immediate fixes</div>
                <ul class="mt-2 space-y-1 text-xs text-emerald-900">
                    @foreach((array) data_get($improvementPlan, 'immediate_fixes', []) as $item)
                        <li>- {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <div class="text-xs font-semibold uppercase text-sky-700">Short-term focus</div>
                <ul class="mt-2 space-y-1 text-xs text-sky-900">
                    @foreach((array) data_get($improvementPlan, 'short_term_focus', []) as $item)
                        <li>- {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs font-semibold uppercase text-amber-700">Long-term growth</div>
                <ul class="mt-2 space-y-1 text-xs text-amber-900">
                    @foreach((array) data_get($improvementPlan, 'long_term_growth', []) as $item)
                        <li>- {{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_your_response') }}</div>
            <div class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $submission->response_text }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_ai_feedback') }}</div>
            <div class="mt-4 text-sm text-slate-700" data-feedback>
                @if($submission->status === 'done')
                    @php($feedbackSummary = data_get($submission->ai_feedback_json, 'summary'))
                    @php($rawFeedback = (string) $submission->ai_feedback)
                    @php($summaryText = null)
                    @php($noteItems = [])
                    @if($feedbackSummary)
                        {{ $feedbackSummary }}
                    @else
                    @if(preg_match('/"summary"\s*:\s*"([^"]+)"/', $rawFeedback, $matches))
                        @php($summaryText = $matches[1] ?? null)
                    @else
                        @php($noteMatches = [])
                        @if(preg_match_all('/"notes"\s*:\s*"([^"]+)"/', $rawFeedback, $noteMatches))
                            @php($noteItems = array_filter(array_map('trim', $noteMatches[1] ?? [])))
                            @php($summaryText = implode(' ', array_slice($noteItems, 0, 2)))
                        @endif
                    @endif
                    @if($summaryText)
                        {{ trim(str_replace(['\\n', '\\"'], [' ', '"'], $summaryText)) }}
                    @else
                        {{ __('app.writing_feedback_ready') }}
                    @endif
                    @endif
                @elseif($submission->status === 'failed')
                    {{ $submission->ai_error ?: __('app.writing_feedback_failed') }}
                @else
                    {{ __('app.writing_feedback_pending') }}
                @endif
            </div>
            @if($submission->status === 'done' && $submission->ai_feedback)
                <textarea class="hidden" data-feedback-raw>{{ $submission->ai_feedback }}</textarea>
            @endif
            <div class="mt-4 hidden" data-feedback-notes>
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing_weaknesses') }}</div>
                <ul class="mt-2 space-y-1 text-xs text-slate-700" data-feedback-notes-list></ul>
            </div>
            @php($textErrorsRaw = data_get($submission->ai_feedback_json, 'text_errors', []))
            @php($textErrors = !empty($textErrorsRaw) ? $textErrorsRaw : data_get($submission->ai_feedback_json, 'corrections', []))
            <div class="mt-6 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900 {{ empty($textErrors) ? 'hidden' : '' }}" data-text-errors>
                <div class="text-xs font-semibold uppercase text-slate-500">Text errors</div>
                <ul class="mt-2 space-y-2 text-xs text-slate-700" data-text-errors-list>
                    @foreach((array) $textErrors as $item)
                        @php($before = (string) data_get($item, 'before', data_get($item, 'error', data_get($item, 'issue', ''))))
                        @php($after = (string) data_get($item, 'after', data_get($item, 'fix', '')))
                        @php($reason = (string) data_get($item, 'reason', data_get($item, 'note', data_get($item, 'issue', ''))))
                        @if($before !== '' || $after !== '' || $reason !== '')
                            <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                @if($before !== '')
                                    <div><span class="font-semibold">Before:</span> "{{ $before }}"</div>
                                @endif
                                @if($after !== '')
                                    <div><span class="font-semibold">After:</span> "{{ $after }}"</div>
                                @endif
                                @if($reason !== '')
                                    <div class="text-slate-500">{{ $reason }}</div>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
            @if($submission->status === 'done' && !$submission->ai_feedback_json)
                @php($criteriaItems = [])
                @if(preg_match_all('/"([a-z_]+)"\s*:\s*\{[^}]*"notes"\s*:\s*"([^"]*)"/', $rawFeedback ?? '', $criteriaMatches, PREG_SET_ORDER))
                    @foreach($criteriaMatches as $match)
                        @php($criteriaItems[$match[1]] = ['notes' => $match[2]])
                    @endforeach
                @endif
                @if(!empty($criteriaItems))
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing_criteria') }}</div>
                        <div class="mt-3 space-y-3 text-sm text-slate-700">
                            @foreach($criteriaItems as $key => $item)
                                <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                                    <div class="font-semibold">{{ \Illuminate\Support\Str::headline($key) }}</div>
                                    <div class="mt-1 text-xs text-slate-600">{{ trim(str_replace(['\\n', '\\"'], [' ', '"'], $item['notes'] ?? '')) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($noteItems))
                    <div class="mt-4">
                        <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing_weaknesses') }}</div>
                        <ul class="mt-2 space-y-1 text-xs text-slate-700">
                            @foreach($noteItems as $item)
                                <li>- {{ trim(str_replace(['\\n', '\\"'], [' ', '"'], $item)) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            @if($submission->ai_feedback_json && !$isDiagnosticMode)
                @php($criteria = $submission->ai_feedback_json['criteria'] ?? [])
                @php($strengths = $submission->ai_feedback_json['strengths'] ?? [])
                @php($weaknesses = $submission->ai_feedback_json['weaknesses'] ?? [])
                @php($improvements = $submission->ai_feedback_json['improvements'] ?? [])
                @php($checklist = $submission->ai_feedback_json['checklist'] ?? [])
                @php($corrections = $submission->ai_feedback_json['corrections'] ?? [])
                @php($examples = $submission->ai_feedback_json['examples'] ?? [])
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
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-writing-insights>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-sm text-emerald-900" data-writing-strengths>
                        <div class="text-xs font-semibold uppercase text-emerald-700">{{ __('app.writing_strengths') }}</div>
                        @if(!empty($strengths))
                            <ul class="mt-2 space-y-1 text-xs text-emerald-800">
                                @foreach($strengths as $item)
                                    <li>- {{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-emerald-700">{{ __('app.writing_no_strengths') }}</div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-3 text-sm text-amber-900" data-writing-weaknesses>
                        <div class="text-xs font-semibold uppercase text-amber-700">{{ __('app.writing_weaknesses') }}</div>
                        @if(!empty($weaknesses))
                            <ul class="mt-2 space-y-1 text-xs text-amber-800">
                                @foreach($weaknesses as $item)
                                    <li>- {{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-amber-700">{{ __('app.writing_no_weaknesses') }}</div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-3 text-sm text-sky-900" data-writing-improvements>
                        <div class="text-xs font-semibold uppercase text-sky-700">{{ __('app.writing_improvements') }}</div>
                        @if(!empty($improvements))
                            <ul class="mt-2 space-y-1 text-xs text-sky-800">
                                @foreach($improvements as $item)
                                    <li>- {{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-sky-700">{{ __('app.writing_no_improvements') }}</div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-3 text-sm text-indigo-900" data-writing-checklist>
                        <div class="text-xs font-semibold uppercase text-indigo-700">{{ __('app.writing_checklist') }}</div>
                        @if(!empty($checklist))
                            <ul class="mt-2 space-y-1 text-xs text-indigo-800">
                                @foreach($checklist as $item)
                                    <li>- {{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-2 text-xs text-indigo-700">{{ __('app.writing_no_checklist') }}</div>
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
                                <div class="mt-2 text-xs text-slate-500">No corrections provided.</div>
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
                                <div class="mt-2 text-xs text-slate-500">No examples provided.</div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-6 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-900" data-mini-practice>
                        <div class="text-xs font-semibold uppercase text-slate-500">Mini practice</div>
                        @if(!empty($corrections))
                            <div class="mt-3 space-y-3" data-practice-corrections>
                                @foreach($corrections as $index => $item)
                                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                        <div class="text-xs text-slate-500">Fix the sentence</div>
                                        <div class="mt-1 text-xs text-slate-700">Before: {{ $item['before'] ?? '-' }}</div>
                                        <div class="mt-2">
                                            <input
                                                type="text"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                                                placeholder="Rewrite correctly..."
                                                data-correction-input
                                                data-correction-answer="{{ $item['after'] ?? '' }}"
                                            >
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500" data-correction-result></div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty($examples))
                            <div class="mt-4 space-y-3" data-practice-examples>
                                @foreach($examples as $item)
                                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                        <div class="text-xs text-slate-500">Write a similar sentence</div>
                                        <div class="mt-1 text-xs text-slate-700">Example: {{ $item }}</div>
                                        <div class="mt-2">
                                            <input type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700" placeholder="Your sentence...">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <button type="button" class="mt-4 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white" data-practice-check>
                            Check answers
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div
        class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm"
        data-writing-help
        data-create-url="{{ route('writing.ai-help.store', $submission) }}"
        data-status-base="{{ url('/writing/ai-help') }}"
        data-csrf-token="{{ csrf_token() }}"
        data-daily-limit-message="{{ __('app.daily_limit_reached') }}"
        data-ready-text="{{ __('app.writing_chat_ready') }}"
        data-ai-help-failed="{{ __('app.ai_help_failed') }}"
        data-ai-help-pending="{{ __('app.ai_help_pending') }}"
        data-ask-ai="{{ __('app.ask_ai') }}"
        data-writing-chat-hint="{{ __('app.writing_chat_hint') }}"
        data-writing-label="{{ __('app.writing') }}"
        data-user-label="{{ __('app.user') }}"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs font-semibold uppercase text-slate-500">{{ __('app.writing_chat_title') }}</div>
            <button type="button" data-writing-help-submit class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                <span data-writing-help-spinner class="hidden h-3 w-3 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                <span data-writing-help-button-text>{{ __('app.ask_ai') }}</span>
            </button>
        </div>
        <textarea
            data-writing-help-input
            rows="2"
            class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
            placeholder="{{ __('app.writing_chat_placeholder') }}"
        ></textarea>
        <div class="mt-3 text-xs text-slate-400" data-writing-help-status>{{ __('app.writing_chat_hint') }}</div>
        <div class="mt-4 space-y-3" data-writing-help-history>
            @foreach($helps ?? [] as $item)
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <div class="text-xs font-semibold text-slate-400">{{ __('app.user') }}</div>
                    <div class="mt-1">{{ $item->user_prompt }}</div>
                </div>
                @if($item->ai_response)
                    <div class="rounded-xl border border-slate-100 bg-white px-3 py-2 text-sm text-slate-700">
                        <div class="text-xs font-semibold text-slate-400">AI</div>
                        <div class="mt-1">{{ $item->ai_response }}</div>
                    </div>
                @elseif($item->status === 'failed')
                    <div class="rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ $item->error_message ?: __('app.ai_help_failed') }}
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('writing.show', $submission->task) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            {{ __('app.back_to_task') }}
        </a>
        <a href="{{ route('writing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            {{ __('app.back_to_list') }}
        </a>
    </div>

    <script>
        (() => {
            const configEl = document.querySelector('[data-writing-config]');
            const statusEl = document.querySelector('[data-status]');
            const feedbackEl = document.querySelector('[data-feedback]');
            const accuracyEl = document.querySelector('[data-accuracy-percent]');
            const criteriaWrapper = document.querySelector('[data-criteria]');
            const strengthsWrapper = document.querySelector('[data-writing-strengths]');
            const weaknessesWrapper = document.querySelector('[data-writing-weaknesses]');
            const improvementsWrapper = document.querySelector('[data-writing-improvements]');
            const checklistWrapper = document.querySelector('[data-writing-checklist]');
            const insightsWrapper = document.querySelector('[data-writing-insights]');
            const notesContainer = document.querySelector('[data-feedback-notes]');
            const notesList = document.querySelector('[data-feedback-notes-list]');
            const rawFeedbackEl = document.querySelector('[data-feedback-raw]');
            const textErrorsContainer = document.querySelector('[data-text-errors]');
            const textErrorsList = document.querySelector('[data-text-errors-list]');
            const config = {
                status: configEl?.dataset.statusValue || '',
                statusUrl: configEl?.dataset.statusUrl || '',
                initialAccuracy: configEl?.dataset.initialAccuracy || '',
                feedbackReady: configEl?.dataset.feedbackReady || '',
                feedbackFailed: configEl?.dataset.feedbackFailed || '',
                feedbackPending: configEl?.dataset.feedbackPending || '',
                criteriaLabel: configEl?.dataset.criteriaLabel || '',
                strengthsLabel: configEl?.dataset.strengthsLabel || '',
                weaknessesLabel: configEl?.dataset.weaknessesLabel || '',
                improvementsLabel: configEl?.dataset.improvementsLabel || '',
                checklistLabel: configEl?.dataset.checklistLabel || '',
                noStrengths: configEl?.dataset.noStrengths || '',
                noWeaknesses: configEl?.dataset.noWeaknesses || '',
                noImprovements: configEl?.dataset.noImprovements || '',
                noChecklist: configEl?.dataset.noChecklist || '',
            };
            const toNumberOrNull = (value) => {
                if (value === null || value === undefined || value === '') {
                    return null;
                }
                const num = Number(value);
                return Number.isFinite(num) ? num : null;
            };
            const initialAccuracy = toNumberOrNull(config.initialAccuracy);
            const renderAccuracy = (value, overallBand = null, healthIndex = null) => {
                if (!accuracyEl) return;
                if (typeof value === 'number' && Number.isFinite(value)) {
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, Math.round(value)))}%`;
                    return;
                }
                if (typeof overallBand === 'number' && Number.isFinite(overallBand)) {
                    const converted = Math.round((overallBand / 9) * 100);
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, converted))}%`;
                    return;
                }
                if (typeof healthIndex === 'number' && Number.isFinite(healthIndex)) {
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, Math.round(healthIndex)))}%`;
                    return;
                }
                if (typeof initialAccuracy === 'number' && Number.isFinite(initialAccuracy)) {
                    accuracyEl.textContent = `${Math.max(0, Math.min(100, Math.round(initialAccuracy)))}%`;
                    return;
                }
                const existing = (accuracyEl.textContent || '').trim();
                if (/^[0-9]+(?:\.[0-9]+)?%$/.test(existing)) {
                    return;
                }
                accuracyEl.textContent = '-';
            };
            const renderTextErrors = (items) => {
                if (!textErrorsContainer || !textErrorsList) return;
                textErrorsList.innerHTML = '';
                if (!Array.isArray(items) || items.length === 0) {
                    textErrorsContainer.classList.add('hidden');
                    return;
                }
                items.forEach((item) => {
                    if (!item) return;
                    const before = String(item.before ?? item.error ?? item.issue ?? '').trim();
                    const after = String(item.after ?? item.fix ?? '').trim();
                    const reason = String(item.reason ?? item.note ?? item.issue ?? '').trim();
                    if (!before && !after && !reason) return;
                    const li = document.createElement('li');
                    li.className = 'rounded-lg border border-slate-100 bg-slate-50 px-3 py-2';
                    li.innerHTML = `
                        ${before ? `<div><span class="font-semibold">Before:</span> "${before}"</div>` : ''}
                        ${after ? `<div><span class="font-semibold">After:</span> "${after}"</div>` : ''}
                        ${reason ? `<div class="text-slate-500">${reason}</div>` : ''}
                    `;
                    textErrorsList.appendChild(li);
                });
                if (textErrorsList.children.length) {
                    textErrorsContainer.classList.remove('hidden');
                } else {
                    textErrorsContainer.classList.add('hidden');
                }
            };
            const renderParsedFeedback = (parsed) => {
                if (!parsed) return;
                const feedbackCard = feedbackEl?.closest('.rounded-2xl');
                let criteriaContainer = criteriaWrapper;
                if (!criteriaContainer && feedbackCard && parsed.criteria) {
                    const block = document.createElement('div');
                    block.className = 'mt-6 border-t border-slate-100 pt-4';
                    block.innerHTML = `
                        <div class="text-xs font-semibold uppercase text-slate-400">${config.criteriaLabel}</div>
                        <div class="mt-3 space-y-3 text-sm text-slate-700" data-criteria></div>
                    `;
                    feedbackCard.appendChild(block);
                    criteriaContainer = block.querySelector('[data-criteria]');
                }

                let insightsBlock = insightsWrapper || null;
                let strengthsBox = strengthsWrapper;
                let weaknessesBox = weaknessesWrapper;
                let improvementsBox = improvementsWrapper;
                let checklistBox = checklistWrapper;
                const createInsightBox = (dataAttr, label, wrapperClass, labelClass) => {
                    if (!insightsBlock) return null;
                    const div = document.createElement('div');
                    div.className = wrapperClass;
                    div.setAttribute(dataAttr, '');
                    div.innerHTML = `
                        <div class="text-xs font-semibold uppercase ${labelClass}">${label}</div>
                    `;
                    insightsBlock.appendChild(div);
                    return div;
                };
                if (!insightsBlock && feedbackCard) {
                    insightsBlock = document.createElement('div');
                    insightsBlock.className = 'mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4';
                    feedbackCard.appendChild(insightsBlock);
                }
                if (insightsBlock) {
                    insightsBlock.classList.remove('sm:grid-cols-3');
                    insightsBlock.classList.add('sm:grid-cols-2', 'lg:grid-cols-4');
                    strengthsBox = strengthsBox || createInsightBox(
                        'data-writing-strengths',
                        config.strengthsLabel,
                        'rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-3 text-sm text-emerald-900',
                        'text-emerald-700'
                    );
                    weaknessesBox = weaknessesBox || createInsightBox(
                        'data-writing-weaknesses',
                        config.weaknessesLabel,
                        'rounded-xl border border-amber-100 bg-amber-50 px-3 py-3 text-sm text-amber-900',
                        'text-amber-700'
                    );
                    improvementsBox = improvementsBox || createInsightBox(
                        'data-writing-improvements',
                        config.improvementsLabel,
                        'rounded-xl border border-sky-100 bg-sky-50 px-3 py-3 text-sm text-sky-900',
                        'text-sky-700'
                    );
                    checklistBox = checklistBox || createInsightBox(
                        'data-writing-checklist',
                        config.checklistLabel,
                        'rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-3 text-sm text-indigo-900',
                        'text-indigo-700'
                    );
                }

                if (feedbackEl) {
                    if (parsed.summary) {
                        feedbackEl.textContent = parsed.summary;
                    } else if (parsed.criteria && Object.keys(parsed.criteria).length) {
                        const noteChunks = Object.values(parsed.criteria)
                            .map((item) => item?.notes)
                            .filter(Boolean)
                            .slice(0, 2);
                        if (noteChunks.length) {
                            feedbackEl.textContent = noteChunks.join(' ');
                        }
                    }
                }
                renderAccuracy(
                    toNumberOrNull(parsed.accuracy_percent),
                    toNumberOrNull(parsed.overall_band),
                    toNumberOrNull(parsed.health_index?.overall_percent)
                );
                renderTextErrors(parsed.text_errors || parsed.corrections || []);
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
                if (strengthsBox || weaknessesBox || improvementsBox || checklistBox) {
                    const strengths = Array.isArray(parsed.strengths) ? parsed.strengths : [];
                    const weaknesses = Array.isArray(parsed.weaknesses) ? parsed.weaknesses : [];
                    const improvements = Array.isArray(parsed.improvements) ? parsed.improvements : [];
                    const checklist = Array.isArray(parsed.checklist) ? parsed.checklist : [];

                    const renderList = (wrapper, items, emptyText) => {
                        if (!wrapper) return;
                        while (wrapper.children.length > 1) {
                            wrapper.removeChild(wrapper.lastElementChild);
                        }
                        if (items.length) {
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
                            div.textContent = emptyText;
                            wrapper.appendChild(div);
                        }
                    };

                    renderList(strengthsBox, strengths, config.noStrengths);
                    renderList(weaknessesBox, weaknesses, config.noWeaknesses);
                    renderList(improvementsBox, improvements, config.noImprovements);
                    renderList(checklistBox, checklist, config.noChecklist);
                }
            };

            const buildJsonCandidates = (text) => {
                const trimmed = (text || '').trim();
                if (!trimmed) return [];
                const candidates = [trimmed];
                const fenceStripped = trimmed
                    .replace(/^```(?:json)?\s*/i, '')
                    .replace(/\s*```$/, '')
                    .trim();
                if (fenceStripped && fenceStripped !== trimmed) {
                    candidates.push(fenceStripped);
                }
                const start = trimmed.indexOf('{');
                const end = trimmed.lastIndexOf('}');
                if (start !== -1 && end !== -1 && end > start) {
                    candidates.push(trimmed.slice(start, end + 1));
                }
                if (
                    (trimmed.startsWith('"') && trimmed.endsWith('"'))
                    || (trimmed.startsWith("'") && trimmed.endsWith("'"))
                ) {
                    candidates.push(trimmed.slice(1, -1));
                }
                return Array.from(new Set(candidates));
            };

            const normalizeJsonCandidate = (value) => {
                if (!value) return '';
                return value
                    .replace(/^\uFEFF/, '')
                    .replace(/[\u201C\u201D\u201E\u00AB\u00BB]/g, '"')
                    .replace(/[\u2018\u2019]/g, "'")
                    .replace(/,\s*([}\]])/g, '$1')
                    .replace(/[\u0000-\u001F\u007F]+/g, ' ')
                    .trim();
            };

            const tryParseJson = (value) => {
                try {
                    return JSON.parse(value);
                } catch (e) {
                    return null;
                }
            };

            const parseFeedbackJson = (text) => {
                if (!text) return null;
                const candidates = buildJsonCandidates(text);
                for (const candidate of candidates) {
                    const normalized = normalizeJsonCandidate(candidate);
                    if (!normalized) continue;
                    let parsed = tryParseJson(normalized);
                    if (parsed && typeof parsed === 'object') {
                        return parsed;
                    }
                    if (typeof parsed === 'string') {
                        const nested = tryParseJson(parsed);
                        if (nested && typeof nested === 'object') {
                            return nested;
                        }
                    }
                    const unescaped = normalized
                        .replace(/\\n/g, '\n')
                        .replace(/\\"/g, '"')
                        .replace(/\\'/g, "'");
                    parsed = tryParseJson(unescaped);
                    if (parsed && typeof parsed === 'object') {
                        return parsed;
                    }
                }
                return null;
            };

            const extractSummaryFromText = (text) => {
                if (!text) return null;
                const summaryMatch = text.match(/\"summary\"\\s*:\\s*\"([^\"]+)\"/);
                if (summaryMatch && summaryMatch[1]) {
                    return summaryMatch[1].replace(/\\n/g, ' ').replace(/\\"/g, '"').trim();
                }
                const notes = [];
                const notesRegex = /\"notes\"\\s*:\\s*\"([^\"]+)\"/g;
                let match = notesRegex.exec(text);
                while (match) {
                    notes.push(match[1]);
                    if (notes.length >= 2) break;
                    match = notesRegex.exec(text);
                }
                if (notes.length) {
                    return notes.join(' ').replace(/\\n/g, ' ').replace(/\\"/g, '"').trim();
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

            const extractCriteriaFromText = (text) => {
                if (!text) return null;
                const criteria = {};
                const criteriaRegex = /\"([a-z_]+)\"\\s*:\\s*\\{\\s*\"band\"\\s*:\\s*(?:null|[0-9]+(?:\\.[0-9]+)?)\\s*,\\s*\"notes\"\\s*:\\s*\"([^\"]*)\"/g;
                let match = criteriaRegex.exec(text);
                while (match) {
                    criteria[match[1]] = {
                        notes: match[2].replace(/\\n/g, ' ').replace(/\\"/g, '"').trim(),
                    };
                    match = criteriaRegex.exec(text);
                }
                return Object.keys(criteria).length ? criteria : null;
            };

            const renderCriteriaFromText = (text) => {
                const criteria = extractCriteriaFromText(text);
                if (!criteria) return false;
                let criteriaContainer = criteriaWrapper;
                const feedbackCard = feedbackEl?.closest('.rounded-2xl');
                if (!criteriaContainer && feedbackCard) {
                    const block = document.createElement('div');
                    block.className = 'mt-6 border-t border-slate-100 pt-4';
                    block.innerHTML = `
                        <div class="text-xs font-semibold uppercase text-slate-400">${config.criteriaLabel}</div>
                        <div class="mt-3 space-y-3 text-sm text-slate-700" data-criteria></div>
                    `;
                    feedbackCard.appendChild(block);
                    criteriaContainer = block.querySelector('[data-criteria]');
                }
                if (!criteriaContainer) return false;
                criteriaContainer.innerHTML = '';
                Object.entries(criteria).forEach(([key, item]) => {
                    const div = document.createElement('div');
                    div.className = 'rounded-xl border border-slate-100 bg-slate-50 px-3 py-2';
                    div.innerHTML = `
                        <div class="font-semibold">${key.replace(/_/g, ' ')}</div>
                        <div class="mt-1 text-xs text-slate-600">${item.notes ?? ''}</div>
                    `;
                    criteriaContainer.appendChild(div);
                });
                return true;
            };

            const extractNotesFromText = (text) => {
                if (!text) return [];
                const notes = [];
                const notesRegex = /\"notes\"\\s*:\\s*\"([^\"]+)\"/g;
                let match = notesRegex.exec(text);
                while (match) {
                    notes.push(match[1].replace(/\\n/g, ' ').replace(/\\"/g, '"').trim());
                    match = notesRegex.exec(text);
                }
                return notes.filter(Boolean);
            };

            const renderNotesFromText = (text) => {
                if (!notesContainer || !notesList) return;
                const notes = extractNotesFromText(text);
                notesList.innerHTML = '';
                if (!notes.length) {
                    notesContainer.classList.add('hidden');
                    return;
                }
                notes.forEach((note) => {
                    const li = document.createElement('li');
                    li.textContent = `- ${note}`;
                    notesList.appendChild(li);
                });
                notesContainer.classList.remove('hidden');
            };

            if (config.status === 'done' || config.status === 'failed') {
                const rawText = rawFeedbackEl?.value || '';
                const currentText = rawText || feedbackEl?.textContent || '';
                const parsed = parseFeedbackJson(currentText);
                if (parsed) {
                    renderParsedFeedback(parsed);
                } else {
                    const summary = extractSummaryFromText(currentText);
                    if (summary && feedbackEl) {
                        feedbackEl.textContent = summary;
                    } else if (feedbackEl) {
                        const notes = extractNotesFromText(currentText);
                        if (notes.length) {
                            feedbackEl.textContent = notes.slice(0, 2).join(' ');
                        } else if (currentText.includes('{')) {
                            feedbackEl.textContent = config.feedbackReady || '';
                        }
                    } else if (feedbackEl && currentText.includes('{')) {
                        feedbackEl.textContent = config.feedbackReady || '';
                    }
                    renderNotesFromText(currentText);
                    renderCriteriaFromText(currentText);
                    const accuracyValue = extractAccuracyPercentFromText(currentText);
                    const overallBand = extractOverallBandFromText(currentText);
                    renderAccuracy(accuracyValue, overallBand);
                    renderTextErrors([]);
                }
                if (
                    config.status === 'done'
                    && config.statusUrl
                    && accuracyEl
                    && accuracyEl.textContent?.trim() === '-'
                ) {
                    fetch(config.statusUrl, { headers: { 'Accept': 'application/json' } })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (!data) return;
                            if (typeof data.accuracy_percent === 'number') {
                                renderAccuracy(
                                    data.accuracy_percent,
                                    toNumberOrNull(data.overall_band),
                                    toNumberOrNull(data.health_index?.overall_percent)
                                );
                            }
                            const liveParsed = data.ai_feedback_json || parseFeedbackJson(data.ai_feedback);
                            if (liveParsed) {
                                renderParsedFeedback(liveParsed);
                            }
                        })
                        .catch(() => {
                            // ignore
                        });
                }
                return;
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
                        const hasFeedback = !!(data.ai_feedback && String(data.ai_feedback).trim().length);
                        const hasJson = !!data.ai_feedback_json;
                        if (!hasFeedback && !hasJson) {
                            statusEl.textContent = config.feedbackPending || '';
                            if (feedbackEl) {
                                feedbackEl.textContent = config.feedbackPending || '';
                            }
                            return;
                        }
                        statusEl.textContent = config.feedbackReady;
                        if (feedbackEl) {
                            feedbackEl.textContent = data.ai_feedback || '';
                        }
                        const parsed = data.ai_feedback_json || parseFeedbackJson(data.ai_feedback);
                        if (parsed) {
                            renderParsedFeedback(parsed);
                        } else if (feedbackEl) {
                            const summary = extractSummaryFromText(data.ai_feedback || '');
                            if (summary) {
                                feedbackEl.textContent = summary;
                            } else {
                                feedbackEl.textContent = config.feedbackReady || '';
                            }
                            renderNotesFromText(data.ai_feedback || '');
                            renderCriteriaFromText(data.ai_feedback || '');
                            const accuracyValue = extractAccuracyPercentFromText(data.ai_feedback || '');
                            const overallBand = extractOverallBandFromText(data.ai_feedback || '');
                            renderAccuracy(accuracyValue, overallBand);
                        }
                        if (typeof data.accuracy_percent === 'number') {
                            renderAccuracy(data.accuracy_percent);
                        }
                        clearInterval(window.__writingPoll);
                    } else if (data.status === 'failed') {
                        statusEl.textContent = config.feedbackFailed;
                        feedbackEl.textContent = data.ai_error || config.feedbackFailed;
                        clearInterval(window.__writingPoll);
                    }
                } catch (e) {
                    // ignore
                }
            };

            window.__writingPoll = setInterval(poll, 3000);
            poll();
        })();
    </script>

    <script>
        (() => {
            const container = document.querySelector('[data-writing-help]');
            if (!container) return;

            const csrfToken = container.dataset.csrfToken || '';
            const button = container.querySelector('[data-writing-help-submit]');
            const spinner = container.querySelector('[data-writing-help-spinner]');
            const buttonText = container.querySelector('[data-writing-help-button-text]');
            const input = container.querySelector('[data-writing-help-input]');
            const statusEl = container.querySelector('[data-writing-help-status]');
            const historyEl = container.querySelector('[data-writing-help-history]');
            const createUrl = container.dataset.createUrl;
            const statusBase = container.dataset.statusBase;
            const dailyLimitMessage = container.dataset.dailyLimitMessage || '';
            const readyText = container.dataset.readyText || '';
            const helpFailedText = container.dataset.aiHelpFailed || '';
            const helpPendingText = container.dataset.aiHelpPending || '';
            const askAiText = container.dataset.askAi || '';
            const chatHintText = container.dataset.writingChatHint || '';
            const writingLabel = container.dataset.writingLabel || '';
            const userLabel = container.dataset.userLabel || '';

            const handleErrorResponse = async (response) => {
                let payload = null;
                try {
                    payload = await response.json();
                } catch (e) {
                    payload = null;
                }

                const message = payload?.message || helpFailedText;
                statusEl.textContent = message;
                setLoading(false);

                if (payload?.upgrade_prompt || message === dailyLimitMessage) {
                    window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: writingLabel } }));
                }
            };

            const setLoading = (loading) => {
                if (loading) {
                    container.dataset.pending = '1';
                    button.disabled = true;
                    button.classList.add('opacity-60', 'cursor-not-allowed');
                    spinner.classList.remove('hidden');
                    buttonText.textContent = helpPendingText;
                } else {
                    container.dataset.pending = '0';
                    button.disabled = false;
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                    spinner.classList.add('hidden');
                    buttonText.textContent = askAiText;
                }
            };

            const addMessage = (role, text) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700';
                wrapper.innerHTML = `<div class="text-xs font-semibold text-slate-400">${role}</div><div class="mt-1">${text}</div>`;
                historyEl.prepend(wrapper);
            };

            const pollStatus = (helpId) => {
                const poll = async () => {
                    try {
                        const res = await fetch(`${statusBase}/${helpId}`, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.status === 'done') {
                            const limitNotice = data.limit_notice || container.dataset.limitNotice;
                            statusEl.textContent = limitNotice ? `${readyText} - ${limitNotice}` : readyText;
                            addMessage('AI', data.ai_response || '');
                            setLoading(false);
                            clearInterval(container._poll);
                        } else if (data.status === 'failed') {
                    statusEl.textContent = data.error_message || helpFailedText;
                    setLoading(false);
                    clearInterval(container._poll);
                }
                    } catch (e) {
                        // ignore
                    }
                };
                container._poll = setInterval(poll, 3000);
                poll();
            };

            const submit = async () => {
                if (container.dataset.pending === '1') {
                    return;
                }

                const message = (input.value || '').trim();
                if (!message) {
                    statusEl.textContent = chatHintText;
                    return;
                }

                setLoading(true);
                statusEl.textContent = helpPendingText;
                addMessage(userLabel, message);
                input.value = '';

                try {
                    const res = await fetch(createUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ prompt: message }),
                    });
                    if (!res.ok) {
                        await handleErrorResponse(res);
                        return;
                    }
                    const data = await res.json();
                    if (data.limit_notice) {
                        container.dataset.limitNotice = data.limit_notice;
                    }
                    pollStatus(data.id);
                } catch (e) {
                    statusEl.textContent = helpFailedText;
                    setLoading(false);
                }
            };

            button.addEventListener('click', submit);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    submit();
                }
            });
        })();
    </script>

    <script>
        (() => {
            const container = document.querySelector('[data-mini-practice]');
            if (!container) return;
            const checkBtn = container.querySelector('[data-practice-check]');
            if (!checkBtn) return;

            const normalize = (value) => (value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();

            checkBtn.addEventListener('click', () => {
                const inputs = container.querySelectorAll('[data-correction-input]');
                inputs.forEach((input) => {
                    const answer = normalize(input.dataset.correctionAnswer || '');
                    const value = normalize(input.value || '');
                    const result = input.closest('div')?.parentElement?.querySelector('[data-correction-result]');
                    if (!result) return;
                    if (!answer) {
                        result.textContent = 'Answer not available.';
                        result.className = 'mt-2 text-xs text-slate-500';
                        return;
                    }
                    if (value === answer) {
                        result.textContent = 'Correct ✅';
                        result.className = 'mt-2 text-xs text-emerald-600';
                    } else {
                        result.textContent = `Try again. Correct: ${input.dataset.correctionAnswer}`;
                        result.className = 'mt-2 text-xs text-rose-600';
                    }
                });
            });
        })();
    </script>
</x-layouts.app>
