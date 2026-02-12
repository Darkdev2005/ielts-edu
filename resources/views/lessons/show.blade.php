<x-layouts.app :title="$lesson->title">
    @php($wordCount = $lesson->content_text ? \Illuminate\Support\Str::wordCount($lesson->content_text) : 0)
    @php($estimatedMinutes = $wordCount > 0 ? max(1, (int) ceil($wordCount / 200)) : null)
    @php($audioUrl = $lesson->audio_url)
    @php($transcriptText = $lesson->content_text)
    @php($youtubeId = null)
    @if($audioUrl)
        @php(preg_match('~youtu\.be/([^?&/]+)~', $audioUrl, $ytShort))
        @php(preg_match('~youtube\.com/(?:watch\?v=|embed/)([^?&/]+)~', $audioUrl, $ytLong))
        @php($youtubeId = $ytShort[1] ?? $ytLong[1] ?? null)
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                {{ $lesson->type === 'reading' ? __('app.reading') : __('app.listening') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $lesson->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.difficulty_label') }}: {{ $lesson->difficulty }}</p>
            @if($estimatedMinutes)
                <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ __('app.estimated_time') }}: {{ $estimatedMinutes }} {{ __('app.minutes') }}
                </div>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
                <div class="text-white/70">{{ __('app.questions_label') }}</div>
                <div class="text-2xl font-semibold">{{ $lesson->questions->count() }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 text-sm text-slate-600 shadow-sm">
                <div class="text-xs uppercase text-slate-400">{{ __('app.difficulty_label') }}</div>
                <div class="text-xl font-semibold text-slate-900">{{ $lesson->difficulty }}</div>
            </div>
        </div>
    </div>

    @if($lesson->type === 'reading' && $lesson->content_text)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h2 class="text-sm font-semibold uppercase text-slate-500">{{ __('app.reading') }}</h2>
                <div class="text-xs text-slate-400">{{ $wordCount }} {{ __('app.words') }}</div>
            </div>
            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-5 text-slate-700">
                <p class="whitespace-pre-line leading-relaxed">{{ $lesson->content_text }}</p>
            </div>
        </div>
    @endif

    @if($lesson->type === 'listening' && $audioUrl)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h2 class="text-sm font-semibold uppercase text-slate-500">{{ __('app.listening') }}</h2>
                <div class="text-xs text-slate-400">{{ __('app.audio_url') }}</div>
            </div>
            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-5">
                @if($youtubeId)
                    <div class="aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <iframe
                            class="h-full w-full"
                            src="https://www.youtube.com/embed/{{ $youtubeId }}"
                            title="YouTube video player"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </div>
                    <div class="mt-3 text-xs text-slate-500">
                        {{ __('app.listening_youtube_notice') }}
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-4" data-practice-player>
                        <audio data-practice-audio preload="metadata">
                            <source src="{{ $audioUrl }}">
                        </audio>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-audio-play>
                                {{ __('app.play') }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-audio-replay>
                                {{ __('app.replay') }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-audio-rewind>
                                {{ __('app.rewind_5s') }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-audio-forward>
                                {{ __('app.forward_5s') }}
                            </button>
                            <div class="ml-auto flex items-center gap-2 text-xs text-slate-500">
                                <span>{{ __('app.playback_speed') }}</span>
                                <select class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700" data-audio-speed>
                                    <option value="0.75">0.75x</option>
                                    <option value="1" selected>1.0x</option>
                                    <option value="1.25">1.25x</option>
                                    <option value="1.5">1.5x</option>
                                    <option value="2">2.0x</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <input type="range" min="0" max="100" value="0" step="0.1" class="w-full" data-audio-seek>
                            <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                                <span data-audio-current>0:00</span>
                                <span data-audio-duration>0:00</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-loop-start>
                                {{ __('app.loop_start') }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-loop-end>
                                {{ __('app.loop_end') }}
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" data-loop-clear>
                                {{ __('app.loop_clear') }}
                            </button>
                            <span class="text-[11px] text-slate-500" data-loop-status>{{ __('app.loop_off') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            @if($transcriptText)
                <details class="mt-4 rounded-2xl border border-slate-100 bg-white p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">{{ __('app.show_transcript') }}</summary>
                    <div class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $transcriptText }}</div>
                </details>
            @endif
        </div>
    @endif

    @if($lesson->questions->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-slate-200 bg-white/80 p-6 text-slate-500">
            {{ __('app.questions_pending') }}
        </div>
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
        <form method="POST" action="{{ route('attempts.store', $lesson) }}" class="mt-8 space-y-6" data-lesson-stepper>
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
            @foreach($lesson->questions as $index => $question)
                @php($help = $helpByQuestion->get($question->id))
                @php($questionType = $question->type ?? 'mcq')
                @php($typeLabel = match ($questionType) {
                    'tfng' => 'TFNG',
                    'completion' => 'Completion',
                    'matching' => 'Matching',
                    default => __('app.multiple_choice'),
                })
                @php($options = is_array($question->options) ? $question->options : [])
                @if($questionType === 'tfng' && empty($options))
                    @php($options = ['TRUE', 'FALSE', 'NOT GIVEN'])
                @endif
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm" data-question-step data-step-index="{{ $index }}">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-400">Q{{ $index + 1 }}</div>
                        <div class="text-xs text-slate-400">{{ $typeLabel }}</div>
                    </div>
                    <div class="mb-4 text-lg font-semibold text-slate-900">{{ $question->prompt }}</div>
                    @switch($questionType)
                        @case('completion')
                            <div>
                                <input
                                    type="text"
                                    name="answers[{{ $question->id }}]"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700"
                                    value="{{ old('answers.'.$question->id) }}"
                                    placeholder="Write your answer..."
                                    required
                                >
                            </div>
                            @break
                        @case('matching')
                            @php($items = (array) data_get($question->meta, 'items', []))
                            <div class="grid gap-3">
                                @foreach($items as $itemIndex => $item)
                                    @php($itemKey = $itemIndex + 1)
                                    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                        <div class="font-semibold text-slate-800">{{ $item }}</div>
                                        <select
                                            name="answers[{{ $question->id }}][{{ $itemKey }}]"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                            required
                                        >
                                            <option value="">Select option...</option>
                                            @foreach($options as $i => $option)
                                                @php($letter = chr(65 + $i))
                                                <option value="{{ $letter }}" @selected(old('answers.'.$question->id.'.'.$itemKey) === $letter)>
                                                    {{ $letter }}. {{ $option }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                            @break
                        @case('tfng')
                            <div class="grid gap-3">
                                @foreach($options as $i => $option)
                                    @php($value = strtoupper(trim($option)) ?: $option)
                                    @php($label = $value === 'NOT GIVEN' ? 'NG' : strtoupper(substr($value, 0, 1)))
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                        <input
                                            type="radio"
                                            name="answers[{{ $question->id }}]"
                                            value="{{ $value }}"
                                            class="peer sr-only"
                                            @checked(old('answers.'.$question->id) === $value)
                                            @if($loop->first) required @endif
                                        >
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                            {{ $label }}
                                        </span>
                                        <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break
                        @default
                            <div class="grid gap-3">
                                @foreach($options as $i => $option)
                                    @php($label = chr(65 + $i))
                                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                        <input
                                            type="radio"
                                            name="answers[{{ $question->id }}]"
                                            value="{{ $label }}"
                                            class="peer sr-only"
                                            @checked(old('answers.'.$question->id) === $label)
                                            @if($loop->first) required @endif
                                        >
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-xs font-semibold text-slate-600 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white">
                                            {{ $label }}
                                        </span>
                                        <span class="text-sm text-slate-700 peer-checked:text-slate-900">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                    @endswitch

                    <div class="mt-5" data-stepper-controls-slot></div>

                    <div
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"
                        data-ai-help
                        data-question-id="{{ $question->id }}"
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

    <script type="application/json" id="lesson-i18n">
        {!! json_encode([
            'ai_help_pending' => __('app.ai_help_pending'),
            'ai_help_failed' => __('app.ai_help_failed'),
            'ai_help_done' => __('app.ai_help_done'),
            'ask_ai_hint' => __('app.ask_ai_hint'),
            'select_option_required' => __('app.select_option_required'),
            'ask_ai' => __('app.ask_ai'),
            'daily_limit_reached' => __('app.daily_limit_reached'),
            'question_progress' => __('app.question_progress', ['current' => ':current', 'total' => ':total']),
            'play' => __('app.play'),
            'pause' => __('app.pause'),
            'loop_off' => __('app.loop_off'),
            'loop_on' => __('app.loop_on'),
            'loop_invalid' => __('app.loop_invalid'),
            'loop_need_start' => __('app.loop_need_start'),
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>

    <script>
        (() => {
            const i18nEl = document.getElementById('lesson-i18n');
            const i18n = i18nEl ? JSON.parse(i18nEl.textContent) : {};

            const formatTime = (value) => {
                if (!Number.isFinite(value)) {
                    return '0:00';
                }
                const minutes = Math.floor(value / 60);
                const seconds = Math.floor(value % 60).toString().padStart(2, '0');
                return `${minutes}:${seconds}`;
            };

            const players = Array.from(document.querySelectorAll('[data-practice-player]'));
            players.forEach((player) => {
                const audio = player.querySelector('[data-practice-audio]');
                if (!audio) {
                    return;
                }

                const playBtn = player.querySelector('[data-audio-play]');
                const replayBtn = player.querySelector('[data-audio-replay]');
                const rewindBtn = player.querySelector('[data-audio-rewind]');
                const forwardBtn = player.querySelector('[data-audio-forward]');
                const speedSelect = player.querySelector('[data-audio-speed]');
                const seek = player.querySelector('[data-audio-seek]');
                const currentEl = player.querySelector('[data-audio-current]');
                const durationEl = player.querySelector('[data-audio-duration]');
                const loopStartBtn = player.querySelector('[data-loop-start]');
                const loopEndBtn = player.querySelector('[data-loop-end]');
                const loopClearBtn = player.querySelector('[data-loop-clear]');
                const loopStatus = player.querySelector('[data-loop-status]');

                let loopStart = null;
                let loopEnd = null;

                const setLoopStatus = (text) => {
                    if (loopStatus) {
                        loopStatus.textContent = text;
                    }
                };

                const updateLoopStatus = () => {
                    if (loopStart === null || loopEnd === null) {
                        setLoopStatus(i18n.loop_off || '{{ __('app.loop_off') }}');
                        return;
                    }

                    if (loopEnd <= loopStart) {
                        setLoopStatus(i18n.loop_invalid || '{{ __('app.loop_invalid') }}');
                        return;
                    }

                    const label = `${formatTime(loopStart)} - ${formatTime(loopEnd)}`;
                    setLoopStatus((i18n.loop_on || '{{ __('app.loop_on') }}') + ' ' + label);
                };

                const updatePlayLabel = () => {
                    if (!playBtn) return;
                    playBtn.textContent = audio.paused ? (i18n.play || '{{ __('app.play') }}') : (i18n.pause || '{{ __('app.pause') }}');
                };

                const updateSeek = () => {
                    if (!seek) return;
                    if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
                        seek.value = 0;
                        return;
                    }
                    seek.max = audio.duration;
                    seek.value = audio.currentTime;
                };

                audio.addEventListener('loadedmetadata', () => {
                    if (durationEl) {
                        durationEl.textContent = formatTime(audio.duration);
                    }
                    updateSeek();
                });

                audio.addEventListener('timeupdate', () => {
                    if (currentEl) {
                        currentEl.textContent = formatTime(audio.currentTime);
                    }

                    updateSeek();

                    if (loopStart !== null && loopEnd !== null && loopEnd > loopStart) {
                        if (audio.currentTime >= loopEnd) {
                            audio.currentTime = loopStart;
                        }
                    }
                });

                audio.addEventListener('play', updatePlayLabel);
                audio.addEventListener('pause', updatePlayLabel);
                audio.addEventListener('ended', updatePlayLabel);

                if (playBtn) {
                    playBtn.addEventListener('click', () => {
                        if (audio.paused) {
                            audio.play().catch(() => {});
                        } else {
                            audio.pause();
                        }
                    });
                }

                if (replayBtn) {
                    replayBtn.addEventListener('click', () => {
                        audio.currentTime = 0;
                        audio.play().catch(() => {});
                    });
                }

                if (rewindBtn) {
                    rewindBtn.addEventListener('click', () => {
                        audio.currentTime = Math.max(0, audio.currentTime - 5);
                    });
                }

                if (forwardBtn) {
                    forwardBtn.addEventListener('click', () => {
                        audio.currentTime = Math.min(audio.duration || audio.currentTime + 5, audio.currentTime + 5);
                    });
                }

                if (speedSelect) {
                    speedSelect.addEventListener('change', () => {
                        const rate = parseFloat(speedSelect.value);
                        audio.playbackRate = Number.isFinite(rate) ? rate : 1;
                    });
                }

                if (seek) {
                    seek.addEventListener('input', () => {
                        const value = parseFloat(seek.value);
                        if (Number.isFinite(value)) {
                            audio.currentTime = value;
                        }
                    });
                }

                if (loopStartBtn) {
                    loopStartBtn.addEventListener('click', () => {
                        loopStart = audio.currentTime;
                        if (loopEnd !== null && loopEnd <= loopStart) {
                            loopEnd = null;
                        }
                        updateLoopStatus();
                    });
                }

                if (loopEndBtn) {
                    loopEndBtn.addEventListener('click', () => {
                        if (loopStart === null) {
                            setLoopStatus(i18n.loop_need_start || '{{ __('app.loop_need_start') }}');
                            return;
                        }
                        loopEnd = audio.currentTime;
                        updateLoopStatus();
                    });
                }

                if (loopClearBtn) {
                    loopClearBtn.addEventListener('click', () => {
                        loopStart = null;
                        loopEnd = null;
                        updateLoopStatus();
                    });
                }

                updatePlayLabel();
                updateLoopStatus();
            });
        })();
    </script>

    <script>
        (() => {
            const csrfToken = '{{ csrf_token() }}';
            const i18nEl = document.getElementById('lesson-i18n');
            const i18n = i18nEl ? JSON.parse(i18nEl.textContent) : {};
            const texts = {
                pending: i18n.ai_help_pending || '',
                failed: i18n.ai_help_failed || '',
                done: i18n.ai_help_done || '',
                empty: i18n.ask_ai_hint || '',
                askAi: i18n.ask_ai || '',
                dailyLimit: i18n.daily_limit_reached || '',
            };
            const requiredMessage = i18n.select_option_required || '';
            const containers = Array.from(document.querySelectorAll('[data-ai-help]'));

            const setStatus = (container, state, message) => {
                const statusEl = container.querySelector('[data-ai-help-status]');
                if (!statusEl) {
                    return;
                }

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
                if (!button || !spinner || !buttonText) {
                    return;
                }

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
                    buttonText.textContent = texts.askAi;
                }
            };

            const startPolling = (container) => {
                if (container._aiPoll) {
                    return;
                }

                const poll = async () => {
                    const helpId = container.dataset.helpId;
                    if (!helpId || !['queued', 'processing'].includes(container.dataset.status)) {
                        return;
                    }

                    try {
                        const statusUrl = `${container.dataset.statusBase}/${helpId}`;
                        const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) {
                            return;
                        }
                        const payload = await response.json();
                        updateFromStatus(container, payload);
                    } catch (e) {
                        // Ignore transient errors; we'll retry on next tick.
                    }
                };

                container._aiPoll = setInterval(poll, 3000);
                poll();
            };

            const updateFromStatus = (container, payload) => {
                if (!payload || !payload.status) {
                    return;
                }

                const responseEl = container.querySelector('[data-ai-help-response]');

                container.dataset.status = payload.status;

                if (payload.status === 'done') {
                    const limitNotice = payload.limit_notice || container.dataset.limitNotice;
                    const doneMessage = limitNotice ? `${texts.done} · ${limitNotice}` : texts.done;
                    responseEl.textContent = payload.ai_response || '';
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

            containers.forEach((container) => {
                const button = container.querySelector('[data-ai-help-submit]');
                const input = container.querySelector('[data-ai-help-input]');

                const handleErrorResponse = async (response, responseEl) => {
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

                    if (payload?.upgrade_prompt || message === texts.dailyLimit) {
                        window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: texts.askAi || 'AI' } }));
                    }
                };

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
                                'X-CSRF-TOKEN': csrfToken,
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
                        startPolling(container);
                    } catch (e) {
                        responseEl.textContent = texts.failed;
                        setStatus(container, 'failed', texts.failed);
                        setLoading(container, false);
                    }
                };

                button.addEventListener('click', async () => {
                    submit();
                });

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

            const requiredRadios = Array.from(document.querySelectorAll('input[type="radio"][required]'));
            requiredRadios.forEach((radio) => {
                radio.addEventListener('invalid', (event) => {
                    event.target.setCustomValidity(requiredMessage);
                });
            });

            const allRadios = Array.from(document.querySelectorAll('input[type="radio"][name^="answers["]'));
            allRadios.forEach((radio) => {
                radio.addEventListener('change', (event) => {
                    const name = event.target.name;
                    allRadios
                        .filter((item) => item.name === name)
                        .forEach((item) => item.setCustomValidity(''));
                });
            });
            const allAnswerInputs = Array.from(document.querySelectorAll('[name^="answers["]'));
            allAnswerInputs
                .filter((input) => input.type !== 'radio')
                .forEach((input) => {
                    input.addEventListener('input', () => input.setCustomValidity(''));
                    input.addEventListener('change', () => input.setCustomValidity(''));
                });

            const stepperForm = document.querySelector('[data-lesson-stepper]');
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
            const progressTemplate = i18n.question_progress || 'Question :current of :total';

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

            const radiosForStep = (step) =>
                inputsForStep(step).filter((input) => input.type === 'radio');

            const isAnswered = (step) => {
                const inputs = inputsForStep(step);
                if (!inputs.length) return true;
                const radios = radiosForStep(step);
                if (radios.length) {
                    return radios.some((radio) => radio.checked);
                }
                return inputs.every((input) => {
                    if (input.tagName === 'SELECT') {
                        return input.value !== '';
                    }
                    return String(input.value || '').trim() !== '';
                });
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
