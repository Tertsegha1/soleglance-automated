<x-card class="p-4 sm:p-5">
    <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data" x-data="{ visibility: '{{ old('visibility', 'public') }}', files: [] }">
        @csrf
        <div class="flex gap-3">
            <x-avatar :user="auth()->user()" class="h-10 w-10" />
            <div class="flex-1">
                <label for="body" class="sr-only">What's on your mind?</label>
                <textarea id="body" name="body" rows="3" required maxlength="10000"
                          class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="What's on your mind, {{ auth()->user()->name }}?">{{ old('body') }}</textarea>

                <div x-show="visibility === 'custom'" x-cloak class="mt-2">
                    <label for="audience" class="block text-xs font-medium text-slate-600 mb-1">Visible to (hold Ctrl/Cmd to select several)</label>
                    <select id="audience" name="audience[]" multiple class="w-full rounded-lg border-slate-300 text-sm" size="4">
                        @forelse ($friends as $friend)
                            <option value="{{ $friend->id }}" @selected(in_array($friend->id, old('audience', [])))>{{ $friend->name }} ({{ '@' . $friend->username }})</option>
                        @empty
                            <option disabled>You have no friends yet</option>
                        @endforelse
                    </select>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer hover:text-indigo-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m4 16 4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M14 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
                        Photo/video
                        <input type="file" name="media[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" class="sr-only"
                               @change="files = Array.from($event.target.files).map(f => f.name)">
                    </label>
                    <span class="text-xs text-slate-500" x-text="files.join(', ')"></span>

                    <label for="visibility" class="sr-only">Visibility</label>
                    <select id="visibility" name="visibility" x-model="visibility" class="ml-auto rounded-lg border-slate-300 text-sm py-1.5">
                        @foreach ($visibilities as $v)
                            <option value="{{ $v->value }}">{{ $v->label() }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>Post</x-primary-button>
                </div>
            </div>
        </div>
    </form>
</x-card>
