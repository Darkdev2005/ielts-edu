<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">
            {{ __('app.profile') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            <div class="p-4 sm:p-8 bg-white/95 shadow sm:rounded-2xl">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm text-slate-500">{{ __('app.profile') }}</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $user->name }}</div>
                        <div class="text-sm text-slate-500">{{ $user->email }}</div>
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
                            <div class="text-xs text-slate-400">{{ __('app.period_end') }}</div>
                            <div class="font-semibold text-slate-900">
                                {{ $user->subscription?->current_period_end?->format('Y-m-d') ?? '-' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                {{ __('app.pricing') }}
                            </a>
                            <a href="{{ route('billing.portal') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                {{ __('app.billing_portal') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white/95 shadow sm:rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white/95 shadow sm:rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white/95 shadow sm:rounded-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
