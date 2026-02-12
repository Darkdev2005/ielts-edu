<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', __('app.app_name')) }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space+grotesk:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-['Space_Grotesk'] text-slate-900 antialiased" x-data="{ supportOpen: false }" x-on:open-support.window="supportOpen = true" x-on:keydown.escape.window="supportOpen = false">
        <div class="min-h-screen bg-slate-950 text-white">
            <div class="relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(56,189,248,0.25),transparent_45%),radial-gradient(circle_at_80%_10%,rgba(251,146,60,0.25),transparent_45%),radial-gradient(circle_at_50%_80%,rgba(45,212,191,0.2),transparent_45%)]"></div>
                <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(90deg,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:40px_40px]"></div>

                <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col items-center justify-center px-6 py-12 lg:flex-row lg:items-stretch lg:justify-between lg:gap-10">
                    <div class="flex w-full max-w-lg flex-col justify-between">
                        <div>
                            <a href="/" class="inline-flex items-center gap-3 text-white">
                                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/20">IE</span>
                                <span class="text-xl font-semibold tracking-wide">{{ __('app.app_name') }}</span>
                            </a>
                            <div class="mt-8 space-y-3">
                                <h1 class="text-4xl font-semibold leading-tight">
                                    {{ __('auth.hero_title') }}
                                </h1>
                                <p class="text-white/70">
                                    {{ __('auth.hero_subtitle') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 flex items-center gap-3 text-sm text-white/70">
                            <span class="uppercase tracking-wide text-white/50">{{ __('auth.language') }}</span>
                            <a href="{{ route('lang.switch', 'en') }}" class="hover:text-white">EN</a>
                            <a href="{{ route('lang.switch', 'uz') }}" class="hover:text-white">UZ</a>
                            <a href="{{ route('lang.switch', 'ru') }}" class="hover:text-white">RU</a>
                        </div>
                    </div>

                    <div class="mt-10 w-full max-w-md lg:mt-0">
                        <div class="relative rounded-2xl bg-white/95 p-6 text-slate-900 shadow-2xl ring-1 ring-white/10 backdrop-blur">
                            {{ $slot }}
                        </div>
                        <div class="mt-4 text-xs text-white/50">
                            {{ __('auth.hero_footer') }}
                        </div>
                    </div>
                </div>
                <x-footer-panel />
                <x-support-modal />
            </div>
        </div>
    </body>
</html>
