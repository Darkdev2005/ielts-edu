<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', __('app.app_name')) }}</title>
        @php
            $faviconPngVersion = file_exists(public_path('favicon.png')) ? filemtime(public_path('favicon.png')) : '1';
            $faviconIcoVersion = file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : $faviconPngVersion;
        @endphp
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconPngVersion }}">
        <link rel="alternate icon" href="{{ asset('favicon.ico') }}?v={{ $faviconIcoVersion }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space+grotesk:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-['Space_Grotesk'] antialiased text-slate-900">
        <div class="min-h-screen bg-slate-950 text-white">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(56,189,248,0.18),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(251,146,60,0.18),transparent_45%),radial-gradient(circle_at_50%_90%,rgba(45,212,191,0.18),transparent_45%)]"></div>
            <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(90deg,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:40px_40px]"></div>

            <div class="relative">
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="rounded-2xl bg-white/90 px-6 py-4 text-slate-900 shadow-xl ring-1 ring-white/10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <div class="rounded-3xl bg-white/95 p-6 text-slate-900 shadow-2xl ring-1 ring-white/10">
                        {{ $slot }}
                    </div>
                </main>
                <x-footer-panel />
            </div>
        </div>
    </body>
</html>
