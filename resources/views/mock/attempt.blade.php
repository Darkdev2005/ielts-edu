<x-layouts.app :title="$test->title">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_test') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $test->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ strtoupper($test->module) }} · {{ $test->total_questions }} {{ __('app.questions') }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-5 py-4 text-sm text-white">
            <div class="text-white/70">{{ __('app.time_limit_seconds') }}</div>
            <div class="mt-1 text-3xl font-semibold" data-mock-timer>{{ gmdate('i:s', $remainingSeconds) }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('mock.attempts.submit', $attempt) }}" class="mt-8 space-y-6" id="mock-attempt-form">
        @csrf
        <input type="hidden" name="_mode" value="mock">

        @foreach($sections as $section)
            <section
                class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm {{ $test->module === 'listening' && $loop->index > 0 ? 'hidden' : '' }}"
                data-mock-section
                data-section-index="{{ $loop->index }}"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('app.section_number') }} {{ $section->section_number }} {{ $section->title ? '· '.$section->title : '' }}</h2>
                    <span class="text-xs text-slate-500">{{ $section->questions->count() }} {{ __('app.questions') }}</span>
                </div>

                @if($test->module === 'listening' && $section->audio_url)
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <audio class="w-full" controls controlsList="nodownload noplaybackrate" preload="metadata" data-listening-audio>
                            <source src="{{ $section->audio_url }}">
                        </audio>
                    </div>
                @endif

                @if($test->module === 'reading' && $section->passage_text)
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="whitespace-pre-line leading-relaxed">{{ $section->passage_text }}</p>
                    </div>
                @endif

                <div class="mt-5 space-y-5">
                    @foreach($section->questions as $question)
                        @php($savedAnswer = $answers->get($question->id)?->user_answer)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-900">Q{{ $question->order_index }}. {{ $question->question_text }}</div>

                            @if($question->question_type === 'mcq')
                                <div class="mt-3 space-y-2">
                                    @foreach(['A', 'B', 'C', 'D'] as $opt)
                                        @php($optionText = $question->options_json[$opt] ?? '')
                                        <label class="flex items-start gap-2 text-sm text-slate-700">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $opt }}" @checked(old('answers.'.$question->id, $savedAnswer) === $opt)>
                                            <span><strong>{{ $opt }}.</strong> {{ $optionText }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif($question->question_type === 'tfng')
                                <div class="mt-3 flex flex-wrap gap-3 text-sm text-slate-700">
                                    @foreach(['TRUE', 'FALSE', 'NOT_GIVEN'] as $opt)
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $opt }}" @checked(old('answers.'.$question->id, $savedAnswer) === $opt)>
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif($question->question_type === 'ynng')
                                <div class="mt-3 flex flex-wrap gap-3 text-sm text-slate-700">
                                    @foreach(['YES', 'NO', 'NOT_GIVEN'] as $opt)
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $opt }}" @checked(old('answers.'.$question->id, $savedAnswer) === $opt)>
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input
                                    type="text"
                                    name="answers[{{ $question->id }}]"
                                    value="{{ old('answers.'.$question->id, $savedAnswer) }}"
                                    class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="{{ __('app.your_answer') }}"
                                >
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($test->module === 'listening' && !$loop->last)
                    <div class="mt-4 text-right">
                        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-next-section>
                            {{ __('app.next_question') }}
                        </button>
                    </div>
                @endif
            </section>
        @endforeach

        <div class="flex justify-end">
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">{{ __('app.submit_answers') }}</button>
        </div>
    </form>

    <script>
        (() => {
            const form = document.getElementById('mock-attempt-form');
            const timerEl = document.querySelector('[data-mock-timer]');
            if (!form || !timerEl) return;

            let remaining = {{ $remainingSeconds }};
            const tick = () => {
                const min = String(Math.floor(remaining / 60)).padStart(2, '0');
                const sec = String(remaining % 60).padStart(2, '0');
                timerEl.textContent = `${min}:${sec}`;
                if (remaining <= 0) {
                    form.submit();
                    return;
                }
                remaining -= 1;
                setTimeout(tick, 1000);
            };
            tick();

            const sections = Array.from(document.querySelectorAll('[data-mock-section]'));
            const nextButtons = Array.from(document.querySelectorAll('[data-next-section]'));
            if (nextButtons.length) {
                let current = 0;
                nextButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!sections[current + 1]) return;
                        sections[current].classList.add('hidden');
                        current += 1;
                        sections[current].classList.remove('hidden');
                        sections[current].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            }

            const audios = document.querySelectorAll('[data-listening-audio]');
            audios.forEach((audio) => {
                audio.addEventListener('pause', () => {
                    if (audio.ended) return;
                    audio.play().catch(() => {});
                });
                audio.addEventListener('seeking', () => {
                    audio.currentTime = Math.max(0, audio.currentTime - 1);
                });
            });
        })();
    </script>
</x-layouts.app>
