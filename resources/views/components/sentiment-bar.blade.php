@props(['positive' => 0, 'neutral' => 0, 'negative' => 0])
@php $total = max(1, $positive + $neutral + $negative); @endphp
<div {{ $attributes->merge(['class' => 'flex h-2 w-full overflow-hidden rounded-full bg-slate-100']) }}
     role="img" aria-label="{{ $positive }} positive, {{ $neutral }} neutral, {{ $negative }} negative">
    <div class="bg-emerald-500" style="width: {{ 100 * $positive / $total }}%"></div>
    <div class="bg-slate-300" style="width: {{ 100 * $neutral / $total }}%"></div>
    <div class="bg-rose-500" style="width: {{ 100 * $negative / $total }}%"></div>
</div>
