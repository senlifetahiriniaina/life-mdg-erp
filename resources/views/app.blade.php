<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'WideHalo ERP') }}</title>

        <!-- PWA -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#1e40af">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Self-hosted fonts (Phase 5.6) - Preload critical fonts for faster rendering -->
        @php
            $cdnUrl = config('cdn.enabled') ? rtrim(config('cdn.url'), '/') : '';
        @endphp

        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/inter-tight/inter-tight-400.woff2' : '/fonts/inter-tight/inter-tight-400.woff2' }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/inter-tight/inter-tight-700.woff2' : '/fonts/inter-tight/inter-tight-700.woff2' }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/geist/geist-700.woff2' : '/fonts/geist/geist-700.woff2' }}" as="font" type="font/woff2" crossorigin>
        <!-- Import self-hosted fonts CSS with @font-face rules -->
        <link rel="stylesheet" href="/css/fonts-self-hosted.css">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
