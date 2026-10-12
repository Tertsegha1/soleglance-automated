<x-app-layout title="Users">
    @include('admin._nav')
    <form method="get" class="flex flex-wrap gap-2 mb-4">
        <x-text-input name="q" :value="$q" placeholder="Name, username or email" />
        <select name="status" class="rounded-md border-slate-300">
            <option value="">All</option>
            <option value="suspended" @selected($status === 'suspended')>Suspended</option>
            <option value="admins" @selected($status === 'admins')>Admins</option>
        </select>
        <x-primary-button>Filter</x-primary-button>
    </form>
    <x-card class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th class="p-3">User</th><th class="p-3">Email</th><th class="p-3">Joined</th><th class="p-3 text-right">Posts</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-t align-top">
                        <td class="p-3"><a href="{{ $user->profileUrl() }}" class="font-medium hover:underline">{{ $user->name }}</a><div class="text-xs text-slate-500">{{ '@' . $user->username }}{{ $user->isAdmin() ? ' · admin' : '' }}</div></td>
                        <td class="p-3">{{ $user->email }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $user->created_at->toDateString() }}</td>
                        <td class="p-3 text-right">{{ $user->posts_count }}</td>
                        <td class="p-3">@if ($user->isSuspended())<span class="text-rose-700" title="{{ $user->suspension_reason }}">Suspended</span>@else Active @endif</td>
                        <td class="p-3">
                            @unless ($user->isAdmin())
                                <div x-data="{ open: false }">
                                    @if ($user->isSuspended())
                                        <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">@csrf<button class="text-indigo-600 hover:underline">Reinstate</button></form>
                                    @else
                                        <button type="button" @click="open = !open" class="text-amber-700 hover:underline">Suspend…</button>
                                        <form x-show="open" x-cloak method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mt-2 flex gap-1">
                                            @csrf
                                            <input name="reason" maxlength="500" placeholder="Reason (sent to user)" class="rounded border-slate-300 text-xs">
                                            <button class="text-xs rounded bg-amber-600 text-white px-2">Suspend</button>
                                        </form>
                                    @endif
                                    <details class="mt-1">
                                        <summary class="text-rose-700 cursor-pointer">Delete…</summary>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-2 flex gap-1">
                                            @csrf @method('DELETE')
                                            <input name="confirm" required placeholder="Type {{ $user->username }}" class="rounded border-slate-300 text-xs">
                                            <button class="text-xs rounded bg-rose-600 text-white px-2">Delete permanently</button>
                                        </form>
                                    </details>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
