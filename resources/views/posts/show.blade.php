<x-app-layout title="Post">
    <div class="max-w-2xl mx-auto space-y-4">
        <x-post-card :post="$post" :full="true" />

        <x-card class="p-4 sm:p-5" id="comments">
            <h2 class="font-semibold mb-3">Comments</h2>
            <div class="space-y-4">
                @forelse ($post->comments as $comment)
                    <div class="flex gap-3" id="comment-{{ $comment->id }}">
                        <a href="{{ $comment->user->profileUrl() }}"><x-avatar :user="$comment->user" class="h-8 w-8" /></a>
                        <div class="flex-1 rounded-lg bg-slate-50 px-3 py-2">
                            <div class="flex items-center gap-2 text-sm">
                                <a href="{{ $comment->user->profileUrl() }}" class="font-semibold hover:underline">{{ $comment->user->name }}</a>
                                <span class="text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
                                <x-sentiment-badge :sentiment="$comment->sentiment" class="ml-auto" />
                                @can('delete', $comment)
                                    <form method="POST" action="{{ route('comments.destroy', $comment) }}" onsubmit="return confirm('Delete this comment?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                    </form>
                                @endcan
                            </div>
                            <p class="text-sm mt-1 whitespace-pre-line break-words">{{ $comment->body }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No comments yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('comments.store', $post) }}" class="mt-4 flex gap-2">
                @csrf
                <label for="comment-body" class="sr-only">Write a comment</label>
                <input id="comment-body" name="body" required maxlength="2000" placeholder="Write a comment…" class="flex-1 rounded-lg border-slate-300 text-sm">
                <x-primary-button>Comment</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
