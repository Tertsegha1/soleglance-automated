<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the administrator account. Credentials come from the
     * environment so that no real password is ever committed.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@soleglance.test');
        $password = env('ADMIN_PASSWORD');

        if (!$password) {
            $password = Str::password(20);
            $this->command?->warn("ADMIN_PASSWORD not set; generated admin password: {$password}");
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => 'SoleGlance Admin',
            'username' => $admin->username ?? 'admin',
            'password' => $password,
        ]);
        $admin->forceFill(['role' => Role::Admin, 'email_verified_at' => now()])->save();
    }
}
