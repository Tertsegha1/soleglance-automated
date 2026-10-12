<x-app-layout title="Messages">
    <div class="grid md:grid-cols-3 gap-0 md:gap-4 h-[calc(100vh-10rem)] min-h-[28rem]">
        <x-card class="overflow-y-auto {{ $conversation ? 'hidden md:block' : '' }}">
            <h1 class="font-semibold p-4 border-b">Messages</h1>
            @forelse ($conversations as $c)
                @php $other = $c->otherParticipant(auth()->user()); @endphp
                @continue(!$other)
                <a href="{{ route('chat.show', $c) }}"
                   class="flex items-center gap-3 px-4 py-3 border-b hover:bg-slate-50 {{ $conversation?->id === $c->id ? 'bg-indigo-50' : '' }}">
                    <x-avatar :user="$other" class="h-10 w-10" />
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium truncate">{{ $other->name }}</span>
                            @if ($c->last_message_at)<span class="text-xs text-slate-500 shrink-0">{{ $c->last_message_at->shortRelativeDiffForHumans() }}</span>@endif
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-sm text-slate-500 truncate">{{ $c->latestMessage?->body }}</span>
                            @if ($c->unread)<span class="rounded-full bg-indigo-600 text-white text-xs px-2 shrink-0">{{ $c->unread }}</span>@endif
                        </div>
                    </div>
                </a>
            @empty
                <x-empty>No conversations yet. Open a friend's profile and choose <strong>Message</strong>.</x-empty>
            @endforelse
        </x-card>

        <x-card class="md:col-span-2 flex flex-col overflow-hidden {{ $conversation ? '' : 'hidden md:flex' }}">
            @if ($conversation)
                <div class="flex items-center gap-3 p-3 border-b">
                    <a href="{{ route('chat.index') }}" class="md:hidden text-slate-500" aria-label="Back to conversations">&larr;</a>
                    @if ($other)
                        <x-avatar :user="$other" class="h-9 w-9" />
                        <a href="{{ $other->profileUrl() }}" class="font-semibold hover:underline">{{ $other->name }}</a>
                    @endif
                    <form method="get" class="ml-auto hidden sm:block">
                        <label for="chat-search" class="sr-only">Search this conversation</label>
                        <input id="chat-search" type="search" name="q" value="{{ $search }}" placeholder="Search messages" class="rounded-full border-slate-300 text-sm py-1">
                    </form>
                    <form method="POST" action="{{ route('chat.clear', $conversation) }}" onsubmit="return confirm('Delete this chat history for you? The other person keeps their copy.')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-rose-600 hover:underline">Delete chat</button>
                    </form>
                </div>

                <div id="chat"
                     class="flex-1 flex flex-col min-h-0"
                     data-conversation="{{ $conversation->id }}"
                     data-me="{{ auth()->id() }}"
                     data-send-url="{{ route('chat.send', $conversation) }}"
                     data-read-url="{{ route('chat.read', $conversation) }}"
                     data-delete-url="{{ url('/messages/' . $conversation->id . '/messages') }}">
                    <ol data-messages class="flex-1 overflow-y-auto p-4 space-y-2" aria-live="polite">
                        @if ($search)
                            <li class="text-center text-xs text-slate-500">Showing messages matching "{{ $search }}". <a class="underline" href="{{ route('chat.show', $conversation) }}">Clear</a></li>
                        @endif
                        @forelse ($messages as $m)
                            @include('chat._message', ['m' => $m])
                        @empty
                            <li data-empty class="text-center text-sm text-slate-500 py-8">No messages yet. Say hello!</li>
                        @endforelse
                    </ol>
                    <form data-composer method="POST" action="{{ route('chat.send', $conversation) }}" class="flex gap-2 p-3 border-t">
                        @csrf
                        <label for="message-body" class="sr-only">Message</label>
                        <input id="message-body" name="body" required maxlength="5000" autocomplete="off" placeholder="Type a message…" class="flex-1 rounded-full border-slate-300">
                        <x-primary-button>Send</x-primary-button>
                    </form>
                </div>
            @else
                <x-empty class="m-auto">Select a conversation.</x-empty>
            @endif
        </x-card>
    </div>
</x-app-layout>
