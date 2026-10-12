<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Health\HealthRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalysePost implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Post $post)
    {
    }

    public function handle(AnalyticsClient $analytics, HealthRecorder $health): void
    {
        if (!$analytics->enabled()) {
            return;
        }

        $result = $analytics->sentiment([$this->post->body])[0];
        $topic = $analytics->topics([$this->post->body])[0];

        $this->post->forceFill([
            'language' => $result['language'],
            'sentiment' => $result['label'],
            'sentiment_score' => $result['score'],
            'sentiment_scores' => $result['scores'],
            'sentiment_model' => $result['model'],
            'category' => $topic['category'],
            'analysed_at' => now(),
        ])->saveQuietly();

        $health->record($this->post, $analytics->healthAnalyse([$this->post->body])[0]);
    }
}
