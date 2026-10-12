@props(['sentiment', 'score' => null])
@if ($sentiment)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ' . $sentiment->colorClass()]) }}
          title="Automatic sentiment estimate{{ $score !== null ? ' (confidence ' . number_format($score * 100) . '%)' : '' }}">
        {{ ucfirst($sentiment->value) }}
    </span>
@endif
