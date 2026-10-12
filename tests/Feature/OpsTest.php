<?php

use App\Enums\Role;
use App\Jobs\RunSystemCheck;
use App\Models\NewsSource;
use App\Models\User;
use App\Notifications\SystemProblems;
use App\Services\Ops\Bootstrapper;
use App\Services\Ops\SystemCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('bootstrap creates the first admin and default news sources once', function () {
    config(['soleglance.admin_email' => 'boss@soleglance.test', 'soleglance.admin_password' => 'a-strong-test-password']);

    app(Bootstrapper::class)->run();
    app(Bootstrapper::class)->run(); // second start: nothing duplicated

    expect(User::where('role', Role::Admin)->pluck('email')->all())->toBe(['boss@soleglance.test'])
        ->and(NewsSource::count())->toBe(count(Bootstrapper::DEFAULT_SOURCES));
});

test('bootstrap leaves existing admins and sources alone', function () {
    User::factory()->admin()->create(['email' => 'existing@soleglance.test']);
    NewsSource::factory()->create();

    app(Bootstrapper::class)->run();

    expect(User::where('role', Role::Admin)->count())->toBe(1)->and(NewsSource::count())->toBe(1);
});

test('system check reports drift, failed jobs and broken feeds, and notifies admins once', function () {
    Notification::fake();
    config(['analytics.url' => 'http://analytics.test']);
    Http::fake([
        'analytics.test/health' => Http::response(['status' => 'ok']),
        'analytics.test/v1/admin/metrics' => Http::response(['monitor' => ['drift_alert' => true, 'psi' => ['languages' => 0.41]],
            'retrain' => ['last' => null]]),
    ]);
    $admin = User::factory()->admin()->create();
    \Illuminate\Support\Facades\DB::table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'redis', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);
    NewsSource::factory()->create(['name' => 'Broken feed'])->forceFill(['last_error' => 'HTTP 500'])->save();

    $problems = app(SystemCheck::class)->problems();
    expect(implode(' ', $problems))->toContain('drift')->toContain('1 background job')->toContain('Broken feed');

    app()->call([new RunSystemCheck, 'handle']);
    app()->call([new RunSystemCheck, 'handle']);
    Notification::assertSentToTimes($admin, SystemProblems::class, 1);
});

test('system check is quiet when everything is healthy', function () {
    config(['analytics.url' => 'http://analytics.test']);
    Http::fake([
        'analytics.test/health' => Http::response(['status' => 'ok']),
        'analytics.test/v1/admin/metrics' => Http::response(['monitor' => ['observed' => 10], 'retrain' => ['last' => null]]),
    ]);
    expect(app(SystemCheck::class)->problems())->toBe([]);
});

test('system check flags an unreachable analytics service', function () {
    config(['analytics.url' => 'http://analytics.test']);
    Http::fake(['analytics.test/*' => Http::response('down', 503)]);
    expect(app(SystemCheck::class)->problems()[0])->toContain('unreachable');
});
