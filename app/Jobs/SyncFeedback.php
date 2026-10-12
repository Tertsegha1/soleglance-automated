<?php

namespace App\Jobs;

use App\Models\LabelFeedback;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Sends label corrections to the analytics service and triggers gated
 * retraining once enough new corrections have accumulated. Only text from
 * public posts and news articles is ever sent.
 */
class SyncFeedback implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function handle(AnalyticsClient $analytics): void
    {
        if (!$analytics->enabled()) {
            return;
        }

        $sent = 0;
        LabelFeedback::with('subject')->whereNull('sent_at')->orderBy('id')->chunkById(200, function ($batch) use ($analytics, &$sent) {
            $items = [];
            foreach ($batch as $fb) {
                $text = $fb->shareableText();
                if ($text !== null) {
                    $items[] = ['task' => $fb->task, 'text' => $text, 'label' => $fb->corrected, 'source' => 'platform'];
                }
            }
            if ($items) {
                $analytics->sendFeedback($items);
                $sent += count($items);
            }
            // Non-shareable corrections are marked too, so they are not retried forever.
            LabelFeedback::whereKey($batch->modelKeys())->update(['sent_at' => now()]);
        });

        $pending = Cache::increment('feedback_since_retrain', $sent);
        if ($pending >= config('health.retrain_after_feedback')) {
            $analytics->retrain();
            Cache::put('feedback_since_retrain', 0);
        }
    }
}
