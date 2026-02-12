<x-layouts.app :title="__('app.result')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $user->name }}</h1>
            <p class="text-sm text-slate-500">{{ $attempt->lesson->title }}</p>
        </div>
        <a href="{{ route('admin.results.show', $user) }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_results') }}</a>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="text-sm text-slate-500">{{ __('app.score') }}</div>
        <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $attempt->score }} / {{ $attempt->total }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ __('app.completed_at') }}: {{ $attempt->completed_at }}</div>
    </div>

    @if(session('status') === 'explanation-regenerating')
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {{ __('app.explanation_regenerating') }}
        </div>
    @endif

    <div class="mt-8 space-y-4">
        @foreach($attempt->answers as $answer)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="text-lg font-semibold text-slate-900">{{ $answer->question->prompt }}</div>
                    <div class="rounded-full px-3 py-1 text-xs font-semibold {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                        {{ $answer->is_correct ? __('app.correct') : __('app.incorrect') }}
                    </div>
                </div>
                <div class="mt-3 text-sm text-slate-600">
                    <span class="font-medium">{{ __('app.your_answer') }}:</span>
                    <span class="{{ $answer->is_correct ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $answer->selected_answer ?? '-' }}
                    </span>
                    / <span class="font-medium">{{ __('app.correct') }}:</span> {{ $answer->question->correct_answer }}
                </div>
                @if(!$answer->is_correct)
                    <div
                        class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"
                        data-answer-id="{{ $answer->id }}"
                        data-ai-pending="{{ $answer->ai_explanation ? '0' : '1' }}"
                    >
                        <span data-ai-text>
                            {{ $answer->ai_explanation ?? __('app.ai_pending') }}
                        </span>
                        <span
                            data-ai-loading-indicator
                            class="ml-2 inline-block h-2 w-2 animate-ping rounded-full bg-slate-400 align-middle {{ $answer->ai_explanation ? 'hidden' : '' }}"
                            aria-hidden="true"
                        ></span>
                    </div>
                    <form method="POST" action="{{ route('admin.results.regenerate', ['user' => $user->id, 'attempt' => $attempt->id, 'answer' => $answer->id]) }}" class="mt-3">
                        @csrf
                        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('app.regenerate_explanation') }}
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        (() => {
            const pending = Array.from(document.querySelectorAll('[data-ai-pending="1"]'));
            if (!pending.length) return;

            const statusUrl = @json(route('admin.results.answers.status', [$user, $attempt]));

            const refresh = async () => {
                try {
                    const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const payload = await response.json();
                    const answers = Array.isArray(payload.answers) ? payload.answers : [];
                    const byId = new Map(answers.map((item) => [String(item.id), item.ai_explanation]));

                    let remaining = 0;
                    pending.forEach((el) => {
                        if (el.dataset.aiPending !== '1') {
                            return;
                        }
                        const text = byId.get(el.dataset.answerId);
                        const textEl = el.querySelector('[data-ai-text]');
                        const indicator = el.querySelector('[data-ai-loading-indicator]');
                        if (text) {
                            if (textEl) {
                                textEl.textContent = text;
                            } else {
                                el.textContent = text;
                            }
                            el.dataset.aiPending = '0';
                            if (indicator) {
                                indicator.classList.add('hidden');
                            }
                        } else {
                            remaining += 1;
                            if (indicator) {
                                indicator.classList.remove('hidden');
                            }
                        }
                    });

                    if (remaining === 0) {
                        clearInterval(timer);
                    }
                } catch (error) {
                    // Ignore transient errors and try again on next tick.
                }
            };

            const timer = setInterval(refresh, 3000);
            refresh();
        })();
    </script>
</x-layouts.app>



