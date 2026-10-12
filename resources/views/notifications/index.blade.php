<x-app-layout title="Notifications">
    <div class="max-w-2xl mx-auto">
        <x-card>
            <div class="flex items-center justify-between p-4 border-b">
                <h1 class="font-semibold">Notifications</h1>
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-sm text-indigo-600 hover:underline">Mark all as read</button></form>
            </div>
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 p-4 border-b last:border-b-0 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-indigo-50/50' }}">
                    @if (!$n->read_at)<span class="mt-1.5 h-2 w-2 rounded-full bg-indigo-600 shrink-0" aria-label="Unread"></span>@endif
                    <div class="flex-1">
                        <p class="text-sm">{{ $n->data['message'] ?? 'Notification' }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <x-empty>You're all caught up.</x-empty>
            @endforelse
        </x-card>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </div>
</x-app-layout>
