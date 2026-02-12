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
                {{ __('app.grammar') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $attempt->topic->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.completed_at') }}: {{ $attempt->completed_at }}</p>
        </div>
        <div class="flex flex-col items-start gap-3">
            <div class="rounded-2xl bg-slate-900 px-5 py-4 text-sm text-white">
                <div class="text-white/70">{{ __('app.score') }}</div>
                <div class="text-3xl font-semibold">{{ $attempt->score }} / {{ $attempt->total }}</div>
            </div>
            @if($nextTopic)
                <a href="{{ route('grammar.show', $nextTopic) }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    {{ __('app.next_grammar_topic') }}
                </a>
            @else
                <a href="{{ route('grammar.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('app.browse_grammar') }}
                </a>
            @endif
        </div>
    </div>

    <div class="mt-8 space-y-5">
        <div class="rounded-2xl border p-5 {{ $toneClass['border'] }} {{ $toneClass['bg'] }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide {{ $toneClass['text'] }}">{{ __('app.result_feedback') }}</div>
                    <div class="mt-2 text-base font-semibold text-slate-900">
                        {{ __('app.' . $messageKey, ['score' => $percentage]) }}
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ __('app.review_rules_hint') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('grammar.show', $attempt->topic) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">
                            {{ __('app.review_rules') }}
                        </a>
                        <a href="{{ route('grammar.practice', $attempt->topic) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">
                            {{ __('app.practice_again') }}
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

        @foreach($attempt->answers as $answer)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                @php
                    $exercise = $answer->exercise;
                    $type = strtolower($exercise->exercise_type ?? $exercise->type ?? 'mcq');
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
                    $isCorrect = (bool) $answer->is_correct;
                    $selectedRaw = trim((string) ($answer->selected_answer ?? ''));
                    $correctRaw = trim((string) ($exercise->correct_answer ?? ''));

                    $formatOption = function (string $letter) use ($options) {
                        $letter = strtoupper($letter);
                        if (isset($options[$letter])) {
                            return $letter.'. '.$options[$letter];
                        }
                        return $letter;
                    };

                    $formatBoolean = function (string $value) {
                        return strtolower($value) === 'true' ? __('app.true_label') : __('app.false_label');
                    };

                    if ($type === 'mcq') {
                        $selectedDisplay = $selectedRaw !== '' ? $formatOption($selectedRaw) : '';
                        $correctDisplay = $correctRaw !== '' ? $formatOption($correctRaw) : '';
                    } elseif ($type === 'tf') {
                        $selectedDisplay = $selectedRaw !== '' ? $formatBoolean($selectedRaw) : '';
                        $correctDisplay = $correctRaw !== '' ? $formatBoolean($correctRaw) : '';
                    } else {
                        $selectedDisplay = $selectedRaw;
                        $correctDisplay = $correctRaw;
                    }

                    $explanation = $exercise->localizedExplanation();
                @endphp
                <div class="flex items-start justify-between gap-4">
                    <div class="text-lg font-semibold text-slate-900">{{ $exercise->question ?? $exercise->prompt }}</div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isCorrect ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $isCorrect ? __('app.correct') : __('app.incorrect') }}
                        </span>
                    </div>
                </div>
                <div class="mt-3 text-sm text-slate-600">
                    <div>{{ __('app.your_answer') }}: <span class="font-semibold text-slate-800">{{ $selectedDisplay !== '' ? $selectedDisplay : '—' }}</span></div>
                    @if(!$isCorrect)
                        <div class="mt-1">{{ __('app.correct_answer') }}: <span class="font-semibold text-slate-800">{{ $correctDisplay !== '' ? $correctDisplay : '—' }}</span></div>
                    @endif
                </div>
                @if(!$isCorrect && $explanation)
                    <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ $explanation }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
