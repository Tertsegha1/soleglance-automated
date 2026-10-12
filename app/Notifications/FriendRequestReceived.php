<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $from)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'friend_request',
            'actor_id' => $this->from->id,
            'message' => "{$this->from->name} sent you a friend request.",
            'url' => route('friends.index'),
        ];
    }
}
