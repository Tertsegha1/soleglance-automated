<?php

namespace App\Services\Analytics;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Python analytics service.
 *
 * Every method throws AnalyticsUnavailable when the service is not
 * configured or does not respond, so callers (queued jobs) can retry.
 */
class AnalyticsClient
{
    public function enabled(): bool
    {
        return filled(config('analytics.url'));
    }

    /**
     * @param  list<string>  $texts
     * @return list<array{label: string, score: float, scores: array<string, float>, language: string, model: string}>
     */
    public function sentiment(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = $this->post('/v1/sentiment', ['texts' => array_values($texts)]);

        return array_map(fn ($r) => $r + ['model' => $response['model']], $response['results']);
    }

    /**
     * @param  list<string>  $texts
     * @return list<array{category: string, confidence: float}>
     */
    public function topics(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->post('/v1/topics', ['texts' => array_values($texts)])['results'];
    }

    /**
     * @param  list<array{id: string, text: string, created_at: string, sentiment?: ?string, category?: ?string}>  $documents
     * @return list<array<string, mixed>>
     */
    public function trends(array $documents, \DateTimeInterface $now, array $options = []): array
    {
        return $this->post('/v1/trends', [
            'documents' => $documents,
            'now' => $now->format(DATE_ATOM),
            'window_hours' => $options['window_hours'] ?? config('analytics.trends.window_hours'),
            'history_windows' => $options['history_windows'] ?? config('analytics.trends.history_windows'),
            'min_mentions' => $options['min_mentions'] ?? config('analytics.trends.min_mentions'),
            'limit' => $options['limit'] ?? config('analytics.trends.limit'),
        ])['trends'];
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<string, mixed>>
     */
    public function healthAnalyse(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->post('/v1/health/analyse', ['texts' => array_values($texts)])['results'];
    }

    /**
     * @param  list<array{key: string, values: list<int|float>}>  $series
     * @return list<array<string, mixed>>
     */
    public function healthSignals(array $series, array $options = []): array
    {
        return $this->post('/v1/health/signals', ['series' => $series] + $options)['alerts'];
    }

    public function sendFeedback(array $items): int
    {
        return $this->post('/v1/feedback', ['items' => $items])['stored'];
    }

    public function retrain(): array
    {
        return $this->post('/v1/admin/retrain', []);
    }

    public function adminGet(string $path): ?array
    {
        if (!$this->enabled()) {
            return null;
        }
        try {
            return $this->request()->get($path)->throw()->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function health(): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            return $this->request()->get('/health')->throw()->json();
        } catch (\Throwable) {
            return null;
        }
    }

    private function post(string $path, array $payload): array
    {
        if (!$this->enabled()) {
            throw new AnalyticsUnavailable('Analytics service is not configured (ANALYTICS_URL).');
        }

        try {
            return $this->request()->post($path, $payload)->throw()->json();
        } catch (\Throwable $e) {
            throw new AnalyticsUnavailable("Analytics request to {$path} failed: {$e->getMessage()}", previous: $e);
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('analytics.url'), '/'))
            ->acceptJson()
            ->withToken((string) config('analytics.token'))
            ->timeout(config('analytics.timeout'))
            ->retry(2, 500, throw: true);
    }
}
