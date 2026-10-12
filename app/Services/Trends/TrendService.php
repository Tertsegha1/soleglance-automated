<?php

namespace App\Services\Trends;

use App\Models\Article;
use App\Models\Post;
use App\Models\Trend;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the document stream (public posts + news articles), asks the
 * analytics service for bursting terms, and stores a snapshot.
 */
class TrendService
{
    public function __construct(private AnalyticsClient $analytics)
    {
    }

    /** @return int number of trends stored */
    public function refresh(?Carbon $now = null): int
    {
        $now ??= now();
        $cfg = config('analytics.trends');
        $since = $now->copy()->subHours($cfg['window_hours'] * ($cfg['history_windows'] + 1));

        $documents = $this->documentsSince($since, $cfg['max_documents']);
        $trends = $this->analytics->trends($documents, $now);

        $windowStart = $now->copy()->subHours($cfg['window_hours']);

        DB::transaction(function () use ($trends, $windowStart, $now) {
            // Keep history for charts, but replace any snapshot for the same window end.
            Trend::where('window_end', $now)->delete();

            foreach ($trends as $t) {
                Trend::create([
                    'term' => mb_substr($t['term'], 0, 100),
                    'category' => $t['category'] ?? null,
                    'window_start' => $windowStart,
                    'window_end' => $now,
                    'mentions' => $t['mentions'],
                    'baseline' => $t['baseline'],
                    'score' => $t['score'],
                    'positive' => $t['sentiment']['positive'] ?? 0,
                    'neutral' => $t['sentiment']['neutral'] ?? 0,
                    'negative' => $t['sentiment']['negative'] ?? 0,
                    'mean_sentiment' => $t['sentiment']['mean'] ?? null,
                    'sample' => array_slice($t['document_ids'] ?? [], 0, 50),
                ]);
            }
        });

        return count($trends);
    }

    /**
     * @return list<array{id: string, text: string, created_at: string, sentiment: ?string, sentiment_score: ?float, category: ?string}>
     */
    public function documentsSince(Carbon $since, int $limit): array
    {
        $posts = Post::query()->publiclyAnalysable()
            ->where('created_at', '>=', $since)
            ->latest()->limit($limit)
            ->get(['id', 'body', 'created_at', 'sentiment', 'sentiment_score', 'category'])
            ->map(fn (Post $p) => [
                'id' => 'post:' . $p->id,
                'text' => $p->body,
                'created_at' => $p->created_at->toAtomString(),
                'sentiment' => $p->sentiment?->value,
                'sentiment_score' => $p->sentiment_score,
                'category' => $p->category?->value,
            ]);

        $articles = Article::query()
            ->where('published_at', '>=', $since)
            ->latest('published_at')->limit($limit)
            ->get(['id', 'title', 'summary', 'published_at', 'sentiment', 'sentiment_score', 'category'])
            ->map(fn (Article $a) => [
                'id' => 'article:' . $a->id,
                'text' => $a->analysisText(),
                'created_at' => $a->published_at->toAtomString(),
                'sentiment' => $a->sentiment?->value,
                'sentiment_score' => $a->sentiment_score,
                'category' => $a->category?->value,
            ]);

        return $posts->concat($articles)->values()->all();
    }
}
