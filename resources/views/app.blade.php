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

        <!-- Self-hosted fonts (Phase 5.6) - Preload critical fonts for faster rendering.
             (A redundant fonts.bunny.net stylesheet used to be loaded here too — dropped:
             it isn't in the CSP's style-src allowlist so it was always being blocked, and
             self-hosted fonts below are the actual intended path.) -->
        @php
            $cdnUrl = config('cdn.enabled') ? rtrim(config('cdn.url'), '/') : '';
            $cspNonce = request()->attributes->get('csp_nonce');
        @endphp

        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/inter-tight/inter-tight-400.woff2' : '/fonts/inter-tight/inter-tight-400.woff2' }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/inter-tight/inter-tight-700.woff2' : '/fonts/inter-tight/inter-tight-700.woff2' }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ $cdnUrl ? $cdnUrl . '/fonts/geist/geist-700.woff2' : '/fonts/geist/geist-700.woff2' }}" as="font" type="font/woff2" crossorigin>
        <!-- Import self-hosted fonts CSS with @font-face rules -->
        <link rel="stylesheet" href="/css/fonts-self-hosted.css">

        <!-- Scripts -->
        {{-- @routes only forwards a route-group argument, with no way to pass the
             per-request CSP nonce set by App\Http\Middleware\SecurityHeaders — its
             inline <script> was silently blocked by our own CSP (script-src requires
             a matching nonce for inline scripts), breaking window.Ziggy and every
             route() call in the frontend. Call the generator directly instead. --}}
        {!! app(\Tighten\Ziggy\BladeRouteGenerator::class)->generate(null, $cspNonce) !!}
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
