<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsClient;
use App\Services\Trends\TrendService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshTrends implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function handle(TrendService $trends, AnalyticsClient $analytics): void
    {
        if ($analytics->enabled()) {
            $trends->refresh();
        }
    }
}
