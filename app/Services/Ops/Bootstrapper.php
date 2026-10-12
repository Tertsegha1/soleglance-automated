<?php

namespace App\Services\Ops;

use App\Enums\Category;
use App\Enums\Role;
use App\Models\NewsSource;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * First-start automation: wait for the database, migrate, create the first
 * administrator and default news sources. Safe to run on every start.
 */
class Bootstrapper
{
    /** Public-sector / humanitarian feeds whose terms allow automated reading. */
    public const DEFAULT_SOURCES = [
        ['WHO news', 'https://www.who.int/rss-feeds/news-english.xml', 'health', null],
        ['ReliefWeb: Nigeria health updates', 'https://reliefweb.int/updates/rss.xml?advanced-search=%28PC175%29_%28T4595%29', 'health', 'NG'],
        ['ReliefWeb: Pakistan health updates', 'https://reliefweb.int/updates/rss.xml?advanced-search=%28PC182%29_%28T4595%29', 'health', 'PK'],
        ['ReliefWeb: epidemics', 'https://reliefweb.int/disasters/rss.xml?search=type.exact:%22Epidemic%22', 'health', null],
    ];

    /** @return list<string> log lines */
    public function run(int $dbAttempts = 30): array
    {
        $log = [];
        $this->waitForDatabase($dbAttempts);
        Artisan::call('migrate', ['--force' => true]);
        $log[] = trim(Artisan::output()) ?: 'migrations up to date';

        if (!User::where('role', Role::Admin)->exists()) {
            $email = config('soleglance.admin_email');
            $password = config('soleglance.admin_password') ?: Str::password(20);
            $admin = new User(['name' => 'SoleGlance Admin', 'username' => 'admin', 'email' => $email, 'password' => $password]);
            $admin->forceFill(['role' => Role::Admin, 'email_verified_at' => now()])->save();
            $log[] = "created administrator {$email}" . (config('soleglance.admin_password') ? '' : " with generated password {$password} (change it after first login)");
        }

        if (config('soleglance.seed_default_news_sources') && NewsSource::count() === 0) {
            foreach (self::DEFAULT_SOURCES as [$name, $url, $category, $country]) {
                NewsSource::create(['name' => $name, 'feed_url' => $url, 'category' => Category::from($category),
                    'country' => $country, 'language' => 'en', 'active' => true]);
            }
            $log[] = 'added ' . count(self::DEFAULT_SOURCES) . ' default news sources';
        }

        return $log;
    }

    private function waitForDatabase(int $attempts): void
    {
        for ($i = 1; ; $i++) {
            try {
                DB::connection()->getPdo();

                return;
            } catch (\Throwable $e) {
                if ($i >= $attempts) {
                    throw $e;
                }
                sleep(2);
            }
        }
    }
}
