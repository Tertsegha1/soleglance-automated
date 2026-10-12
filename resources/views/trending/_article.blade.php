<x-card class="p-4">
    <div class="flex items-start gap-3">
        <div class="flex-1 min-w-0">
            <a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-medium hover:underline">{{ $article->title }}</a>
            <div class="text-xs text-slate-500 mt-0.5">
                {{ $article->source?->name }} &middot; {{ $article->published_at?->diffForHumans() }}
                @if ($article->category) &middot; {{ $article->category->label() }} @endif
            </div>
            @if ($article->summary)<p class="text-sm text-slate-600 mt-1 line-clamp-2">{{ $article->summary }}</p>@endif
        </div>
        <x-sentiment-badge :sentiment="$article->sentiment" :score="$article->sentiment_score" />
    </div>
</x-card>
