<x-layouts.app :title="__('app.speaking')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-purple-700">
                {{ __('app.speaking') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.speaking_preview_intro') }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ __('app.speaking_pro_hint') }}
        </div>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        @foreach($prompts as $prompt)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.preview') }}</div>
                <div class="mt-3 text-sm text-slate-700">{{ $prompt }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <x-locked-feature :label="__('app.start_pro')" />
        <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('app.choose_pro') }}
        </a>
        <a href="{{ route('lessons.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            {{ __('app.back_to_lesson') }}
        </a>
    </div>

    <script>
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: @json(__('app.speaking')) } }));
        });
    </script>
</x-layouts.app>
