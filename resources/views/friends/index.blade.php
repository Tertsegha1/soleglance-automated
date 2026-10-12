<x-app-layout title="Friends">
    <div class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-4">
            @if ($incoming->isNotEmpty())
                <x-card class="p-4">
                    <h2 class="font-semibold mb-3">Friend requests</h2>
                    @foreach ($incoming as $req)
                        <div class="flex items-center gap-3 py-2 border-t first:border-t-0">
                            <x-avatar :user="$req->requester" class="h-10 w-10" />
                            <a href="{{ $req->requester->profileUrl() }}" class="flex-1 font-medium hover:underline">{{ $req->requester->name }}</a>
                            <form method="POST" action="{{ route('friends.accept', $req->requester) }}">@csrf<x-primary-button>Accept</x-primary-button></form>
                            <form method="POST" action="{{ route('friends.decline', $req->requester) }}">@csrf<x-secondary-button type="submit">Decline</x-secondary-button></form>
                        </div>
                    @endforeach
                </x-card>
            @endif

            <x-card class="p-4">
                <h2 class="font-semibold mb-3">Your friends ({{ $friends->count() }})</h2>
                @forelse ($friends as $friend)
                    <div class="flex items-center gap-3 py-2 border-t first:border-t-0">
                        <x-avatar :user="$friend" class="h-10 w-10" />
                        <div class="flex-1">
                            <a href="{{ $friend->profileUrl() }}" class="font-medium hover:underline">{{ $friend->name }}</a>
                            <div class="text-xs text-slate-500">{{ '@' . $friend->username }}</div>
                        </div>
                        <form method="POST" action="{{ route('chat.with', $friend) }}">@csrf<x-secondary-button type="submit">Message</x-secondary-button></form>
                        <form method="POST" action="{{ route('friends.destroy', $friend) }}" onsubmit="return confirm('Remove {{ e($friend->name) }}?')">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Unfriend</button></form>
                    </div>
                @empty
                    <x-empty>No friends yet. Use search to find people you know.</x-empty>
                @endforelse
            </x-card>
        </div>

        <aside class="space-y-4">
            @if ($outgoing->isNotEmpty())
                <x-card class="p-4">
                    <h2 class="font-semibold mb-3">Sent requests</h2>
                    @foreach ($outgoing as $req)
                        <div class="flex items-center gap-2 py-1.5 text-sm">
                            <a href="{{ $req->addressee->profileUrl() }}" class="flex-1 hover:underline">{{ $req->addressee->name }}</a>
                            <form method="POST" action="{{ route('friends.destroy', $req->addressee) }}">@csrf @method('DELETE')<button class="text-slate-500 hover:underline">Cancel</button></form>
                        </div>
                    @endforeach
                </x-card>
            @endif
            <x-card class="p-4">
                <h2 class="font-semibold mb-3">People you may know</h2>
                @forelse ($suggestions as $s)
                    <div class="flex items-center gap-2 py-1.5">
                        <x-avatar :user="$s" class="h-8 w-8" />
                        <a href="{{ $s->profileUrl() }}" class="flex-1 text-sm hover:underline">{{ $s->name }}</a>
                        <form method="POST" action="{{ route('friends.store', $s) }}">@csrf<button class="text-sm text-indigo-600 hover:underline">Add</button></form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No suggestions right now.</p>
                @endforelse
            </x-card>
        </aside>
    </div>
</x-app-layout>
