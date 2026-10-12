<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostCommented extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $by, public int $postId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'comment',
            'actor_id' => $this->by->id,
            'post_id' => $this->postId,
            'message' => "{$this->by->name} commented on your post.",
            'url' => route('posts.show', $this->postId),
        ];
    }
}
