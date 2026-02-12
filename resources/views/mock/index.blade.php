<x-layouts.app :title="__('app.mock_tests')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.mock_tests') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.mock_tests_intro') }}</p>
        </div>
    </div>

    <div class="mt-8 space-y-8">
        @foreach(['listening', 'reading'] as $module)
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-slate-900">{{ $module === 'listening' ? __('app.listening') : __('app.reading') }}</h2>
                    <span class="text-xs text-slate-500">{{ ($testsByModule[$module] ?? collect())->count() }} {{ __('app.mock_tests') }}</span>
                </div>
                <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @forelse(($testsByModule[$module] ?? collect()) as $test)
                        @php($isFreePreview = (int) ($freePreviewTestIds[$module] ?? 0) === (int) $test->id)
                        @php($canStart = !empty($moduleAccess[$module]) || $isFreePreview)
                        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-semibold text-slate-900">{{ $test->title }}</h3>
                                <div class="flex items-center gap-2">
                                    @if($isFreePreview)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold uppercase text-emerald-700">{{ __('app.free_preview') }}</span>
                                    @endif
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase text-slate-600">{{ strtoupper($test->module) }}</span>
                                </div>
                            </div>
                            @if($test->description)
                                <p class="mt-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($test->description, 120) }}</p>
                            @endif
                            <div class="mt-4 flex items-center gap-3 text-xs text-slate-500">
                                <span>{{ $test->sections_count }} {{ __('app.sections') }}</span>
                                <span>·</span>
                                <span>{{ $test->total_questions }} {{ __('app.questions') }}</span>
                                <span>·</span>
                                <span>{{ $test->time_limit }}s</span>
                            </div>
                            @if($canStart)
                                <form method="POST" action="{{ route('mock.start', $test) }}" class="mt-4">
                                    @csrf
                                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.start_mock') }}</button>
                                </form>
                            @else
                                <button type="button"
                                    class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700"
                                    @click="$dispatch('open-paywall', { feature: '{{ __('app.mock_tests') }}' })"
                                >
                                    {{ __('app.upgrade_required') }}
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('app.no_mock_tests') }}</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        <a href="{{ route('mock.writing.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing') }}</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">{{ __('app.mock_tests') }}</div>
            <div class="mt-2 text-sm text-slate-500">Writing mock flow</div>
        </a>
        <a href="{{ route('mock.speaking.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.speaking') }}</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">{{ __('app.mock_tests') }}</div>
            <div class="mt-2 text-sm text-slate-500">Speaking mock flow</div>
        </a>
    </div>

    @if($latestAttempts->isNotEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.recent_attempts') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach($latestAttempts as $attempt)
                    <a href="{{ $attempt->status === 'completed' ? route('mock.attempts.result', $attempt) : route('mock.attempts.show', $attempt) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm hover:bg-slate-50">
                        <span>{{ $attempt->test?->title }}</span>
                        <span class="text-xs text-slate-500">{{ $attempt->status }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
