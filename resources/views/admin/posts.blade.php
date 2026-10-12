<x-app-layout title="Moderate posts">
    @include('admin._nav')
    <div class="flex gap-2 mb-4 text-sm">
        @foreach (['recent' => 'Recent', 'negative' => 'Most negative', 'hidden' => 'Hidden'] as $value => $label)
            <a href="{{ route('admin.posts.index', ['filter' => $value]) }}" class="rounded px-3 py-1 border {{ $filter === $value ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ $label }}</a>
        @endforeach
    </div>
    <x-card>
        @forelse ($posts as $post)
            <div class="p-4 border-b last:border-b-0 {{ $post->hidden_at ? 'bg-rose-50/60' : '' }}">
                <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                    <a href="{{ $post->user->profileUrl() }}" class="font-medium text-slate-900 hover:underline">{{ $post->user->name }}</a>
                    <span>{{ $post->created_at->diffForHumans() }}</span><span>&middot; {{ $post->visibility->label() }}</span>
                    <x-sentiment-badge :sentiment="$post->sentiment" :score="$post->sentiment_score" />
                    @if ($post->hidden_at)<span class="text-rose-700">Hidden: {{ $post->hidden_reason ?: 'no reason given' }}</span>@endif
                </div>
                <p class="mt-1 whitespace-pre-line break-words">{{ \Illuminate\Support\Str::limit($post->body, 500) }}</p>
                <div class="mt-2">
                    @if ($post->hidden_at)
                        <form method="POST" action="{{ route('admin.posts.unhide', $post) }}">@csrf<button class="text-sm text-indigo-600 hover:underline">Restore</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.posts.hide', $post) }}" class="flex gap-2">@csrf
                            <input name="reason" maxlength="500" placeholder="Reason" class="rounded border-slate-300 text-xs">
                            <button class="text-sm text-rose-600 hover:underline">Hide post</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <x-empty>No posts.</x-empty>
        @endforelse
    </x-card>
    <div class="mt-4">{{ $posts->links() }}</div>
</x-app-layout>
