<x-layouts.app>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('app.pricing_title') }}</h1>
            <p class="text-sm text-slate-600">{{ __('app.pricing_subtitle') }}</p>
        </div>

        @if(auth()->user()?->is_admin)
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="text-sm font-semibold text-slate-900">{{ __('app.price_management') }}</div>
                <p class="mt-1 text-xs text-slate-500">{{ __('app.price_management_hint') }}</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach($plans->whereIn('slug', ['plus', 'pro']) as $plan)
                        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            @csrf
                            @method('PATCH')
                            <div class="text-sm font-semibold text-slate-900">{{ $plan->name }}</div>
                            <label class="mt-3 block text-xs text-slate-500">{{ __('app.price_monthly_label') }}</label>
                            <input
                                type="number"
                                name="price_monthly"
                                min="0"
                                step="1"
                                value="{{ old('price_monthly', $plan->price_monthly) }}"
                                class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            >
                            <button class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-slate-800">
                                {{ __('app.update_price') }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-3">
            @foreach($plans as $plan)
                <x-pricing-card :plan="$plan" :current-plan="$currentPlan" />
            @endforeach
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('app.unlock_more') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('app.paywall_hint') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-locked-feature :label="__('app.writing_ai')" />
                <x-locked-feature :label="__('app.reading_full')" />
                <x-locked-feature :label="__('app.listening_full')" />
                <x-locked-feature :label="__('app.ai_explanation_full')" />
                <x-locked-feature :label="__('app.analytics_full')" />
                <x-locked-feature :label="__('app.mock_tests')" />
            </div>
        </div>

        @php
            $manualCard = config('subscriptions.manual_payment.card_number');
            $manualHolder = config('subscriptions.manual_payment.card_holder');
            $manualBank = config('subscriptions.manual_payment.bank_name');
            $manualNote = config('subscriptions.manual_payment.instructions');
            $manualPlan = $plans->firstWhere('slug', 'plus') ?? $plans->first();
        @endphp
        @if($manualCard && $manualPlan)
            <div id="manual-payment" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('app.manual_payment_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('app.manual_payment_help') }}</p>
                @if($pendingRequest)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        {{ __('app.subscription_request_pending') }}
                        <div class="mt-1 text-xs text-amber-700">
                            {{ __('app.subscription_request_pending_detail', [
                                'plan' => $pendingRequest->plan?->name ?? __('app.not_set'),
                                'date' => optional($pendingRequest->created_at)->format('Y-m-d'),
                            ]) }}
                        </div>
                    </div>
                @endif
                <div class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase text-slate-400">{{ __('app.manual_payment_card') }}</div>
                        <div class="mt-1 font-semibold">{{ $manualCard }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase text-slate-400">{{ __('app.manual_payment_holder') }}</div>
                        <div class="mt-1 font-semibold">{{ $manualHolder ?: __('app.not_set') }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase text-slate-400">{{ __('app.manual_payment_bank') }}</div>
                        <div class="mt-1 font-semibold">{{ $manualBank ?: __('app.not_set') }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase text-slate-400">{{ __('app.manual_payment_amount') }}</div>
                        <div class="mt-1 font-semibold">{{ number_format($manualPlan->price_monthly, 0, '.', ' ') }} UZS</div>
                    </div>
                </div>
                @if($manualNote)
                    <p class="mt-3 text-xs text-slate-500">{{ $manualNote }}</p>
                @endif

                @auth
                    @if(!$pendingRequest)
                        <form method="POST" action="{{ route('subscriptions.requests.store') }}" class="mt-4 flex flex-col gap-3">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $manualPlan->id }}">
                            <textarea
                                name="message"
                                rows="3"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="{{ __('app.manual_payment_note') }}"
                            >{{ old('message') }}</textarea>
                            <button class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">
                                {{ __('app.manual_payment_submit') }}
                            </button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="mt-4 inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ __('app.manual_payment_login') }}
                    </a>
                @endauth
            </div>
        @endif
    </div>

    @if($manualCard && $manualPlan)
        <script>
            const scrollToManualPayment = () => {
                if (window.location.hash !== '#manual-payment') {
                    return;
                }
                const target = document.getElementById('manual-payment');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };

            window.addEventListener('load', scrollToManualPayment);
            window.addEventListener('hashchange', scrollToManualPayment);
        </script>
    @endif
</x-layouts.app>
