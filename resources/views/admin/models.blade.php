<x-app-layout title="Models">
    @include('admin._nav')
    @if (!$enabled)
        <x-card class="p-4 text-amber-800">The analytics service is not configured (ANALYTICS_URL).</x-card>
    @else
        <div class="grid lg:grid-cols-3 gap-6">
            <x-card class="p-4">
                <h2 class="font-semibold mb-2">Service</h2>
                @if ($health)
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between"><dt>Sentiment</dt><dd>{{ $health['sentiment_model'] ?? '?' }}</dd></div>
                        <div class="flex justify-between"><dt>Topics</dt><dd>{{ $health['topic_model'] ?? '?' }}</dd></div>
                        @foreach ($health['health_models'] ?? [] as $k => $v)<div class="flex justify-between"><dt>Health: {{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach
                    </dl>
                @else
                    <p class="text-sm text-rose-700">Unreachable.</p>
                @endif
                <h2 class="font-semibold mt-4 mb-2">Feedback</h2>
                <p class="text-sm">{{ $feedback['total'] }} corrections, {{ $feedback['unsent'] }} not yet sent.</p>
                <ul class="text-xs text-slate-600">@foreach ($feedback['by_task'] as $t => $n)<li>{{ $t }}: {{ $n }}</li>@endforeach</ul>
                <form method="POST" action="{{ route('admin.models.retrain') }}" class="mt-3">@csrf<x-primary-button>Retrain now</x-primary-button></form>
                <p class="text-xs text-slate-500 mt-2">Retraining runs automatically after {{ config('health.retrain_after_feedback') }} new corrections. A new model replaces the current one only if no language gets worse on held-out data.</p>
            </x-card>

            <x-card class="p-4">
                <h2 class="font-semibold mb-2">Live monitoring</h2>
                @php $m = $metrics['monitor'] ?? null; @endphp
                @if ($m)
                    <p class="text-sm">{{ number_format($m['observed']) }} texts scored since start.</p>
                    <h3 class="text-sm font-medium mt-2">Languages</h3>
                    <ul class="text-xs text-slate-600">@foreach ($m['languages'] as $l => $n)<li>{{ $l }}: {{ $n }}</li>@endforeach</ul>
                    @isset($m['psi'])
                        <h3 class="text-sm font-medium mt-2">Drift (PSI vs. validation data)</h3>
                        <ul class="text-xs">@foreach ($m['psi'] as $k => $v)<li class="{{ $v > 0.2 ? 'text-rose-700 font-medium' : 'text-slate-600' }}">{{ $k }}: {{ number_format($v, 3) }}</li>@endforeach</ul>
                        @if ($m['drift_alert'] ?? false)<p class="text-xs text-rose-700 mt-1">Significant drift (PSI &gt; 0.2): review recent corrections and consider retraining.</p>@endif
                    @else
                        <p class="text-xs text-slate-500 mt-2">Drift is reported after 100 scored texts.</p>
                    @endisset
                    @if (($metrics['retrain']['last'] ?? null))
                        <h3 class="text-sm font-medium mt-3">Last retraining</h3>
                        <pre class="text-[11px] bg-slate-50 p-2 rounded overflow-x-auto">{{ json_encode($metrics['retrain']['last'], JSON_PRETTY_PRINT) }}</pre>
                    @endif
                @else
                    <p class="text-sm text-slate-500">No data.</p>
                @endif
            </x-card>

            <x-card class="p-4">
                <h2 class="font-semibold mb-2">Model registry</h2>
                @forelse ($registry ?? [] as $name => $entry)
                    <h3 class="text-sm font-medium mt-2">{{ $name }} <span class="text-slate-500">(active {{ $entry['active'] ?? '—' }})</span></h3>
                    <ul class="text-xs space-y-1">
                        @foreach (array_reverse($entry['versions'] ?? []) as $v)
                            <li class="{{ $v['status'] === 'rejected' ? 'text-rose-700' : ($v['status'] === 'active' ? 'text-emerald-700' : 'text-slate-500') }}">{{ $v['version'] }} · {{ $v['status'] }} · {{ $v['created_at'] }}</li>
                        @endforeach
                    </ul>
                @empty
                    <p class="text-sm text-slate-500">No registry yet.</p>
                @endforelse
            </x-card>
        </div>
    @endif
</x-app-layout>
