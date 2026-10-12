<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsClient $analytics): View
    {
        $since = now()->subDays(30);

        return view('admin.dashboard', [
            'stats' => [
                'Users' => User::count(),
                'Suspended' => User::whereNotNull('suspended_at')->count(),
                'Posts (30d)' => Post::where('created_at', '>=', $since)->count(),
                'Messages (30d)' => Message::where('created_at', '>=', $since)->count(),
                'Articles (30d)' => Article::where('created_at', '>=', $since)->count(),
                'Pending analysis' => Post::whereNull('analysed_at')->count() + Article::whereNull('analysed_at')->count(),
            ],
            'signups' => User::where('created_at', '>=', $since)
                ->selectRaw('date(created_at) as d, count(*) as n')->groupBy('d')->orderBy('d')->pluck('n', 'd'),
            'recentActions' => ModerationAction::with(['admin:id,name', 'targetUser:id,name,username'])->latest()->limit(10)->get(),
            'analyticsHealth' => $analytics->health(),
            'analyticsEnabled' => $analytics->enabled(),
            'systemCheck' => \Illuminate\Support\Facades\Cache::get('system_check'),
        ]);
    }
}
