<?php

use App\Jobs\AnalysePendingArticles;
use App\Jobs\DetectHealthSignals;
use App\Jobs\SyncFeedback;
use App\Jobs\FetchNewsSource;
use App\Jobs\RefreshTrends;
use App\Models\NewsSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('news:fetch', function () {
    $sources = NewsSource::where('active', true)->get();
    $sources->each(fn ($s) => FetchNewsSource::dispatchSync($s));
    AnalysePendingArticles::dispatch();
    $this->info("Fetched {$sources->count()} sources.");
})->purpose('Fetch all active RSS/Atom news sources and queue sentiment analysis');

Artisan::command('trends:refresh', function () {
    RefreshTrends::dispatchSync();
    $this->info('Trends refreshed.');
})->purpose('Recompute trending topics now');

Schedule::command('news:fetch')->everyThirtyMinutes()->withoutOverlapping();
Schedule::job(new RefreshTrends)->hourly();
Schedule::job(new DetectHealthSignals)->dailyAt('06:00');
Schedule::job(new SyncFeedback)->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();

Artisan::command('health:signals', function () {
    $alerts = app(\App\Services\Health\SignalDetector::class)->run();
    $this->info(count($alerts) . ' new alert(s).');
})->purpose('Run outbreak signal detection now');
