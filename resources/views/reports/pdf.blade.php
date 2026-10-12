<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SoleGlance report: {{ $report->topic }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
    h1 { font-size: 20px; margin: 0 0 4px; color: #3730a3; }
    h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
    .num { text-align: right; }
    .bar { height: 9px; background: #f1f5f9; }
    .bar span { display: inline-block; height: 9px; }
    .pos { background: #10b981; } .neu { background: #cbd5e1; } .neg { background: #f43f5e; }
    .tiles td { width: 25%; border: 1px solid #e2e8f0; padding: 8px; }
    .big { font-size: 18px; font-weight: bold; }
</style>
</head>
<body>
@php $t = $timeline['totals']; $total = max(1, array_sum($t)); $maxDay = max(1, ...array_map(fn ($i) => $timeline['positive'][$i] + $timeline['neutral'][$i] + $timeline['negative'][$i], array_keys($timeline['days']))); @endphp
<h1>Topic report: {{ $report->topic }}</h1>
<div class="muted">{{ $report->from->toFormattedDateString() }} – {{ $report->to->toFormattedDateString() }}{{ $report->country ? ' · region ' . $report->country : '' }} · generated {{ $generatedAt->toDayDateTimeString() }} UTC</div>

<h2>Summary</h2>
<table class="tiles"><tr>
    <td><div class="muted">Public posts</div><div class="big">{{ $postCount }}</div></td>
    <td><div class="muted">News articles</div><div class="big">{{ $articleCount }}</div></td>
    <td><div class="muted">Positive</div><div class="big">{{ round(100 * $t['positive'] / $total) }}%</div></td>
    <td><div class="muted">Negative</div><div class="big">{{ round(100 * $t['negative'] / $total) }}%</div></td>
</tr></table>
<p class="muted">Percentages are over {{ array_sum($t) }} items with a sentiment estimate. Estimates are produced automatically ({{ config('app.name') }} analytics service) and may misread sarcasm or mixed-language text.</p>

<h2>Daily sentiment</h2>
<table>
    <tr><th>Date</th><th class="num">Pos</th><th class="num">Neu</th><th class="num">Neg</th><th style="width:55%">Volume</th></tr>
    @foreach ($timeline['days'] as $i => $day)
        @php $p = $timeline['positive'][$i]; $n = $timeline['neutral'][$i]; $g = $timeline['negative'][$i]; @endphp
        <tr>
            <td>{{ $day }}</td><td class="num">{{ $p }}</td><td class="num">{{ $n }}</td><td class="num">{{ $g }}</td>
            <td><div class="bar"><span class="pos" style="width: {{ 100 * $p / $maxDay }}%"></span><span class="neu" style="width: {{ 100 * $n / $maxDay }}%"></span><span class="neg" style="width: {{ 100 * $g / $maxDay }}%"></span></div></td>
        </tr>
    @endforeach
</table>

@if ($categoryCounts->isNotEmpty())
    <h2>News categories</h2>
    <table>@foreach ($categoryCounts as $cat => $n)<tr><td>{{ ucfirst($cat) }}</td><td class="num">{{ $n }}</td></tr>@endforeach</table>
@endif

<h2>Most positive news</h2>
<table>@forelse ($mostPositive as $a)<tr><td>{{ $a->title }}<div class="muted">{{ $a->source?->name }} · {{ $a->published_at?->toDateString() }}</div></td><td class="num">{{ number_format($a->sentiment_score * 100) }}%</td></tr>@empty<tr><td class="muted">None</td></tr>@endforelse</table>

<h2>Most negative news</h2>
<table>@forelse ($mostNegative as $a)<tr><td>{{ $a->title }}<div class="muted">{{ $a->source?->name }} · {{ $a->published_at?->toDateString() }}</div></td><td class="num">{{ number_format($a->sentiment_score * 100) }}%</td></tr>@empty<tr><td class="muted">None</td></tr>@endforelse</table>

<h2>Recent public posts</h2>
<table>@forelse ($samplePosts as $p)<tr><td>{{ \Illuminate\Support\Str::limit($p->body, 280) }}<div class="muted">{{ '@' . $p->user->username }} · {{ $p->created_at->toDateString() }}</div></td><td>{{ $p->sentiment?->value }}</td></tr>@empty<tr><td class="muted">None</td></tr>@endforelse</table>
</body>
</html>
