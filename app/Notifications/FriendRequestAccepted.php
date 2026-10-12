<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $by)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'friend_accepted',
            'actor_id' => $this->by->id,
            'message' => "{$this->by->name} accepted your friend request.",
            'url' => $this->by->profileUrl(),
        ];
    }
}
