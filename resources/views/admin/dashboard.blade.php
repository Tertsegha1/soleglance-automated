<x-app-layout title="Admin">
    @include('admin._nav')
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        @foreach ($stats as $label => $value)
            <x-card class="p-4"><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-2xl font-semibold tabular-nums">{{ number_format($value) }}</div></x-card>
        @endforeach
    </div>
    <div class="grid lg:grid-cols-3 gap-6">
        <x-card class="p-4 lg:col-span-2">
            <h2 class="font-semibold mb-2">Sign-ups (30 days)</h2>
            @if ($signups->isNotEmpty())
                <canvas data-chart="line" height="110" data-labels='@json($signups->keys())' data-series='@json([['label' => 'Sign-ups', 'data' => $signups->values()]])' role="img" aria-label="Daily sign-ups"></canvas>
            @else
                <x-empty>No sign-ups in the last 30 days.</x-empty>
            @endif
        </x-card>
        <x-card class="p-4">
            <h2 class="font-semibold mb-2">Analytics service</h2>
            @if (!$analyticsEnabled)
                <p class="text-sm text-amber-700">Not configured. Set <code>ANALYTICS_URL</code>.</p>
            @elseif ($analyticsHealth)
                <p class="text-sm text-emerald-700">Online</p>
                <dl class="text-xs text-slate-600 mt-2 space-y-1">
                    @foreach ($analyticsHealth as $k => $v)<div class="flex justify-between gap-2"><dt>{{ $k }}</dt><dd class="text-right">{{ is_scalar($v) ? var_export($v, true) : json_encode($v) }}</dd></div>@endforeach
                </dl>
            @else
                <p class="text-sm text-rose-700">Unreachable</p>
            @endif
        </x-card>
    </div>
    <x-card class="mt-6">
        <h2 class="font-semibold p-4 border-b">Recent moderation</h2>
        @include('admin._actions', ['actions' => $recentActions])
    </x-card>
</x-app-layout>
