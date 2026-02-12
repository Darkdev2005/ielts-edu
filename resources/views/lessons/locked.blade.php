<x-layouts.app :title="$lesson->title">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                {{ $lesson->type === 'reading' ? __('app.reading') : __('app.listening') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ $lesson->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.difficulty_label') }}: {{ $lesson->difficulty }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
                <div class="text-white/70">{{ __('app.questions_label') }}</div>
                <div class="text-2xl font-semibold">{{ $lesson->questions()->count() }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 text-sm text-slate-600 shadow-sm">
                <div class="text-xs uppercase text-slate-400">{{ __('app.difficulty_label') }}</div>
                <div class="text-xl font-semibold text-slate-900">{{ $lesson->difficulty }}</div>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-slate-700">
        <div class="text-lg font-semibold text-slate-900">{{ __('app.upgrade_required') }}</div>
        <p class="mt-2 text-sm text-slate-600">{{ __('app.paywall_hint') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <x-locked-feature :label="__('app.start_plus')" />
            @php
                $manualPaymentEnabled = (bool) config('subscriptions.manual_payment.card_number');
                $manualPaymentUrl = $manualPaymentEnabled ? route('pricing').'#manual-payment' : route('pricing');
            @endphp
            <a href="{{ $manualPaymentUrl }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ __('app.choose_plus') }}
            </a>
            <a href="{{ route('lessons.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-600 hover:text-slate-900">
                {{ __('app.back_to_lesson') }}
            </a>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            window.dispatchEvent(new CustomEvent('open-paywall', { detail: { feature: @json($featureLabel ?? __('app.upgrade_required')) } }));
        });
    </script>
</x-layouts.app>
