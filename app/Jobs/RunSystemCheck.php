<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SystemProblems;
use App\Services\Ops\SystemCheck;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class RunSystemCheck implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function handle(SystemCheck $check): void
    {
        $problems = $check->problems();
        Cache::put('system_check', ['at' => now()->toIso8601String(), 'problems' => $problems], now()->addDays(2));

        // Notify once per distinct set of problems per day, not on every run.
        $fingerprint = md5(implode('|', $problems));
        if ($problems && Cache::add("system_check_notified:{$fingerprint}", true, now()->addDay())) {
            Notification::send(User::where('role', Role::Admin)->whereNull('suspended_at')->get(), new SystemProblems($problems));
        }
    }
}
