<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

// role and suspension fields are deliberately not fillable.
#[Fillable(['name', 'username', 'email', 'password', 'bio', 'country', 'city', 'date_of_birth'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'date_of_birth' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function canHandleHealth(): bool
    {
        return in_array($this->role, [Role::Admin, Role::HealthOfficer], true);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('suspended_at');
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        // Deterministic initials avatar; no third-party request.
        return route('avatar', $this->username);
    }

    public function profileUrl(): string
    {
        return route('users.show', $this->username);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot(['last_read_at', 'cleared_at']);
    }

    /**
     * Ids of users with an accepted friendship with this user.
     */
    public function friendIds(): Collection
    {
        return Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(fn ($q) => $q->where('requester_id', $this->id)->orWhere('addressee_id', $this->id))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn ($f) => $f->requester_id === $this->id ? $f->addressee_id : $f->requester_id)
            ->values();
    }

    public function friends(): Builder
    {
        return User::query()->whereIn('id', $this->friendIds());
    }

    public function friendshipWith(User $other): ?Friendship
    {
        return Friendship::between($this, $other)->first();
    }

    public function isFriendsWith(User $other): bool
    {
        return $this->friendshipWith($other)?->status === FriendshipStatus::Accepted;
    }
}
