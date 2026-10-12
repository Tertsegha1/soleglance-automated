<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(Str::random(3)) . '_' . fake()->unique()->numberBetween(1000, 9999999),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::User,
            'country' => fake()->randomElement(['PK', 'GB', 'US', 'NG', 'IN']),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function suspended(?string $reason = null): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspension_reason' => $reason]);
    }
}
