<?php

use App\Jobs\AnalysePendingArticles;
use App\Jobs\DetectHealthSignals;
use App\Jobs\RunSystemCheck;
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
Schedule::job(new RunSystemCheck)->everySixHours();

Artisan::command('app:bootstrap', function () {
    foreach (app(\App\Services\Ops\Bootstrapper::class)->run() as $line) {
        $this->info($line);
    }
})->purpose('First-start automation: wait for the database, migrate, create the first admin and default news sources');

Artisan::command('app:check', function () {
    $problems = app(\App\Services\Ops\SystemCheck::class)->problems();
    $problems ? array_map(fn ($p) => $this->warn($p), $problems) : $this->info('All checks passed.');

    return $problems ? 1 : 0;
})->purpose('Run the operational checks now (analytics, drift, failed jobs, backlog, news sources)');
Schedule::command('queue:prune-failed --hours=168')->daily();

Artisan::command('health:signals', function () {
    $alerts = app(\App\Services\Health\SignalDetector::class)->run();
    $this->info(count($alerts) . ' new alert(s).');
})->purpose('Run outbreak signal detection now');
