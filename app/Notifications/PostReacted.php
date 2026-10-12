<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostReacted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $by, public int $postId, public string $type)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'reaction',
            'actor_id' => $this->by->id,
            'post_id' => $this->postId,
            'message' => "{$this->by->name} reacted to your post.",
            'url' => route('posts.show', $this->postId),
        ];
    }
}
