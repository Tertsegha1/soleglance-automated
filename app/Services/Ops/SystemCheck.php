<?php

namespace App\Services\Ops;

use App\Models\Article;
use App\Models\NewsSource;
use App\Models\Post;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Support\Facades\DB;

/**
 * Daily operational checks. Returns a list of problems (empty when healthy).
 */
class SystemCheck
{
    public function __construct(private AnalyticsClient $analytics)
    {
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];

        if ($this->analytics->enabled()) {
            if (!$this->analytics->health()) {
                $problems[] = 'The analytics service is unreachable; new posts are not being analysed.';
            } else {
                $metrics = $this->analytics->adminGet('/v1/admin/metrics');
                if ($metrics['monitor']['drift_alert'] ?? false) {
                    $psi = collect($metrics['monitor']['psi'] ?? [])->map(fn ($v, $k) => "{$k} " . number_format($v, 2))->implode(', ');
                    $problems[] = "Model drift detected (PSI {$psi}). Review recent corrections and consider retraining.";
                }
                $last = $metrics['retrain']['last'] ?? null;
                if (is_array($last) && isset($last['error'])) {
                    $problems[] = "The last automatic retraining failed: {$last['error']}";
                }
            }
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($failed > 0) {
            $problems[] = "{$failed} background job(s) failed in the last 24 hours (see failed_jobs / php artisan queue:failed).";
        }

        $backlog = Post::whereNull('analysed_at')->where('created_at', '<', now()->subHour())->count()
            + Article::whereNull('analysed_at')->where('created_at', '<', now()->subHour())->count();
        if ($backlog > (int) config('health.backlog_alert', 500)) {
            $problems[] = "{$backlog} posts/articles have waited more than an hour for analysis.";
        }

        foreach (NewsSource::where('active', true)->whereNotNull('last_error')->get(['name', 'last_error']) as $s) {
            $problems[] = "News source \"{$s->name}\" is failing: {$s->last_error}";
        }

        return $problems;
    }
}
