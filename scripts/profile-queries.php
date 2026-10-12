<?php

// Run with: php artisan tinker scripts/profile-queries.php
// Times the expensive queries behind each page for user perf_1.

use App\Models\Post;
use App\Models\Trend;
use App\Models\User;
use App\Services\Search\TopicSearch;
use Illuminate\Support\Facades\DB;

$u = User::where('username', 'perf_1')->first();
$time = function (string $label, callable $fn) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t = microtime(true);
    $fn();
    $ms = (microtime(true) - $t) * 1000;
    $log = DB::getQueryLog();
    usort($log, fn ($a, $b) => $b['time'] <=> $a['time']);
    printf("%-28s %7.0f ms  %d queries; slowest %.0f ms: %s\n", $label, $ms, count($log), $log[0]['time'] ?? 0, substr($log[0]['query'] ?? '', 0, 140));
};

$time('feed paginate', fn () => Post::visibleTo($u)->with(['user', 'media'])->withCount('comments')->latest()->paginate(15));
$time('feed simplePaginate', fn () => Post::visibleTo($u)->with(['user', 'media'])->withCount('comments')->latest()->simplePaginate(15));
$time('friendIds', fn () => $u->friendIds());
$time('profile', fn () => Post::visibleTo($u)->where('posts.user_id', $u->id + 1)->latest()->paginate(15));
$time('search posts', fn () => app(TopicSearch::class)->posts('budget', $u)->latest()->limit(10)->get());
$time('timeline 14d', fn () => app(TopicSearch::class)->sentimentTimeline('budget', now()->subDays(13), now()));
$time('unread count', fn () => $u->unreadNotifications()->count());
$time('trend widget', fn () => Trend::where('window_end', Trend::max('window_end'))->orderByDesc('score')->limit(5)->get());
