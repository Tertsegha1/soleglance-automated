<x-app-layout title="Health">
    @php $label = fn ($d) => \App\Models\HealthMention::label($d); @endphp
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-semibold">Health signals</h1>
            <p class="text-sm text-slate-500 max-w-3xl">Mentions of diseases and symptoms in public posts and news, detected automatically in English, Nigerian Pidgin, Urdu and Roman Urdu. Signals can be wrong and are not confirmed outbreaks; follow official guidance from NCDC (Nigeria) or NIH (Pakistan).</p>
        </div>
        <form method="get" class="flex flex-wrap gap-2 items-end">
            <div><x-input-label for="country" value="Country" />
                <select id="country" name="country" class="mt-1 rounded-md border-slate-300"><option value="">All</option>
                    @foreach (config('health.countries') as $code => $name)<option value="{{ $code }}" @selected(($filters['country'] ?? null) === $code)>{{ $name }}</option>@endforeach
                </select></div>
            <div><x-input-label for="disease" value="Condition" />
                <select id="disease" name="disease" class="mt-1 rounded-md border-slate-300"><option value="">All</option>
                    @foreach (config('health.labels') as $id => $name)<option value="{{ $id }}" @selected(($filters['disease'] ?? null) === $id)>{{ $name }}</option>@endforeach
                </select></div>
            <div><x-input-label for="days" value="Period" />
                <select id="days" name="days" class="mt-1 rounded-md border-slate-300">
                    @foreach ([14, 30, 90] as $d)<option value="{{ $d }}" @selected($filters['days'] === $d)>{{ $d }} days</option>@endforeach
                </select></div>
            <x-primary-button>Apply</x-primary-button>
        </form>
    </div>

    @if ($alerts->isNotEmpty())
        <x-card class="p-4 mb-6 border-amber-300 bg-amber-50">
            <h2 class="font-semibold text-amber-900 mb-2">Active early-warning signals</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($alerts as $a)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-amber-200 px-2 py-0.5 text-xs font-medium">{{ $a->status }}</span>
                        <strong>{{ $a->title() }}</strong>
                        <span class="text-slate-600">{{ $a->count }} mentions on {{ $a->window_end->toFormattedDateString() }} (usual {{ number_format($a->baseline_mean ?? 0, 1) }}/day)</span>
                        @can('health')<a href="{{ route('admin.health-alerts.index') }}" class="text-indigo-700 underline">manage</a>@endcan
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <x-card class="p-4"><div class="text-sm text-slate-500">Health mentions</div><div class="text-2xl font-semibold tabular-nums">{{ number_format($totals['mentions']) }}</div></x-card>
        <x-card class="p-4"><div class="text-sm text-slate-500">from public posts</div><div class="text-2xl font-semibold tabular-nums">{{ number_format($totals['posts']) }}</div></x-card>
        <x-card class="p-4"><div class="text-sm text-slate-500">from news</div><div class="text-2xl font-semibold tabular-nums">{{ number_format($totals['articles']) }}</div></x-card>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <x-card class="p-4 lg:col-span-2">
            <h2 class="font-semibold mb-2">Daily mentions</h2>
            <canvas data-chart="line" height="110" data-labels='@json($dayLabels)' data-series='@json([['label' => 'Mentions', 'data' => $daily]])' role="img" aria-label="Daily health mentions"></canvas>
        </x-card>
        <x-card class="p-4">
            <h2 class="font-semibold mb-2">Most mentioned</h2>
            @forelse ($byDisease as $d => $n)
                <a href="{{ route('health.index', array_filter(['disease' => $d, 'country' => $filters['country'] ?? null, 'days' => $filters['days']])) }}" class="flex justify-between text-sm py-1 hover:underline"><span>{{ $label($d) }}</span><span class="tabular-nums text-slate-500">{{ $n }}</span></a>
            @empty
                <p class="text-sm text-slate-500">No health mentions in this period.</p>
            @endforelse
            @if ($byRegion->isNotEmpty())
                <h2 class="font-semibold mt-4 mb-2">By region</h2>
                @foreach ($byRegion as $r => $n)<div class="flex justify-between text-sm py-0.5"><span>{{ $r }}</span><span class="tabular-nums text-slate-500">{{ $n }}</span></div>@endforeach
            @endif
        </x-card>
    </div>

    <h2 class="font-semibold mb-3">Recent public mentions</h2>
    <div class="grid lg:grid-cols-2 gap-4">
        @forelse ($recent as $m)
            <x-card class="p-4">
                <div class="flex flex-wrap gap-1 mb-2">
                    @foreach ($m->diseases as $d)<span class="rounded-full bg-rose-50 text-rose-800 px-2 py-0.5 text-xs">{{ $label($d) }}</span>@endforeach
                    @foreach ($m->symptoms as $s)<span class="rounded-full bg-slate-100 text-slate-700 px-2 py-0.5 text-xs">{{ str_replace('_', ' ', $s) }}</span>@endforeach
                    @if ($m->region)<span class="rounded-full bg-indigo-50 text-indigo-800 px-2 py-0.5 text-xs">{{ $m->region }}</span>@endif
                </div>
                @if ($m->mentionable instanceof \App\Models\Post)
                    <a href="{{ route('posts.show', $m->mentionable) }}" class="text-sm hover:underline">{{ \Illuminate\Support\Str::limit($m->mentionable->body, 220) }}</a>
                @else
                    <a href="{{ $m->mentionable->url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-sm hover:underline">{{ $m->mentionable->title }}</a>
                @endif
                <div class="text-xs text-slate-500 mt-1">{{ $m->occurred_at->diffForHumans() }}</div>
            </x-card>
        @empty
            <x-empty>No public health mentions yet.</x-empty>
        @endforelse
    </div>
</x-app-layout>
