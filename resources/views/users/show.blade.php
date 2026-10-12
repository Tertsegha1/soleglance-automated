<x-app-layout :title="$profile->name">
    <div class="max-w-3xl mx-auto space-y-4">
        <x-card class="p-5">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <x-avatar :user="$profile" class="h-24 w-24" />
                <div class="flex-1">
                    <h1 class="text-2xl font-semibold">{{ $profile->name }}</h1>
                    <p class="text-slate-500">{{ '@' . $profile->username }}
                        @if ($profile->city || $profile->country) &middot; {{ collect([$profile->city, $profile->country])->filter()->implode(', ') }} @endif
                        &middot; {{ $friendCount }} {{ \Illuminate\Support\Str::plural('friend', $friendCount) }}
                    </p>
                    @if ($profile->bio)<p class="mt-2 whitespace-pre-line">{{ $profile->bio }}</p>@endif
                    @if ($profile->isSuspended())<p class="mt-2 text-sm text-rose-700">This account is suspended.</p>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if (auth()->id() === $profile->id)
                        <a href="{{ route('profile.edit') }}" class="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50">Edit profile</a>
                    @elseif (!$friendship)
                        <form method="POST" action="{{ route('friends.store', $profile) }}">@csrf<x-primary-button>Add friend</x-primary-button></form>
                    @elseif ($friendship->status === \App\Enums\FriendshipStatus::Accepted)
                        <form method="POST" action="{{ route('chat.with', $profile) }}">@csrf<x-primary-button>Message</x-primary-button></form>
                        <form method="POST" action="{{ route('friends.destroy', $profile) }}" onsubmit="return confirm('Remove {{ e($profile->name) }} from your friends?')">@csrf @method('DELETE')<x-secondary-button type="submit">Unfriend</x-secondary-button></form>
                    @elseif ($friendship->requester_id === auth()->id())
                        <form method="POST" action="{{ route('friends.destroy', $profile) }}">@csrf @method('DELETE')<x-secondary-button type="submit">Cancel request</x-secondary-button></form>
                    @else
                        <form method="POST" action="{{ route('friends.accept', $profile) }}">@csrf<x-primary-button>Accept request</x-primary-button></form>
                        <form method="POST" action="{{ route('friends.decline', $profile) }}">@csrf<x-secondary-button type="submit">Decline</x-secondary-button></form>
                    @endif
                </div>
            </div>
        </x-card>

        @forelse ($posts as $post)
            <x-post-card :post="$post" />
        @empty
            <x-card><x-empty>No posts to show.</x-empty></x-card>
        @endforelse
        {{ $posts->links() }}
    </div>
</x-app-layout>
