<x-layouts.app :title="__('app.speaking')">
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-slate-700">
        <div class="text-lg font-semibold text-slate-900">{{ __('app.upgrade_required') }}</div>
        <p class="mt-2 text-sm text-slate-600">{{ __('app.speaking_pro_hint') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <x-locked-feature :label="__('app.start_pro')" />
            <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ __('app.choose_pro') }}
            </a>
            <a href="{{ route('lessons.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-600 hover:text-slate-900">
                {{ __('app.back_to_lesson') }}
            </a>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: @json(__('app.speaking')) } }));
        });
    </script>
</x-layouts.app>
