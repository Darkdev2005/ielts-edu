<x-layouts.app :title="__('app.mock_tests').' · '.__('app.speaking')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking') }} Mock</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.mock_tests_intro') }}</p>
        </div>
        <a href="{{ route('mock.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('app.mock_tests') }}
        </a>
    </div>

    @if(session('status'))
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ session('status') }}</div>
    @endif

    <div class="mt-8 space-y-6">
        @foreach([1, 2, 3] as $part)
            @php($partPrompts = $promptsByPart[$part] ?? collect())
            @if($partPrompts->isNotEmpty())
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Part {{ $part }}</h2>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        @foreach($partPrompts as $prompt)
                            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                                <div class="text-xs font-semibold uppercase text-slate-400">Part {{ $prompt->part }}</div>
                                <div class="mt-2 text-sm text-slate-700">{{ $prompt->prompt }}</div>
                                <div class="mt-3 text-xs text-slate-500">{{ $prompt->difficulty }}</div>
                                @if($canAccess)
                                    <a href="{{ route('mock.speaking.show', $prompt) }}" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                                        {{ __('app.start_mock') }}
                                    </a>
                                @else
                                    <button type="button"
                                        class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700"
                                        @click="$dispatch('open-paywall', { feature: '{{ __('app.mock_tests') }}' })"
                                    >
                                        {{ __('app.upgrade_required') }}
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if($recentSubmissions->isNotEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.recent_attempts') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach($recentSubmissions as $submission)
                    <a href="{{ route('mock.speaking.result', $submission) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm hover:bg-slate-50">
                        <span>Part {{ $submission->prompt?->part }} · {{ \Illuminate\Support\Str::limit((string) $submission->prompt?->prompt, 60) }}</span>
                        <span class="text-xs text-slate-500">{{ $submission->status }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
