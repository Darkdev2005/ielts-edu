@props([
    'plan',
    'currentPlan' => null,
])

@php
    $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
    $comingSoon = !$plan->is_active;
    $isPaidPlan = (bool) $plan->price_monthly;
    $showYearly = $isPaidPlan && in_array($plan->slug, ['plus', 'pro'], true);
    $yearlyMonths = (int) config('subscriptions.yearly_offer.months', 12);
    $yearlyDiscount = (int) config('subscriptions.yearly_offer.discount_percent', 0);
    $yearlyTotal = $isPaidPlan
        ? (int) round($plan->price_monthly * $yearlyMonths * (100 - $yearlyDiscount) / 100)
        : 0;
    $manualPaymentEnabled = (bool) config('subscriptions.manual_payment.card_number');
    $manualPaymentUrl = $manualPaymentEnabled ? route('pricing').'#manual-payment' : null;
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">{{ $plan->name }}</h3>
        @if($isCurrent)
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ __('app.current_plan') }}</span>
        @elseif($comingSoon)
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('app.coming_soon') }}</span>
        @endif
    </div>
    <div class="mt-4 text-3xl font-semibold text-slate-900">
        @if($plan->price_monthly)
            {{ number_format($plan->price_monthly, 0, '.', ' ') }} UZS
            <span class="text-sm font-medium text-slate-500">/ {{ __('app.per_month') }}</span>
        @else
            {{ __('app.free') }}
        @endif
    </div>
    @if($showYearly)
        <div class="mt-2 text-xs text-slate-500">
            {{ __('app.yearly_offer') }}: {{ number_format($yearlyTotal, 0, '.', ' ') }} UZS / {{ __('app.per_year') }}
            @if($yearlyDiscount > 0)
                <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                    {{ __('app.save_percent', ['percent' => $yearlyDiscount]) }}
                </span>
            @endif
        </div>
    @endif
    <ul class="mt-4 space-y-2 text-sm text-slate-600">
        @foreach($plan->features as $feature)
            <li class="flex items-center gap-2">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 10l3 3 7-7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                {{ $feature->name }}
            </li>
        @endforeach
    </ul>
    <div class="mt-6">
        @if($plan->slug === 'free')
            <span class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('app.default_plan') }}</span>
        @elseif($comingSoon)
            <button type="button" disabled class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">
                {{ __('app.coming_soon') }}
            </button>
        @else
            @if(config('subscriptions.demo_mode'))
                <button type="button" disabled class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">
                    {{ __('app.demo_mode') }}
                </button>
            @else
                @if($manualPaymentEnabled && $plan->slug === 'plus')
                    <a href="{{ $manualPaymentUrl }}" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {{ __('app.choose_plus') }}
                    </a>
                @else
                    <form method="POST" action="{{ route('subscribe.plus') }}">
                        @csrf
                        <button class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {{ __('app.choose_plus') }}
                        </button>
                    </form>
                @endif
            @endif
        @endif
    </div>
</div>
