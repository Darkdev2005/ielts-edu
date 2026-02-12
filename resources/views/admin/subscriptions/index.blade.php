<x-layouts.app :title="__('app.subscriptions')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.subscriptions') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('app.subscriptions_intro') }}</p>
        </div>
    </div>

    @if(session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if(isset($requests) && $requests->isNotEmpty())
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
            <div class="text-base font-semibold text-slate-900">{{ __('app.manual_payment_title') }}</div>
            <p class="mt-1 text-xs text-slate-600">{{ __('app.subscription_requests_pending') }}</p>
            <div class="mt-4 space-y-3">
                @foreach($requests as $request)
                    <div class="rounded-xl border border-amber-200 bg-white/90 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $request->user?->name }}</div>
                                <div class="text-xs text-slate-500">{{ $request->user?->email }}</div>
                                <div class="mt-2 text-xs text-slate-500">
                                    {{ __('app.plan') }}: <span class="font-semibold text-slate-800">{{ $request->plan?->name }}</span>
                                    В· {{ __('app.manual_payment_amount') }}: <span class="font-semibold text-slate-800">{{ number_format($request->amount ?? 0, 0, '.', ' ') }} {{ $request->currency ?? 'UZS' }}</span>
                                </div>
                                @if($request->message)
                                    <div class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                        {{ $request->message }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-col gap-2">
                                <form method="POST" action="{{ route('admin.subscriptions.requests.approve', $request) }}" class="flex flex-col gap-2">
                                    @csrf
                                    <label class="text-xs font-semibold text-slate-500">
                                        {{ __('app.activation_months') }}
                                    </label>
                                    <input
                                        type="number"
                                        name="months"
                                        min="1"
                                        max="24"
                                        value="{{ config('subscriptions.manual_payment.default_months', 1) }}"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs"
                                    >
                                    <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">
                                        {{ __('app.approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.subscriptions.requests.reject', $request) }}" class="flex flex-col gap-2">
                                    @csrf
                                    <input type="text" name="admin_note" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs" placeholder="{{ __('app.admin_note_optional') }}">
                                    <button class="w-full rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                        {{ __('app.reject') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8 space-y-4">
        @foreach($users as $user)
            @php
                $planName = $user->currentPlan?->name ?? __('app.free');
                $status = $user->subscription?->status ?? 'none';
                $statusLabels = [
                    'active' => __('app.subscription_active'),
                    'trialing' => __('app.subscription_trialing'),
                    'past_due' => __('app.subscription_past_due'),
                    'canceled' => __('app.subscription_canceled'),
                    'incomplete' => __('app.subscription_inactive'),
                    'incomplete_expired' => __('app.subscription_inactive'),
                    'unpaid' => __('app.subscription_unpaid'),
                    'inactive' => __('app.subscription_inactive'),
                    'none' => __('app.subscription_none'),
                ];
                $badgeClasses = [
                    'active' => 'bg-emerald-100 text-emerald-700',
                    'trialing' => 'bg-sky-100 text-sky-700',
                    'past_due' => 'bg-amber-100 text-amber-700',
                    'canceled' => 'bg-rose-100 text-rose-700',
                    'unpaid' => 'bg-rose-100 text-rose-700',
                    'inactive' => 'bg-slate-100 text-slate-600',
                    'none' => 'bg-slate-100 text-slate-600',
                ];
                $statusLabel = $statusLabels[$status] ?? $status;
                $badgeClass = $badgeClasses[$status] ?? 'bg-slate-100 text-slate-600';
            @endphp
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $user->name }}</div>
                    <div class="text-xs text-slate-500">{{ $user->email }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm text-slate-600">
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.plan') }}</div>
                        <div class="font-semibold text-slate-900">{{ $planName }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.subscription_status') }}</div>
                        <div class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass }}">
                            {{ $statusLabel }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.provider') }}</div>
                        <div class="font-semibold text-slate-900">{{ $user->subscription?->provider ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.period_end') }}</div>
                        <div class="font-semibold text-slate-900">
                            {{ $user->subscription?->current_period_end?->format('Y-m-d') ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">{{ $users->links() }}</div>
</x-layouts.app>
