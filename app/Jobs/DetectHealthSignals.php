<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsClient;
use App\Services\Health\SignalDetector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectHealthSignals implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 600];

    public function handle(SignalDetector $detector, AnalyticsClient $analytics): void
    {
        if ($analytics->enabled()) {
            $detector->run();
        }
    }
}
