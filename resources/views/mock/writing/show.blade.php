<x-layouts.app :title="$task->title">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $task->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">Writing · Mock</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 text-sm text-slate-600 shadow-sm">
                <div class="text-xs uppercase text-slate-400">{{ __('app.difficulty_label') }}</div>
                <div class="text-xl font-semibold text-slate-900">{{ $task->difficulty }}</div>
            </div>
            <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
                <div class="text-white/70">{{ __('app.writing_word_target') }}</div>
                <div class="text-2xl font-semibold">{{ $task->min_words ? $task->min_words.'+' : __('app.writing_no_limit') }}</div>
            </div>
        </div>
    </div>

    @if($task->time_limit_minutes)
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <span class="font-semibold">Timer:</span>
            <span data-mock-writing-timer data-seconds="{{ (int) $task->time_limit_minutes * 60 }}"></span>
        </div>
    @endif

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="text-sm font-semibold uppercase text-slate-500">{{ __('app.writing_prompt') }}</div>
        <div class="mt-4 whitespace-pre-line text-slate-700">{{ $task->prompt }}</div>
    </div>

    <form method="POST" action="{{ route('mock.writing.submit', $task) }}" class="mt-8 space-y-4" id="mock-writing-form">
        @csrf
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_response') }}</div>
                <div class="text-xs text-slate-400" data-word-count>0 {{ __('app.words') }}</div>
            </div>
            <textarea
                name="response_text"
                rows="12"
                class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                placeholder="{{ __('app.writing_placeholder') }}"
            >{{ old('response_text') }}</textarea>
            @error('response_text')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-lg">
                {{ __('app.submit_answers') }}
            </button>
            @if($latestSubmission)
                <a href="{{ route('mock.writing.result', $latestSubmission) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                    {{ __('app.result') }}
                </a>
            @endif
            <a href="{{ route('mock.writing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                {{ __('app.back_to_list') }}
            </a>
        </div>
    </form>

    <script>
        (() => {
            const textarea = document.querySelector('textarea[name="response_text"]');
            const counter = document.querySelector('[data-word-count]');
            if (textarea && counter) {
                const update = () => {
                    const words = textarea.value.trim().split(/\s+/).filter(Boolean).length;
                    counter.textContent = `${words} {{ __('app.words') }}`;
                };
                textarea.addEventListener('input', update);
                update();
            }

            const timerEl = document.querySelector('[data-mock-writing-timer]');
            const form = document.getElementById('mock-writing-form');
            if (!timerEl || !form) return;

            let left = Number(timerEl.dataset.seconds || 0);
            const render = () => {
                const m = Math.floor(left / 60).toString().padStart(2, '0');
                const s = (left % 60).toString().padStart(2, '0');
                timerEl.textContent = `${m}:${s}`;
            };

            render();
            const tick = setInterval(() => {
                left -= 1;
                if (left <= 0) {
                    clearInterval(tick);
                    render();
                    form.submit();
                    return;
                }
                render();
            }, 1000);
        })();
    </script>
</x-layouts.app>
