<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Short, stable type names in polymorphic columns (health mentions, feedback).
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'post' => \App\Models\Post::class,
            'article' => \App\Models\Article::class,
        ]);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('health', fn (User $user) => $user->canHandleHealth());

        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(20)->by($request->user()?->id ?: $request->ip()));
    }
}
