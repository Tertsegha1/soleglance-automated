<x-app-layout title="Edit post">
    <div class="max-w-2xl mx-auto">
        <x-card class="p-5">
            <h1 class="font-semibold text-lg mb-4">Edit post</h1>
            <form method="POST" action="{{ route('posts.update', $post) }}" class="space-y-4">
                @csrf @method('PATCH')
                <div>
                    <x-input-label for="body" value="Post" />
                    <textarea id="body" name="body" rows="6" required maxlength="10000" class="mt-1 w-full rounded-lg border-slate-300">{{ old('body', $post->body) }}</textarea>
                </div>
                <div>
                    <x-input-label for="visibility" value="Visibility" />
                    <select id="visibility" name="visibility" class="mt-1 rounded-lg border-slate-300">
                        @foreach ($visibilities as $v)
                            @continue($v === \App\Enums\Visibility::Custom)
                            <option value="{{ $v->value }}" @selected(old('visibility', $post->visibility->value) === $v->value)>{{ $v->label() }}</option>
                        @endforeach
                    </select>
                    @if ($post->visibility === \App\Enums\Visibility::Custom)
                        <p class="text-xs text-slate-500 mt-1">This post is shared with specific friends. Choosing another option replaces that list.</p>
                    @endif
                </div>
                <div class="flex gap-3">
                    <x-primary-button>Save</x-primary-button>
                    <a href="{{ route('posts.show', $post) }}" class="px-4 py-2 text-sm text-slate-600">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
