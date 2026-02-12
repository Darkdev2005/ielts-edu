<x-layouts.app :title="__('app.result')">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ __('app.mock_test') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $test->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.completed_at') }}: {{ $attempt->ended_at }}</p>
        </div>
        <div class="flex flex-col gap-3">
            <div class="rounded-2xl bg-slate-900 px-5 py-4 text-sm text-white">
                <div class="text-white/70">{{ __('app.score') }}</div>
                <div class="text-3xl font-semibold">{{ $attempt->score_raw }} / 40</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/95 px-5 py-4 text-sm text-slate-700 shadow-sm">
                <div class="text-xs uppercase text-slate-400">{{ __('app.listening_mock_band_score') }}</div>
                <div class="text-2xl font-semibold text-slate-900">{{ number_format((float) $attempt->band_score, 1) }}</div>
            </div>
            <a href="{{ route('mock.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ __('app.mock_tests') }}
            </a>
        </div>
    </div>

    <div class="mt-8 space-y-5">
        @foreach($sections as $section)
            @foreach($section->questions as $question)
                @php($answer = $answers->get($question->id))
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="text-lg font-semibold text-slate-900">{{ $question->question_text }}</div>
                        <div class="rounded-full px-3 py-1 text-xs font-semibold {{ $answer?->is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $answer?->is_correct ? __('app.correct') : __('app.incorrect') }}
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-600">
                        <span class="font-medium">{{ __('app.your_answer') }}:</span>
                        <span class="{{ $answer?->is_correct ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $answer?->user_answer ?? '—' }}
                        </span>
                        ·
                        <span class="font-medium">{{ __('app.correct_answer') }}:</span>
                        {{ $question->correct_answer }}
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</x-layouts.app>
