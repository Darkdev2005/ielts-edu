<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.app_name') }}</title>
    @php
        $faviconPngVersion = file_exists(public_path('favicon.png')) ? filemtime(public_path('favicon.png')) : '1';
        $faviconIcoVersion = file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : $faviconPngVersion;
    @endphp
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconPngVersion }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}?v={{ $faviconIcoVersion }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space+grotesk:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-['Space_Grotesk'] text-slate-900 antialiased">
    <div class="min-h-screen bg-slate-950 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(56,189,248,0.18),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(251,146,60,0.18),transparent_45%),radial-gradient(circle_at_50%_90%,rgba(45,212,191,0.18),transparent_45%)]"></div>
        <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(90deg,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:40px_40px]"></div>

        <div
            class="relative"
            x-data="{ mobileOpen: false, paywallOpen: false, paywallFeature: '', supportOpen: false, scrolled: false }"
            x-on:open-paywall.window="paywallOpen = true; paywallFeature = $event.detail.feature || ''"
            x-on:open-support.window="supportOpen = true"
            x-on:keydown.escape.window="mobileOpen = false; paywallOpen = false; supportOpen = false"
            x-on:scroll.window="scrolled = window.scrollY > 8"
        >
            @php
                $navPlan = auth()->user()?->currentPlan;
                $navPlanSlug = $navPlan?->slug;
                $navPlanLabel = $navPlan?->name;
                $navHasPremium = $navPlanSlug && $navPlanSlug !== 'free';
                $navPlanBadgeClass = $navPlanSlug === 'pro'
                    ? 'bg-rose-600 text-white'
                    : 'bg-emerald-600 text-white';
                $premiumGlowClass = $navPlanSlug === 'pro'
                    ? 'from-rose-500/35 via-amber-400/20 to-orange-400/30'
                    : 'from-emerald-500/35 via-amber-400/20 to-cyan-400/30';
                $premiumRingClass = $navPlanSlug === 'pro'
                    ? 'ring-2 ring-rose-200/70 shadow-[0_0_35px_rgba(244,63,94,0.18)]'
                    : 'ring-2 ring-emerald-200/70 shadow-[0_0_35px_rgba(16,185,129,0.18)]';
            @endphp
            <header class="sticky top-0 z-10 hidden border-b border-white/10 backdrop-blur md:block relative" x-bind:class="scrolled ? 'bg-slate-950/95 shadow-lg' : 'bg-white/10'">
                @if($navHasPremium)
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-r {{ $premiumGlowClass }} opacity-60"></div>
                @endif
                <div class="relative mx-auto flex max-w-8xl items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('lessons.index') }}" class="text-lg font-semibold tracking-wide">{{ __('app.app_name') }}</a>
                        @if($navPlanSlug && $navPlanSlug !== 'free')
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $navPlanBadgeClass }}">
                                {{ strtoupper((string) $navPlanLabel) }}
                            </span>
                        @endif
                    </div>
                    <nav class="flex items-center gap-4 text-sm text-white/80">
                        <a href="{{ route('lessons.index') }}" class="hover:text-white">{{ __('app.lessons') }}</a>
                        <a href="{{ route('mock.index') }}" class="hover:text-white">{{ __('app.mock_tests') }}</a>
                        <a href="{{ route('dashboard') }}" class="hover:text-white">{{ __('app.dashboard') }}</a>
                        <a href="{{ route('daily-challenge.show') }}" class="hover:text-white">{{ __('app.daily_challenge') }}</a>
                        <a href="{{ route('vocabulary.index') }}" class="hover:text-white">{{ __('app.vocabulary') }}</a>
                        <a href="{{ route('vocabulary.translate.page') }}" class="rounded-full border border-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80 hover:border-white/30 hover:text-white">
                            {{ __('app.vocab_translate_action') }}
                        </a>
                        <a href="{{ route('grammar.index') }}" class="hover:text-white">{{ __('app.grammar') }}</a>
                        <a href="{{ route('mistakes.index') }}" class="hover:text-white">{{ __('app.review_mistakes') }}</a>
                        <a href="{{ route('profile.edit') }}" class="hover:text-white">{{ __('app.profile') }}</a>
                        <a href="{{ route('pricing') }}" class="hover:text-white">{{ __('app.pricing') }}</a>
                        @if(auth()->user()?->is_admin)
                            <div class="relative" x-data="{ adminOpen: false }">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-full border border-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/80 hover:border-white/30 hover:text-white"
                                    @click="adminOpen = !adminOpen"
                                    @keydown.escape.window="adminOpen = false"
                                >
                                    {{ __('app.admin') }}
                                    <svg class="h-3 w-3 text-white/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.09l3.71-3.86a.75.75 0 1 1 1.08 1.04l-4.25 4.41a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div
                                    x-cloak
                                    x-show="adminOpen"
                                    @click.outside="adminOpen = false"
                                    class="absolute right-0 mt-2 w-64 rounded-2xl border border-white/15 bg-slate-950 p-2 text-sm text-white shadow-2xl ring-1 ring-white/10"
                                >
                                    <a href="{{ route('admin.lessons.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.admin') }}</a>
                                    <a href="{{ route('admin.vocabulary.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.vocabulary_upload') }}</a>
                                    <a href="{{ route('admin.results.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.user_results') }}</a>
                                    <a href="{{ route('admin.subscriptions.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.subscriptions') }}</a>
                                    <a href="{{ route('admin.ai-logs.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.ai_logs') }}</a>
                                    <a href="{{ route('admin.mock-questions.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.mock_questions_nav') }}</a>
                                    <a href="{{ route('admin.ai-settings.edit') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.ai_settings') }}</a>
                                    <a href="{{ route('admin.grammar.topics.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.grammar') }}</a>
                                    @if(auth()->user()?->is_super_admin)
                                        <div class="my-1 border-t border-white/10"></div>
                                        <a href="{{ route('admin.admins.index') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ __('app.admin_management') }}</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <span class="text-white/30">|</span>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('lang.switch', 'en') }}" class="hover:text-white">EN</a>
                            <a href="{{ route('lang.switch', 'uz') }}" class="hover:text-white">UZ</a>
                            <a href="{{ route('lang.switch', 'ru') }}" class="hover:text-white">RU</a>
                        </div>
                        <span class="text-white/30">|</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-rose-400">{{ __('app.logout') }}</button>
                        </form>
                    </nav>
                </div>
            </header>
            <header class="sticky top-0 z-10 border-b border-white/10 backdrop-blur md:hidden relative" x-bind:class="scrolled ? 'bg-slate-950/95 shadow-lg' : 'bg-white/10'">
                @if($navHasPremium)
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-r {{ $premiumGlowClass }} opacity-60"></div>
                @endif
                <div class="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('lessons.index') }}" class="text-base font-semibold tracking-wide">{{ __('app.app_name') }}</a>
                        @if($navPlanSlug && $navPlanSlug !== 'free')
                            <span class="rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $navPlanBadgeClass }}">
                                {{ strtoupper((string) $navPlanLabel) }}
                            </span>
                        @endif
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white/90"
                        @click="mobileOpen = true"
                        aria-controls="mobile-menu"
                        :aria-expanded="mobileOpen.toString()"
                    >
                        <span>{{ __('app.menu') }}</span>
                        <span class="flex h-4 w-5 flex-col justify-between" aria-hidden="true">
                            <span class="block h-0.5 w-full rounded bg-white"></span>
                            <span class="block h-0.5 w-full rounded bg-white"></span>
                            <span class="block h-0.5 w-full rounded bg-white"></span>
                        </span>
                    </button>
                </div>
            </header>
            <div
                id="mobile-menu"
                class="z-30 border-b border-white/10 bg-slate-950/95 text-white md:hidden"
                x-show="mobileOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
            >
                <div class="mx-auto flex max-w-6xl flex-col px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm font-semibold tracking-wide">
                            <span>{{ __('app.app_name') }}</span>
                            @if($navPlanSlug && $navPlanSlug !== 'free')
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $navPlanBadgeClass }}">
                                    {{ strtoupper((string) $navPlanLabel) }}
                                </span>
                            @endif
                        </div>
                        <button type="button" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white/90" @click="mobileOpen = false">
                            <span>Menu</span>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12l5-5 5 5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                    <nav class="mt-4 flex flex-col gap-3 text-sm text-white/80">
                        <a href="{{ route('lessons.index') }}" class="hover:text-white">{{ __('app.lessons') }}</a>
                        <a href="{{ route('mock.index') }}" class="hover:text-white">{{ __('app.mock_tests') }}</a>
                        <a href="{{ route('dashboard') }}" class="hover:text-white">{{ __('app.dashboard') }}</a>
                        <a href="{{ route('daily-challenge.show') }}" class="hover:text-white">{{ __('app.daily_challenge') }}</a>
                        <a href="{{ route('vocabulary.index') }}" class="hover:text-white">{{ __('app.vocabulary') }}</a>
                        <a href="{{ route('vocabulary.translate.page') }}" class="w-full rounded-lg border border-white/10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-white/80 hover:border-white/30 hover:text-white">
                            {{ __('app.vocab_translate_action') }}
                        </a>
                        <a href="{{ route('grammar.index') }}" class="hover:text-white">{{ __('app.grammar') }}</a>
                        <a href="{{ route('mistakes.index') }}" class="hover:text-white">{{ __('app.review_mistakes') }}</a>
                        <a href="{{ route('profile.edit') }}" class="hover:text-white">{{ __('app.profile') }}</a>
                        <a href="{{ route('pricing') }}" class="hover:text-white">{{ __('app.pricing') }}</a>
                        @if(auth()->user()?->is_admin)
                            <details class="rounded-xl border border-white/10 px-3 py-2">
                                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-white/70">
                                    {{ __('app.admin') }}
                                </summary>
                                <div class="mt-3 flex flex-col gap-2 text-sm text-white/80">
                                    <a href="{{ route('admin.lessons.index') }}" class="hover:text-white">{{ __('app.admin') }}</a>
                                    <a href="{{ route('admin.vocabulary.index') }}" class="hover:text-white">{{ __('app.vocabulary_upload') }}</a>
                                    <a href="{{ route('admin.results.index') }}" class="hover:text-white">{{ __('app.user_results') }}</a>
                                    <a href="{{ route('admin.subscriptions.index') }}" class="hover:text-white">{{ __('app.subscriptions') }}</a>
                                    <a href="{{ route('admin.ai-logs.index') }}" class="hover:text-white">{{ __('app.ai_logs') }}</a>
                                    <a href="{{ route('admin.mock-questions.index') }}" class="hover:text-white">{{ __('app.mock_questions_nav') }}</a>
                                    <a href="{{ route('admin.ai-settings.edit') }}" class="hover:text-white">{{ __('app.ai_settings') }}</a>
                                    <a href="{{ route('admin.grammar.topics.index') }}" class="hover:text-white">{{ __('app.grammar') }}</a>
                                    @if(auth()->user()?->is_super_admin)
                                        <a href="{{ route('admin.admins.index') }}" class="hover:text-white">{{ __('app.admin_management') }}</a>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </nav>
                    <div class="mt-4 border-t border-white/10 pt-4 text-xs text-white/70">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('lang.switch', 'en') }}" class="hover:text-white">EN</a>
                                <a href="{{ route('lang.switch', 'uz') }}" class="hover:text-white">UZ</a>
                                <a href="{{ route('lang.switch', 'ru') }}" class="hover:text-white">RU</a>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-rose-400">{{ __('app.logout') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @if($navHasPremium)
                <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-r {{ $premiumGlowClass }} opacity-80 blur-2xl"></div>
            @endif
            <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                @if(session('status'))
                    <div class="mb-6 rounded bg-emerald-100 px-4 py-3 text-emerald-800">{{ session('status') }}</div>
                @endif
                @php
                    $statusMessage = session('status');
                    $showUpgradeModal = session('upgrade_prompt') || $statusMessage === __('app.daily_limit_reached');
                @endphp
                @if($showUpgradeModal)
                    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4">
                        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-slate-900 shadow-2xl">
                            <div class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.upgrade_badge') }}</div>
                            <h3 class="mt-2 text-lg font-semibold">{{ __('app.unlock_more') }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ $statusMessage }}</p>
                            <div class="mt-4 flex items-center justify-end gap-2">
                                @php
                                    $manualPaymentEnabled = (bool) config('subscriptions.manual_payment.card_number');
                                    $manualPaymentUrl = $manualPaymentEnabled ? route('pricing').'#manual-payment' : route('pricing');
                                @endphp
                                <a href="{{ $manualPaymentUrl }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-50">
                                    {{ __('app.choose_plus') }}
                                </a>
                                <a href="{{ route('pricing') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-slate-800">
                                    {{ __('app.view_plans') }}
                                </a>
                                <a href="{{ url()->previous() }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 hover:bg-slate-50">
                                    {{ __('app.close') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="rounded-3xl bg-white/95 p-6 text-slate-900 shadow-2xl ring-1 ring-white/10 {{ $navHasPremium ? $premiumRingClass : '' }}">
                    {{ $slot }}
                </div>
            </main>

            <x-footer-panel />
            <x-paywall-modal />
            <x-support-modal />
        </div>
    </div>
</body>
</html>
