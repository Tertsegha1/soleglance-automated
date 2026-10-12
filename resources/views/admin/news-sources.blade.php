<x-app-layout title="News sources">
    @include('admin._nav')
    <div class="grid lg:grid-cols-3 gap-6">
        <x-card class="p-5 h-fit">
            <h2 class="font-semibold mb-3">Add RSS/Atom feed</h2>
            <form method="POST" action="{{ route('admin.news.store') }}" class="space-y-3">
                @csrf
                <div><x-input-label for="name" value="Name" /><x-text-input id="name" name="name" class="mt-1 w-full" required :value="old('name')" /></div>
                <div><x-input-label for="feed_url" value="Feed URL" /><x-text-input id="feed_url" name="feed_url" type="url" class="mt-1 w-full" required :value="old('feed_url')" /></div>
                <div><x-input-label for="category" value="Category (optional)" />
                    <select id="category" name="category" class="mt-1 w-full rounded-md border-slate-300"><option value="">Detect automatically</option>
                        @foreach ($categories as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
                    </select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><x-input-label for="country" value="Country" /><x-text-input id="country" name="country" maxlength="2" class="mt-1 w-full uppercase" :value="old('country')" /></div>
                    <div><x-input-label for="language" value="Language" />
                        <select id="language" name="language" class="mt-1 w-full rounded-md border-slate-300"><option value="en">English</option><option value="ur">Urdu</option></select></div>
                </div>
                <p class="text-xs text-slate-500">Only add feeds whose terms allow automated reading. Articles are linked, not republished.</p>
                <x-primary-button>Add source</x-primary-button>
            </form>
        </x-card>
        <x-card class="lg:col-span-2 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500"><tr><th class="p-3">Source</th><th class="p-3">Category</th><th class="p-3 text-right">Articles</th><th class="p-3">Last fetch</th><th class="p-3"></th></tr></thead>
                <tbody>
                    @forelse ($sources as $s)
                        <tr class="border-t align-top {{ $s->active ? '' : 'opacity-60' }}">
                            <td class="p-3"><div class="font-medium">{{ $s->name }}</div><div class="text-xs text-slate-500 break-all">{{ $s->feed_url }}</div>
                                @if ($s->last_error)<div class="text-xs text-rose-700 mt-1">{{ $s->last_error }}</div>@endif</td>
                            <td class="p-3">{{ $s->category?->label() ?? 'auto' }}{{ $s->country ? ' · ' . $s->country : '' }}</td>
                            <td class="p-3 text-right">{{ $s->articles_count }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $s->last_fetched_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="p-3 whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.news.toggle', $s) }}" class="inline">@csrf<button class="text-indigo-600 hover:underline">{{ $s->active ? 'Pause' : 'Resume' }}</button></form>
                                <form method="POST" action="{{ route('admin.news.destroy', $s) }}" class="inline ml-2" onsubmit="return confirm('Remove this source and all of its articles?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Remove</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty>No sources yet.</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</x-app-layout>
