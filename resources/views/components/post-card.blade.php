@props(['post', 'full' => false])
<x-card class="p-4 sm:p-5 relative" id="post-{{ $post->id }}">
    <div class="flex items-start gap-3">
        <a href="{{ $post->user->profileUrl() }}"><x-avatar :user="$post->user" class="h-10 w-10" /></a>
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <a href="{{ $post->user->profileUrl() }}" class="font-semibold hover:underline">{{ $post->user->name }}</a>
                <span class="text-slate-500">{{ '@' . $post->user->username }}</span>
                <span class="text-slate-400">&middot;</span>
                <a href="{{ route('posts.show', $post) }}" class="text-slate-500 hover:underline" title="{{ $post->created_at->toDayDateTimeString() }}">{{ $post->created_at->diffForHumans() }}</a>
                <span class="text-slate-400" title="Visibility">&middot; {{ $post->visibility->label() }}</span>
                <div class="ml-auto flex items-center gap-1" x-data="{ open: false }">
                    <x-sentiment-badge :sentiment="$post->sentiment" :score="$post->sentiment_score" />
                    @if ($post->sentiment)
                        <button type="button" @click="open = !open" class="text-xs text-slate-400 hover:text-slate-700" title="Is this label right?" aria-label="Correct the sentiment label">?</button>
                        <form x-show="open" x-cloak @click.outside="open = false" method="POST" action="{{ route('feedback.store') }}" class="absolute z-10 mt-16 right-6 rounded-lg border bg-white shadow p-2 text-xs flex items-center gap-1">
                            @csrf
                            <input type="hidden" name="subject_type" value="post"><input type="hidden" name="subject_id" value="{{ $post->id }}"><input type="hidden" name="task" value="sentiment">
                            <span>Should be:</span>
                            @foreach (['positive', 'neutral', 'negative'] as $l)<button name="corrected" value="{{ $l }}" class="rounded border px-2 py-0.5 hover:bg-slate-50">{{ $l }}</button>@endforeach
                        </form>
                    @endif
                </div>
            </div>
            @if ($post->misinformationReview?->status === 'confirmed')
                <div class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-2 text-sm text-amber-900" role="note">
                    <strong>Health fact-check:</strong> {{ $post->misinformationReview->correction }}
                    @if ($post->misinformationReview->source_url)<a href="{{ $post->misinformationReview->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="underline">Source</a>@endif
                </div>
            @endif

            <div class="mt-2 whitespace-pre-line break-words text-slate-800">{{ $full ? $post->body : \Illuminate\Support\Str::limit($post->body, 600) }}</div>
            @if (!$full && mb_strlen($post->body) > 600)
                <a href="{{ route('posts.show', $post) }}" class="text-sm text-indigo-600 hover:underline">Read more</a>
            @endif

            @if ($post->media->isNotEmpty())
                <div class="mt-3 grid gap-2 {{ $post->media->count() > 1 ? 'grid-cols-2' : '' }}">
                    @foreach ($post->media as $media)
                        @if ($media->type === 'image')
                            <img src="{{ $media->url() }}" alt="Image attached to post" class="rounded-lg max-h-96 w-full object-cover" loading="lazy">
                        @else
                            <video src="{{ $media->url() }}" controls preload="metadata" class="rounded-lg max-h-96 w-full bg-black"></video>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="rounded-full border border-slate-200 px-3 py-1 hover:bg-slate-50 {{ $post->my_reaction ? 'bg-indigo-50 border-indigo-200' : '' }}">
                        {{ $post->my_reaction ? $post->my_reaction->emoji() . ' ' . ucfirst($post->my_reaction->value) : '👍 React' }}
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute z-10 bottom-full mb-1 flex gap-1 rounded-full bg-white border shadow px-2 py-1">
                        @foreach (\App\Enums\ReactionType::cases() as $type)
                            <form method="POST" action="{{ route('posts.react', $post) }}">
                                @csrf
                                <input type="hidden" name="type" value="{{ $type->value }}">
                                <button class="text-xl hover:scale-125 transition" title="{{ ucfirst($type->value) }}" aria-label="{{ ucfirst($type->value) }}">{{ $type->emoji() }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
                @if ($post->reaction_total)
                    <span class="text-slate-500" title="{{ collect($post->reaction_counts)->map(fn ($n, $t) => "$n $t")->implode(', ') }}">
                        @foreach (array_slice($post->reaction_counts, 0, 3, true) as $type => $n){{ \App\Enums\ReactionType::from($type)->emoji() }}@endforeach
                        {{ $post->reaction_total }}
                    </span>
                @endif
                <a href="{{ route('posts.show', $post) }}#comments" class="text-slate-500 hover:underline">{{ $post->comments_count ?? $post->comments->count() }} {{ \Illuminate\Support\Str::plural('comment', $post->comments_count ?? $post->comments->count()) }}</a>

                @can('update', $post)
                    <a href="{{ route('posts.edit', $post) }}" class="ml-auto text-slate-500 hover:underline">Edit</a>
                @endcan
                @can('delete', $post)
                    <form method="POST" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Delete this post?')" class="{{ auth()->user()->can('update', $post) ? '' : 'ml-auto' }}">
                        @csrf @method('DELETE')
                        <button class="text-rose-600 hover:underline">Delete</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-card>
