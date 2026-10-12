<x-app-layout title="Health alerts">
    @include('admin._health_nav')
    <div class="flex gap-2 mb-4 text-sm">
        @foreach (['open', 'acknowledged', 'resolved', 'dismissed', 'all'] as $s)
            <a href="{{ route('admin.health-alerts.index', ['status' => $s]) }}" class="rounded px-3 py-1 border {{ $status === $s ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ ucfirst($s) }}</a>
        @endforeach
    </div>
    <x-card>
        @forelse ($alerts as $a)
            <div class="p-4 border-b last:border-b-0">
                <div class="flex flex-wrap items-center gap-2">
                    <strong>{{ $a->title() }}</strong>
                    <span class="text-sm text-slate-500">{{ $a->window_start->toDateString() }} – {{ $a->window_end->toDateString() }} · {{ $a->count }} mentions · EARS {{ $a->method }} = {{ number_format($a->statistic, 1) }} · usual {{ number_format($a->baseline_mean ?? 0, 1) }}/day</span>
                    <span class="ml-auto text-xs rounded-full bg-slate-100 px-2 py-0.5">{{ $a->status }}{{ $a->handler ? ' by ' . $a->handler->name : '' }}</span>
                </div>
                @if ($a->notes)<p class="text-sm mt-1 text-slate-700">{{ $a->notes }}</p>@endif
                <form method="POST" action="{{ route('admin.health-alerts.update', $a) }}" class="mt-2 flex flex-wrap gap-2 items-center">
                    @csrf @method('PATCH')
                    <select name="status" class="rounded border-slate-300 text-sm">
                        @foreach (['acknowledged', 'resolved', 'dismissed', 'open'] as $s)<option value="{{ $s }}" @selected($a->status === $s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                    <input name="notes" value="{{ $a->notes }}" maxlength="2000" placeholder="Notes (e.g. verified with state epidemiologist)" class="flex-1 min-w-64 rounded border-slate-300 text-sm">
                    <x-primary-button>Save</x-primary-button>
                    <a class="text-sm text-indigo-600 hover:underline" href="{{ route('health.index', ['disease' => $a->disease, 'country' => $a->country]) }}">View mentions</a>
                </form>
            </div>
        @empty
            <x-empty>No alerts.</x-empty>
        @endforelse
    </x-card>
    <div class="mt-4">{{ $alerts->links() }}</div>
</x-app-layout>
