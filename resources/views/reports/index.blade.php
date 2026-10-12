<x-app-layout title="Reports">
    <div class="grid md:grid-cols-3 gap-6">
        <x-card class="p-5 h-fit">
            <h1 class="font-semibold text-lg">New report</h1>
            <p class="text-sm text-slate-500 mt-1">A PDF with sentiment over time, category breakdown and example news and posts for a topic. Only public content is included.</p>
            <form method="POST" action="{{ route('reports.store') }}" class="mt-4 space-y-3">
                @csrf
                <div><x-input-label for="topic" value="Topic or keyword" /><x-text-input id="topic" name="topic" class="mt-1 w-full" required maxlength="100" :value="old('topic', $prefill['topic'] ?? '')" /></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><x-input-label for="from" value="From" /><x-text-input id="from" name="from" type="date" class="mt-1 w-full" required :value="old('from', $prefill['from'] ?? now()->subDays(13)->toDateString())" /></div>
                    <div><x-input-label for="to" value="To" /><x-text-input id="to" name="to" type="date" class="mt-1 w-full" required :value="old('to', $prefill['to'] ?? now()->toDateString())" /></div>
                </div>
                <div><x-input-label for="country" value="Region (optional ISO code)" /><x-text-input id="country" name="country" maxlength="2" class="mt-1 w-24 uppercase" :value="old('country', $prefill['country'] ?? '')" /></div>
                <x-primary-button>Generate</x-primary-button>
            </form>
        </x-card>

        <x-card class="md:col-span-2">
            <h2 class="font-semibold p-4 border-b">Your reports</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500"><tr><th class="p-3">Topic</th><th class="p-3">Period</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr class="border-t">
                            <td class="p-3 font-medium">{{ $report->topic }}{{ $report->country ? " ({$report->country})" : '' }}</td>
                            <td class="p-3">{{ $report->from->toDateString() }} – {{ $report->to->toDateString() }}</td>
                            <td class="p-3">
                                @if ($report->status === 'ready') <span class="text-emerald-700">Ready</span>
                                @elseif ($report->status === 'failed') <span class="text-rose-700" title="{{ $report->error }}">Failed</span>
                                @else <span class="text-slate-500">Generating…</span> @endif
                            </td>
                            <td class="p-3 text-right">@if ($report->status === 'ready')<a class="text-indigo-600 hover:underline" href="{{ route('reports.download', $report) }}">Download PDF</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty>No reports yet.</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $reports->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
