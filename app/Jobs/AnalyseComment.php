<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyseComment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Comment $comment)
    {
    }

    public function handle(AnalyticsClient $analytics): void
    {
        if (!$analytics->enabled()) {
            return;
        }

        $result = $analytics->sentiment([$this->comment->body])[0];

        $this->comment->forceFill([
            'sentiment' => $result['label'],
            'sentiment_score' => $result['score'],
            'analysed_at' => now(),
        ])->saveQuietly();
    }
}
