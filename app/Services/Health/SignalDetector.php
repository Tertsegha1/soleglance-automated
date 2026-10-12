<?php

namespace App\Services\Health;

use App\Enums\Role;
use App\Models\HealthAlert;
use App\Models\HealthMention;
use App\Models\User;
use App\Notifications\HealthAlertRaised;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Builds daily mention counts per (disease, country) and (disease, region)
 * from public content, asks the analytics service for EARS aberrations, and
 * turns new ones into alerts for health officers.
 */
class SignalDetector
{
    public function __construct(private AnalyticsClient $analytics)
    {
    }

    /** @return list<HealthAlert> newly created alerts */
    public function run(?Carbon $today = null): array
    {
        $cfg = config('health.signals');
        $today = ($today ?? now())->copy()->startOfDay();
        $start = $today->copy()->subDays($cfg['lookback_days'] - 1);
        $days = collect(range(0, $cfg['lookback_days'] - 1))->map(fn ($i) => $start->copy()->addDays($i)->toDateString());

        $rows = HealthMention::query()
            ->where('is_public', true)
            ->whereIn('disease', config('health.priority_diseases'))
            ->whereNotNull('country')
            ->whereBetween('occurred_at', [$start, $today->copy()->endOfDay()])
            ->get(['disease', 'country', 'region', 'occurred_at']);

        $series = [];
        foreach ($rows as $row) {
            $day = $row->occurred_at->toDateString();
            foreach (array_filter(["{$row->disease}|{$row->country}", $row->region ? "{$row->disease}|{$row->country}|{$row->region}" : null]) as $key) {
                $series[$key][$day] = ($series[$key][$day] ?? 0) + 1;
            }
        }
        if ($series === []) {
            return [];
        }

        $payload = collect($series)->map(fn ($counts, $key) => [
            'key' => $key,
            'values' => $days->map(fn ($d) => $counts[$d] ?? 0)->all(),
        ])->values()->all();

        $response = $this->analytics->healthSignals($payload, [
            'method' => $cfg['method'],
            'baseline' => $cfg['baseline_days'],
            'min_count' => $cfg['min_count'],
            'recent' => 1,
        ]);

        $created = [];
        foreach ($response as $a) {
            [$disease, $country, $region] = array_pad(explode('|', $a['key']), 3, null);
            $recent = HealthAlert::where('disease', $disease)->where('country', $country)->where('region', $region)
                ->where('window_end', '>=', $today->copy()->subDays($cfg['cooldown_days']))
                ->whereIn('status', ['open', 'acknowledged'])->exists();
            if ($recent) {
                continue;
            }
            $values = collect($payload)->firstWhere('key', $a['key'])['values'];
            $baseline = array_slice($values, max(0, $a['start'] - $cfg['baseline_days'] - 2), $cfg['baseline_days']);
            $alert = HealthAlert::create([
                'disease' => $disease,
                'country' => $country,
                'region' => $region,
                'method' => $cfg['method'],
                'statistic' => $a['statistic'],
                'count' => (int) $a['count'],
                'baseline_mean' => $baseline ? array_sum($baseline) / count($baseline) : null,
                'window_start' => $days[$a['start']],
                'window_end' => $days[$a['end']],
                'status' => 'open',
            ]);
            $this->notify($alert);
            $created[] = $alert;
        }

        return $created;
    }

    private function notify(HealthAlert $alert): void
    {
        $recipients = User::whereIn('role', [Role::Admin, Role::HealthOfficer])->whereNull('suspended_at')->get();
        Notification::send($recipients, new HealthAlertRaised($alert));
        $alert->forceFill(['notified_at' => now()])->save();

        if ($url = config('health.alert_webhook')) {
            $body = json_encode($alert->payload());
            $signature = hash_hmac('sha256', $body, (string) config('health.webhook_secret'));
            try {
                Http::timeout(10)->withHeaders(['X-SoleGlance-Signature' => $signature])
                    ->withBody($body, 'application/json')->post($url)->throw();
                $alert->forceFill(['webhook_sent_at' => now()])->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
