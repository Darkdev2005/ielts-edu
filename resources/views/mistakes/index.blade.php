<x-layouts.app :title="__('app.review_mistakes')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-700">
                {{ __('app.review_mistakes') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.review_mistakes') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.review_mistakes_hint') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if($lessonId)
                <a href="{{ route('lessons.show', $lessonId) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    {{ __('app.practice_again') }}
                </a>
            @endif
            <a href="{{ route('lessons.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                {{ __('app.browse_lessons') }}
            </a>
        </div>
    </div>

    <form method="GET" class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 text-sm text-slate-600 shadow-sm md:flex-row md:items-center md:justify-between">
        <div class="flex flex-col gap-2 md:flex-row md:items-center">
            <label class="text-xs text-slate-400">{{ __('app.lesson') }}</label>
            <select name="lesson" class="rounded-xl border border-slate-200 px-3 py-2">
                <option value="">{{ __('app.all') }}</option>
                @foreach($lessons as $lesson)
                    <option value="{{ $lesson->id }}" @selected((string) $lessonId === (string) $lesson->id)>
                        {{ $lesson->title }} ({{ strtoupper($lesson->type) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.filter') }}</button>
            <a href="{{ route('mistakes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.reset') }}</a>
        </div>
    </form>

    <div class="mt-8 space-y-4">
        @forelse($answers as $answer)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs text-slate-400">{{ $answer->attempt?->lesson?->title }}</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ $answer->question?->prompt }}</div>
                    </div>
                    <div class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                        {{ __('app.incorrect') }}
                    </div>
                </div>
                <div class="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-2">
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.your_answer') }}</div>
                        <div class="font-semibold text-slate-800">{{ $answer->selected_answer ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.correct') }}</div>
                        <div class="font-semibold text-slate-800">{{ $answer->question?->correct_answer }}</div>
                    </div>
                </div>
                @if($answer->explanation)
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('app.ai_explanation') }}</div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $canFullExplanation ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $canFullExplanation ? __('app.ai_explanation_full_badge') : __('app.ai_explanation_short_badge') }}
                            </span>
                        </div>
                        @if($answer->explanation_has_structured)
                            @if($answer->explanation_title)
                                <div class="mt-2 text-sm font-semibold text-slate-800">{{ $answer->explanation_title }}</div>
                            @endif
                            @if(!empty($answer->explanation_bullets))
                                <ul class="mt-2 list-disc pl-4 text-sm text-slate-700">
                                    @foreach($answer->explanation_bullets as $bullet)
                                        <li>{{ $bullet }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($answer->explanation_tip !== null)
                                <div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    <span class="font-semibold">Tip:</span> {{ $answer->explanation_tip }}
                                </div>
                            @endif
                        @else
                            <div class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $answer->explanation }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white/80 p-6 text-slate-500">
                {{ __('app.no_mistakes') }}
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $answers->links() }}</div>
</x-layouts.app>
