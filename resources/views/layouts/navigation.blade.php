<nav x-data="{ open: false }" class="sticky top-0 z-20 border-b border-white/10 bg-white/10 backdrop-blur">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-white" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('app.dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('mock.index')" :active="request()->routeIs('mock.*')">
                        {{ __('app.mock_tests') }}
                    </x-nav-link>
                    <x-nav-link :href="route('vocabulary.index')" :active="request()->routeIs('vocabulary.*')">
                        {{ __('app.vocabulary') }}
                    </x-nav-link>
                    <x-nav-link :href="route('grammar.index')" :active="request()->routeIs('grammar.*')">
                        {{ __('app.grammar') }}
                    </x-nav-link>
                    @if(Auth::user()?->is_admin)
                        <x-nav-link :href="route('admin.vocabulary.index')" :active="request()->routeIs('admin.vocabulary.*')">
                            {{ __('app.vocabulary_upload') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.ai-logs.index')" :active="request()->routeIs('admin.ai-logs.*')">
                            {{ __('app.ai_logs') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.mock-questions.index')" :active="request()->routeIs('admin.mock-questions.*')">
                            {{ __('app.mock_questions_nav') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @php
                    $supportTelegram = config('app.support.telegram');
                    $supportEmail = config('app.support.email');
                @endphp
                @if($supportTelegram || $supportEmail)
                    <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-white/50 me-4">
                        {{ __('app.contact_support') }}
                        <div class="flex items-center gap-2 text-sm font-medium normal-case text-white/70">
                            @if($supportTelegram)
                                <a href="{{ $supportTelegram }}" target="_blank" rel="noopener" class="hover:text-white">Telegram</a>
                            @endif
                            @if($supportEmail)
                                <a href="mailto:{{ $supportEmail }}" class="hover:text-white">Email</a>
                            @endif
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-2 text-sm text-white/70 me-4">
                    <a href="{{ route('lang.switch', 'en') }}" class="hover:underline">EN</a>
                    <a href="{{ route('lang.switch', 'uz') }}" class="hover:underline">UZ</a>
                    <a href="{{ route('lang.switch', 'ru') }}" class="hover:underline">RU</a>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-white/20 text-sm leading-4 font-medium rounded-md text-white/80 bg-white/10 hover:text-white focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('app.profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('app.logout') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-white/70 hover:text-white hover:bg-white/10 focus:outline-none focus:bg-white/10 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="bg-white/90 text-slate-900">
            <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('app.dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('mock.index')" :active="request()->routeIs('mock.*')">
                {{ __('app.mock_tests') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('vocabulary.index')" :active="request()->routeIs('vocabulary.*')">
                {{ __('app.vocabulary') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('grammar.index')" :active="request()->routeIs('grammar.*')">
                {{ __('app.grammar') }}
            </x-responsive-nav-link>
            @if(Auth::user()?->is_admin)
                <x-responsive-nav-link :href="route('admin.vocabulary.index')" :active="request()->routeIs('admin.vocabulary.*')">
                    {{ __('app.vocabulary_upload') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.ai-logs.index')" :active="request()->routeIs('admin.ai-logs.*')">
                    {{ __('app.ai_logs') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.mock-questions.index')" :active="request()->routeIs('admin.mock-questions.*')">
                    {{ __('app.mock_questions_nav') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
            <div class="pt-4 pb-1 border-t border-slate-200">
                <div class="px-4">
                    <div class="font-medium text-base text-slate-900">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-slate-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <div class="px-4 text-sm text-slate-600">
                        <a href="{{ route('lang.switch', 'en') }}" class="hover:underline">EN</a>
                        <a href="{{ route('lang.switch', 'uz') }}" class="hover:underline ms-2">UZ</a>
                        <a href="{{ route('lang.switch', 'ru') }}" class="hover:underline ms-2">RU</a>
                    </div>
                    @if($supportTelegram || $supportEmail)
                        <div class="px-4 pt-3 text-xs uppercase tracking-wide text-slate-400">
                            {{ __('app.contact_support') }}
                        </div>
                        <div class="px-4 text-sm text-slate-600">
                            @if($supportTelegram)
                                <a href="{{ $supportTelegram }}" target="_blank" rel="noopener" class="hover:underline">Telegram</a>
                            @endif
                            @if($supportEmail)
                                <a href="mailto:{{ $supportEmail }}" class="hover:underline ms-2">Email</a>
                            @endif
                        </div>
                    @endif

                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('app.profile') }}
                    </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('app.logout') }}
                    </x-responsive-nav-link>
                </form>
                </div>
            </div>
        </div>
    </div>
</nav>
