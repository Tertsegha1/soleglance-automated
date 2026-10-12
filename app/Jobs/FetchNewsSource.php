<?php

namespace App\Jobs;

use App\Models\NewsSource;
use App\Services\News\FeedParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class FetchNewsSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public NewsSource $source)
    {
    }

    public function handle(FeedParser $parser): void
    {
        try {
            $response = Http::timeout(config('analytics.news.fetch_timeout'))
                ->withUserAgent('SoleGlance/2.0 (+https://github.com/Tertsegha1/soleglance-automated)')
                ->get($this->source->feed_url)
                ->throw();

            $items = $parser->parse($response->body(), config('analytics.news.max_items_per_feed'));
        } catch (\Throwable $e) {
            $this->source->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500), 'last_fetched_at' => now()])->save();

            return;
        }

        foreach ($items as $item) {
            $this->source->articles()->firstOrCreate(
                ['guid' => mb_substr($item['guid'], 0, 191)],
                [
                    'title' => mb_substr($item['title'], 0, 500),
                    'summary' => $item['summary'] ? mb_substr($item['summary'], 0, 5000) : null,
                    'url' => mb_substr($item['url'], 0, 2048),
                    'published_at' => $item['published_at'] ?? now(),
                    'country' => $this->source->country,
                    'language' => $this->source->language,
                    'category' => $this->source->category,
                ],
            );
        }

        $this->source->forceFill(['last_error' => null, 'last_fetched_at' => now()])->save();
    }
}
