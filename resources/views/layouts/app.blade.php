<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">
<div class="min-h-screen">
    @include('layouts.navigation')

    @isset($header)
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-6xl mx-auto py-5 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
        </header>
    @endisset

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <x-flash />
        {{ $slot }}
    </main>

    <footer class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-xs text-slate-500">
        SoleGlance &middot; social networking with news and sentiment analytics &middot;
        <a class="underline" href="https://github.com/Tertsegha1/soleglance-automated" rel="noopener">source code</a>
    </footer>
</div>
</body>
</html>
