<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\HealthAlert;
use App\Models\HealthMention;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public health dashboard. Shows aggregates of public posts and news only.
 */
class HealthController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'country' => ['nullable', Rule::in(array_keys(config('health.countries')))],
            'disease' => ['nullable', 'string', 'max:40'],
            'days' => ['nullable', 'integer', 'in:14,30,90'],
        ]);
        $days = (int) ($data['days'] ?? 30);
        $since = now()->subDays($days - 1)->startOfDay();

        $base = HealthMention::query()->where('is_public', true)->where('occurred_at', '>=', $since)
            ->when($data['country'] ?? null, fn ($q, $c) => $q->where('country', $c))
            ->when($data['disease'] ?? null, fn ($q, $d) => $q->where('disease', $d));

        $byDisease = (clone $base)->whereNotNull('disease')
            ->selectRaw('disease, count(*) as n')->groupBy('disease')->orderByDesc('n')->limit(12)->pluck('n', 'disease');

        $daily = (clone $base)->selectRaw('date(occurred_at) as day, count(*) as n')->groupBy('day')->pluck('n', 'day');
        $dayLabels = collect(range(0, $days - 1))->map(fn ($i) => $since->copy()->addDays($i)->toDateString());

        $byRegion = (clone $base)->whereNotNull('region')
            ->selectRaw('region, count(*) as n')->groupBy('region')->orderByDesc('n')->limit(10)->pluck('n', 'region');

        $recent = (clone $base)->with('mentionable')->latest('occurred_at')->limit(30)->get()
            ->filter(fn ($m) => $m->mentionable && (!$m->mentionable instanceof Post || $m->mentionable->hidden_at === null));

        return view('health.index', [
            'filters' => array_merge($data, ['days' => $days]),
            'byDisease' => $byDisease,
            'daily' => $dayLabels->map(fn ($d) => (int) ($daily[$d] ?? 0)),
            'dayLabels' => $dayLabels,
            'byRegion' => $byRegion,
            'recent' => $recent,
            'alerts' => HealthAlert::whereIn('status', ['open', 'acknowledged'])
                ->when($data['country'] ?? null, fn ($q, $c) => $q->where('country', $c))
                ->latest('window_end')->limit(20)->get(),
            'totals' => [
                'mentions' => (clone $base)->count(),
                'posts' => (clone $base)->where('mentionable_type', (new Post)->getMorphClass())->count(),
                'articles' => (clone $base)->where('mentionable_type', (new Article)->getMorphClass())->count(),
            ],
        ]);
    }
}
