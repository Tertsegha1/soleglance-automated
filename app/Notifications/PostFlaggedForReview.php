<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostFlaggedForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $postId, public float $score)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'misinformation_review',
            'post_id' => $this->postId,
            'message' => 'A public post was flagged as possible health misinformation (score ' . number_format($this->score, 2) . ').',
            'url' => route('admin.misinformation.index'),
        ];
    }
}
