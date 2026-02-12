<x-layouts.app :title="__('app.result')">
    @php
        $totalQuestions = $attempt->total > 0 ? $attempt->total : 0;
        $percentage = $totalQuestions > 0 ? (int) round(($attempt->score / $totalQuestions) * 100) : 0;
        $messageKey = 'result_message_ok';
        $tone = 'amber';
        $levelKey = 'level_medium';

        if ($percentage >= 90) {
            $messageKey = 'result_message_high';
            $tone = 'emerald';
            $levelKey = 'level_strong';
        } elseif ($percentage >= 70) {
            $messageKey = 'result_message_good';
            $tone = 'sky';
            $levelKey = 'level_medium';
        } elseif ($percentage < 50) {
            $messageKey = 'result_message_low';
            $tone = 'rose';
            $levelKey = 'level_weak';
        }

        $toneClasses = [
            'emerald' => [
                'border' => 'border-emerald-200',
                'bg' => 'bg-emerald-50',
                'text' => 'text-emerald-800',
                'badge' => 'bg-emerald-100 text-emerald-700',
                'meter' => 'bg-emerald-500',
            ],
            'sky' => [
                'border' => 'border-sky-200',
                'bg' => 'bg-sky-50',
                'text' => 'text-sky-800',
                'badge' => 'bg-sky-100 text-sky-700',
                'meter' => 'bg-sky-500',
            ],
            'amber' => [
                'border' => 'border-amber-200',
                'bg' => 'bg-amber-50',
                'text' => 'text-amber-800',
                'badge' => 'bg-amber-100 text-amber-700',
                'meter' => 'bg-amber-500',
            ],
            'rose' => [
                'border' => 'border-rose-200',
                'bg' => 'bg-rose-50',
                'text' => 'text-rose-800',
                'badge' => 'bg-rose-100 text-rose-700',
                'meter' => 'bg-rose-500',
            ],
        ];

        $toneClass = $toneClasses[$tone] ?? $toneClasses['amber'];
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ __('app.result') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $attempt->lesson->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.completed_at') }}: {{ $attempt->completed_at }}</p>
        </div>
        <div class="flex flex-col items-start gap-3">
            <div class="rounded-2xl bg-slate-900 px-5 py-4 text-sm text-white">
                <div class="text-white/70">{{ __('app.score') }}</div>
                <div class="text-3xl font-semibold">{{ $attempt->score }} / {{ $attempt->total }}</div>
            </div>
            @if($listeningMockBandScore !== null)
                <div class="rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-sm text-slate-700 shadow-sm">
                    <div class="text-xs uppercase text-slate-400">{{ __('app.listening_mock_band_score') }}</div>
                    <div class="text-2xl font-semibold text-slate-900">{{ number_format($listeningMockBandScore, 1) }}</div>
                    <div class="mt-1 text-xs text-slate-400">{{ __('app.listening_mock_band_score_hint', ['score' => $listeningMockRawScore]) }}</div>
                </div>
            @endif
            @if($nextLesson)
                <a href="{{ route('lessons.show', $nextLesson) }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    {{ __('app.next_lesson') }}
                </a>
            @else
                <a href="{{ route('lessons.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('app.browse_lessons') }}
                </a>
            @endif
        </div>
    </div>

    <div class="mt-6 rounded-2xl border p-5 {{ $toneClass['border'] }} {{ $toneClass['bg'] }}">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide {{ $toneClass['text'] }}">{{ __('app.result_feedback') }}</div>
                <div class="mt-2 text-base font-semibold text-slate-900">
                    {{ __('app.' . $messageKey, ['score' => $percentage]) }}
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ __('app.review_lesson_hint') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('lessons.show', $attempt->lesson) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">
                        {{ __('app.review_lesson') }}
                    </a>
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <div class="rounded-full px-3 py-1 text-xs font-semibold {{ $toneClass['badge'] }}">{{ $percentage }}%</div>
                <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-500">
                    <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('app.performance_level') }}</span>
                    <div class="flex items-end gap-1">
                        <span class="h-2 w-1.5 rounded-full {{ $levelKey === 'level_weak' ? $toneClass['meter'] : 'bg-slate-200' }}"></span>
                        <span class="h-3 w-1.5 rounded-full {{ $levelKey !== 'level_weak' ? $toneClass['meter'] : 'bg-slate-200' }}"></span>
                        <span class="h-4 w-1.5 rounded-full {{ $levelKey === 'level_strong' ? $toneClass['meter'] : 'bg-slate-200' }}"></span>
                    </div>
                    <span>{{ __('app.' . $levelKey) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-5">
        @foreach($attempt->answers as $answer)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="text-lg font-semibold text-slate-900">{{ $answer->question->prompt }}</div>
                    <div class="rounded-full px-3 py-1 text-xs font-semibold {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                        {{ $answer->is_correct ? __('app.correct') : __('app.incorrect') }}
                    </div>
                </div>
                @php
                    $questionType = $answer->question->type ?? 'mcq';
                @endphp
                @if($questionType === 'matching')
                    @php
                        $items = (array) data_get($answer->question->meta, 'items', []);
                        $options = (array) $answer->question->options;
                        $selectedMap = json_decode((string) $answer->selected_answer, true) ?: [];
                        $correctMap = json_decode((string) $answer->question->correct_answer, true);
                    @endphp
                    @if(!is_array($correctMap))
                        @php
                            $correctMap = [];
                            $pairs = preg_split('/\s*[|;,]\s*/', (string) $answer->question->correct_answer);
                        @endphp
                        @foreach($pairs as $pair)
                            @if(preg_match('/(\d+)\s*[:=]\s*([A-Za-z]+)/', $pair, $matches))
                                @php
                                    $correctMap[(int) $matches[1]] = strtoupper(substr($matches[2], 0, 1));
                                @endphp
                            @endif
                        @endforeach
                    @endif
                    @php
                        $optionLookup = [];
                    @endphp
                    @foreach($options as $i => $opt)
                        @php
                            $optionLookup[chr(65 + $i)] = $opt;
                        @endphp
                    @endforeach
                    <div class="mt-3 space-y-2 text-sm text-slate-600">
                        @foreach($items as $idx => $item)
                            @php
                                $index = $idx + 1;
                                $selected = $selectedMap[$index] ?? null;
                                $correct = $correctMap[$index] ?? null;
                            @endphp
                            <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                <div class="font-medium text-slate-700">{{ $item }}</div>
                                <div class="mt-1 text-xs">
                                    <span class="font-semibold">{{ __('app.your_answer') }}:</span>
                                    <span class="{{ $selected === $correct ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $selected ? ($selected.'. '.($optionLookup[$selected] ?? '')) : '—' }}
                                    </span>
                                    · <span class="font-semibold">{{ __('app.correct') }}:</span>
                                    {{ $correct ? ($correct.'. '.($optionLookup[$correct] ?? '')) : '—' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif($questionType === 'completion')
                    @php
                        $correctValues = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', (string) $answer->question->correct_answer))));
                    @endphp
                    <div class="mt-3 text-sm text-slate-600">
                        <span class="font-medium">{{ __('app.your_answer') }}:</span>
                        <span class="{{ $answer->is_correct ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $answer->selected_answer ?? '—' }}
                        </span>
                        · <span class="font-medium">{{ __('app.correct') }}:</span>
                        {{ $correctValues ? implode(' / ', $correctValues) : $answer->question->correct_answer }}
                    </div>
                @else
                    <div class="mt-3 text-sm text-slate-600">
                        <span class="font-medium">{{ __('app.your_answer') }}:</span>
                        <span class="{{ $answer->is_correct ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $answer->selected_answer ?? '—' }}
                        </span>
                        · <span class="font-medium">{{ __('app.correct') }}:</span> {{ $answer->question->correct_answer }}
                    </div>
                @endif
                @php
                    $explanation = $answer->ai_explanation ?? $answer->question?->ai_explanation;
                @endphp
                @if(!$answer->is_correct && $explanation)
                    @php
                        $viewer = auth()->user();
                        $canFullExplanation = $viewer && app(\App\Services\FeatureGate::class)->userCan($viewer, 'ai_explanation_full');
                        $lines = preg_split('/\r\n|\r|\n/', trim((string) $explanation));
                        $title = $lines ? array_shift($lines) : null;
                        $bullets = [];
                        $tip = null;
                        foreach ($lines as $line) {
                            $line = trim((string) $line);
                            if ($line === '') {
                                continue;
                            }
                            if (stripos($line, 'Tip:') === 0) {
                                $tip = trim(substr($line, 4));
                                continue;
                            }
                            if (str_starts_with($line, '-')) {
                                $bullets[] = trim(ltrim($line, '- '));
                                continue;
                            }
                            $bullets[] = $line;
                        }
                        $hasStructured = !empty($bullets) || $tip !== null;
                    @endphp
                    <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('app.ai_explanation') }}</div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $canFullExplanation ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $canFullExplanation ? __('app.ai_explanation_full_badge') : __('app.ai_explanation_short_badge') }}
                            </span>
                        </div>
                        @if($hasStructured)
                            @if($title)
                                <div class="mt-2 text-sm font-semibold text-slate-800">{{ $title }}</div>
                            @endif
                            @if(!empty($bullets))
                                <ul class="mt-2 list-disc pl-4 text-sm text-slate-700">
                                    @foreach($bullets as $bullet)
                                        <li>{{ $bullet }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($tip !== null)
                                <div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    <span class="font-semibold">Tip:</span> {{ $tip }}
                                </div>
                            @endif
                        @else
                            <div class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $explanation }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
