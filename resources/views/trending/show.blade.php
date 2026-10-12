<x-app-layout :title="$term">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
        <div>
            <p class="text-sm text-slate-500"><a href="{{ route('trending.index') }}" class="hover:underline">Trending</a> / topic</p>
            <h1 class="text-2xl font-semibold">{{ $term }}</h1>
            <p class="text-sm text-slate-500">{{ $from->toFormattedDateString() }} – {{ $to->toFormattedDateString() }}{{ $country ? ' · ' . $country : '' }}</p>
        </div>
        <form method="get" class="flex flex-wrap gap-2 items-end">
            <input type="hidden" name="q" value="{{ $term }}">
            <div><x-input-label for="from" value="From" /><x-text-input id="from" type="date" name="from" :value="$from->toDateString()" class="mt-1" /></div>
            <div><x-input-label for="to" value="To" /><x-text-input id="to" type="date" name="to" :value="$to->toDateString()" class="mt-1" /></div>
            <div><x-input-label for="country" value="Region" /><x-text-input id="country" name="country" maxlength="2" :value="$country" class="mt-1 w-20 uppercase" /></div>
            <x-primary-button>Apply</x-primary-button>
            <a href="{{ route('reports.index', ['topic' => $term, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'country' => $country]) }}" class="rounded-md border px-4 py-2 text-xs font-semibold uppercase tracking-widest hover:bg-slate-50">PDF report</a>
        </form>
    </div>

    @php $t = $timeline['totals']; $total = array_sum($t); @endphp
    <div class="grid sm:grid-cols-4 gap-4 mb-6">
        <x-card class="p-4"><div class="text-sm text-slate-500">Analysed items</div><div class="text-2xl font-semibold tabular-nums">{{ $total }}</div></x-card>
        @foreach (['positive' => 'text-emerald-700', 'neutral' => 'text-slate-700', 'negative' => 'text-rose-700'] as $label => $color)
            <x-card class="p-4"><div class="text-sm text-slate-500">{{ ucfirst($label) }}</div>
                <div class="text-2xl font-semibold tabular-nums {{ $color }}">{{ $total ? round(100 * $t[$label] / $total) : 0 }}%</div>
                <div class="text-xs text-slate-500">{{ $t[$label] }} items</div></x-card>
        @endforeach
    </div>

    <x-card class="p-4 mb-6">
        <h2 class="font-semibold mb-2">Sentiment over time</h2>
        @if ($total)
            @php
                $series = [
                    ['label' => 'Positive', 'data' => $timeline['positive'], 'color' => '#10b981'],
                    ['label' => 'Neutral', 'data' => $timeline['neutral'], 'color' => '#cbd5e1'],
                    ['label' => 'Negative', 'data' => $timeline['negative'], 'color' => '#f43f5e'],
                ];
            @endphp
            <canvas data-chart="stacked" height="110"
                    data-labels='@json($timeline['days'])'
                    data-series='@json($series)'
                    role="img" aria-label="Daily counts of positive, neutral and negative items mentioning {{ $term }}"></canvas>
        @else
            <x-empty>No analysed public posts or news mention "{{ $term }}" in this period.</x-empty>
        @endif
    </x-card>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="space-y-3">
            <h2 class="font-semibold">News</h2>
            @forelse ($articles as $article)
                @include('trending._article', ['article' => $article])
            @empty
                <x-card><x-empty>No news articles.</x-empty></x-card>
            @endforelse
        </section>
        <section class="space-y-3">
            <h2 class="font-semibold">Posts</h2>
            @forelse ($posts as $post)
                <x-post-card :post="$post" />
            @empty
                <x-card><x-empty>No posts you can see.</x-empty></x-card>
            @endforelse
        </section>
    </div>
</x-app-layout>
