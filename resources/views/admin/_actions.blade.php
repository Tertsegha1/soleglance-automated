<table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="p-3">When</th><th class="p-3">Admin</th><th class="p-3">Action</th><th class="p-3">User</th><th class="p-3">Reason</th></tr></thead>
    <tbody>
        @forelse ($actions as $a)
            <tr class="border-t">
                <td class="p-3 whitespace-nowrap">{{ $a->created_at->toDateTimeString() }}</td>
                <td class="p-3">{{ $a->admin?->name ?? '—' }}</td>
                <td class="p-3">{{ str_replace('_', ' ', $a->action) }}{{ $a->target_post_id ? ' #' . $a->target_post_id : '' }}</td>
                <td class="p-3">{{ $a->targetUser ? '@' . $a->targetUser->username : '(deleted #' . $a->target_user_id . ')' }}</td>
                <td class="p-3">{{ $a->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty>No actions yet.</x-empty></td></tr>
        @endforelse
    </tbody>
</table>
