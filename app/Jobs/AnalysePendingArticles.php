<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Health\HealthRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scores news articles in batches of 64.
 */
class AnalysePendingArticles implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(AnalyticsClient $analytics, HealthRecorder $health): void
    {
        if (!$analytics->enabled()) {
            return;
        }

        Article::whereNull('analysed_at')->orderBy('id')->chunkById(64, function ($articles) use ($analytics, $health) {
            $texts = $articles->map->analysisText()->all();
            $sentiments = $analytics->sentiment($texts);
            $topics = $analytics->topics($texts);
            $healthResults = $analytics->healthAnalyse($texts);

            foreach ($articles->values() as $i => $article) {
                $article->forceFill([
                    'language' => $sentiments[$i]['language'],
                    'sentiment' => $sentiments[$i]['label'],
                    'sentiment_score' => $sentiments[$i]['score'],
                    'sentiment_scores' => $sentiments[$i]['scores'],
                    'sentiment_model' => $sentiments[$i]['model'],
                    // A feed's own category wins over the classifier.
                    'category' => $article->category ?? $topics[$i]['category'],
                    'analysed_at' => now(),
                ])->save();
                $health->record($article, $healthResults[$i]);
            }
        });
    }
}
