<x-app-layout title="Trending">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-semibold">Trending topics</h1>
            <p class="text-sm text-slate-500">
                Terms mentioned unusually often in public posts and news in the last {{ config('analytics.trends.window_hours') }} hours, compared with the {{ config('analytics.trends.history_windows') }} windows before.
                @if ($computedAt) Updated {{ $computedAt->diffForHumans() }}. @endif
            </p>
        </div>
        <form method="get" action="{{ route('trending.show') }}" class="flex gap-2">
            <label for="topic-q" class="sr-only">Analyse a topic</label>
            <x-text-input id="topic-q" name="q" placeholder="Analyse any topic…" required />
            <x-primary-button>Analyse</x-primary-button>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Categories">
        <a href="{{ route('trending.index') }}" class="rounded-full px-3 py-1 text-sm border {{ !$selected ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white hover:bg-slate-50' }}">All</a>
        @foreach ($categories as $cat)
            <a href="{{ route('trending.index', ['category' => $cat->value]) }}" class="rounded-full px-3 py-1 text-sm border {{ $selected === $cat->value ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white hover:bg-slate-50' }}">{{ $cat->label() }}</a>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                @if ($trends->isNotEmpty())
                    <div class="p-4 border-b">
                        <canvas data-chart="bar" height="140"
                                data-labels='@json($trends->take(12)->pluck('term'))'
                                data-series='@json([['label' => 'Mentions', 'data' => $trends->take(12)->pluck('mentions')]])'
                                aria-label="Mentions of the top trending terms" role="img"></canvas>
                    </div>
                @endif
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-500 border-b">
                        <tr><th class="p-3">#</th><th class="p-3">Topic</th><th class="p-3 hidden sm:table-cell">Category</th><th class="p-3 text-right">Mentions</th><th class="p-3 w-40">Sentiment</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($trends as $i => $trend)
                            <tr class="border-b last:border-b-0 hover:bg-slate-50">
                                <td class="p-3 text-slate-400">{{ $i + 1 }}</td>
                                <td class="p-3"><a class="font-medium text-indigo-700 hover:underline" href="{{ route('trending.show', ['q' => $trend->term]) }}">{{ $trend->term }}</a>
                                    <div class="text-xs text-slate-500">burst score {{ number_format($trend->score, 1) }} &middot; usual {{ number_format($trend->baseline, 1) }}/window</div></td>
                                <td class="p-3 hidden sm:table-cell">{{ $trend->category?->label() ?? '—' }}</td>
                                <td class="p-3 text-right tabular-nums">{{ $trend->mentions }}</td>
                                <td class="p-3"><x-sentiment-bar :positive="$trend->positive" :neutral="$trend->neutral" :negative="$trend->negative" />
                                    <div class="text-[11px] text-slate-500 mt-1 tabular-nums">{{ round($trend->share('positive') * 100) }}% pos &middot; {{ round($trend->share('negative') * 100) }}% neg</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty>No trends yet. Trends are recomputed every hour once posts and news are available.</x-empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        <aside class="space-y-3">
            <h2 class="font-semibold">Mood by category (last 7 days)</h2>
            @forelse ($categoryMix as $cat => $mix)
                <a href="{{ route('trending.index', ['category' => $cat]) }}" class="block">
                    <x-card class="p-3 hover:border-indigo-300">
                        <div class="flex justify-between text-sm mb-1"><span class="font-medium">{{ ucfirst($cat) }}</span><span class="text-slate-500">{{ array_sum($mix) }} items</span></div>
                        <x-sentiment-bar :positive="$mix['positive']" :neutral="$mix['neutral']" :negative="$mix['negative']" />
                    </x-card>
                </a>
            @empty
                <p class="text-sm text-slate-500">No analysed content yet.</p>
            @endforelse
            <p class="text-xs text-slate-500">Green: positive &middot; grey: neutral &middot; red: negative. Sentiment is estimated automatically and can be wrong for sarcasm or mixed-language text.</p>
        </aside>
    </div>
</x-app-layout>
