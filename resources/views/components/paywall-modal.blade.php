<div
    x-show="paywallOpen"
    x-cloak
    class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/70 px-4"
>
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 text-slate-900 shadow-2xl">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('app.upgrade_badge') }}</div>
                <h2 class="mt-2 text-2xl font-semibold">{{ __('app.unlock_feature') }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ __('app.upgrade_to_plus_for') }}
                    <span class="font-semibold text-slate-900" x-text="paywallFeature"></span>
                </p>
            </div>
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold" @click="paywallOpen = false">X</button>
        </div>
        <ul class="mt-4 space-y-2 text-sm text-slate-700">
            @foreach([
                __('app.paywall_benefit_writing'),
                __('app.paywall_benefit_reading'),
                __('app.paywall_benefit_listening'),
                __('app.paywall_benefit_ai_explanation'),
                __('app.paywall_benefit_analytics'),
                __('app.paywall_benefit_mock'),
            ] as $benefit)
                <li class="flex items-center gap-2">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 10l3 3 7-7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    {{ $benefit }}
                </li>
            @endforeach
        </ul>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ __('app.view_plans') }}
            </a>
            @auth
                @if(config('subscriptions.demo_mode'))
                    <button type="button" disabled class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">
                        {{ __('app.demo_mode') }}
                    </button>
                @else
                    @php
                        $manualPaymentEnabled = (bool) config('subscriptions.manual_payment.card_number');
                        $manualPaymentUrl = $manualPaymentEnabled ? route('pricing').'#manual-payment' : null;
                    @endphp
                    @if($manualPaymentEnabled)
                        <a href="{{ $manualPaymentUrl }}" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {{ __('app.start_plus') }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('subscribe.plus') }}">
                            @csrf
                            <button class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                {{ __('app.start_plus') }}
                            </button>
                        </form>
                    @endif
                @endif
            @else
                <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    {{ __('app.login_to_upgrade') }}
                </a>
            @endauth
        </div>
    </div>
</div>
