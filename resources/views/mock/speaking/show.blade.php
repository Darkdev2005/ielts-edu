<x-layouts.app :title="__('app.speaking').' Mock'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking') }} Mock</h1>
            <p class="mt-2 text-sm text-slate-500">Part {{ $prompt->part }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 text-sm text-slate-600 shadow-sm">
            <div class="text-xs uppercase text-slate-400">{{ __('app.difficulty_label') }}</div>
            <div class="text-xl font-semibold text-slate-900">{{ $prompt->difficulty }}</div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="text-sm font-semibold text-slate-600">Prompt</div>
        <div class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $prompt->prompt }}</div>
    </div>

    <form method="POST" action="{{ route('mock.speaking.submit', $prompt) }}" enctype="multipart/form-data" class="mt-8 space-y-4">
        @csrf
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_your_response') }}</div>
            <textarea
                name="response_text"
                rows="8"
                class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                placeholder="Speak and type your response..."
            >{{ old('response_text') }}</textarea>
            @error('response_text')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-4">
                <label class="block text-xs font-semibold uppercase text-slate-500">Audio (optional)</label>
                <input type="file" name="audio" accept="audio/*" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-lg">
                {{ __('app.submit_answers') }}
            </button>
            @if($latestSubmission)
                <a href="{{ route('mock.speaking.result', $latestSubmission) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                    {{ __('app.result') }}
                </a>
            @endif
            <a href="{{ route('mock.speaking.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                {{ __('app.back_to_list') }}
            </a>
        </div>
    </form>
</x-layouts.app>
