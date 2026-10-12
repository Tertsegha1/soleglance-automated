<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SoleGlance: social networking with news and sentiment analytics</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gradient-to-b from-indigo-50 to-white text-slate-900 min-h-screen">
<header class="max-w-6xl mx-auto px-6 py-5 flex justify-between items-center">
    <div class="flex items-center gap-2 font-semibold text-lg text-indigo-700"><x-application-logo class="h-9 w-9" /> SoleGlance</div>
    <nav class="flex gap-3 text-sm">
        <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg hover:bg-white">Log in</a>
        <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Create account</a>
    </nav>
</header>
<main class="max-w-6xl mx-auto px-6 py-16 grid md:grid-cols-2 gap-12 items-center">
    <div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">Talk with friends. <span class="text-indigo-600">See what the world feels.</span></h1>
        <p class="mt-5 text-lg text-slate-600">SoleGlance combines a social network with live news and trending-topic dashboards, and shows how people feel about each topic, in English, Urdu and Roman Urdu.</p>
        <div class="mt-8 flex gap-3">
            <a href="{{ route('register') }}" class="px-6 py-3 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700">Get started, it's free</a>
        </div>
    </div>
    <ul class="grid sm:grid-cols-2 gap-4 text-sm">
        @foreach ([
            ['Friends & chat', 'Add friends, share posts with exactly who you choose, and chat in real time.'],
            ['Trending dashboards', 'Politics, sports, business, technology and more, from public posts and news feeds.'],
            ['Sentiment analysis', 'See the positive/neutral/negative mood behind any topic, over time and by region.'],
            ['Reports', 'Download a PDF report for any topic and date range.'],
        ] as [$h, $p])
            <li class="rounded-xl bg-white border border-slate-200 p-5 shadow-sm"><h2 class="font-semibold">{{ $h }}</h2><p class="text-slate-600 mt-1">{{ $p }}</p></li>
        @endforeach
    </ul>
</main>
</body>
</html>
