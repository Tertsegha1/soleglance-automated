<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    use HasFactory;

    protected $fillable = ['requester_id', 'addressee_id', 'status', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the canonical pair columns in sync so the unique index works.
        static::saving(function (Friendship $f) {
            $f->user_low_id = min($f->requester_id, $f->addressee_id);
            $f->user_high_id = max($f->requester_id, $f->addressee_id);
        });
    }

    public function scopeBetween(Builder $query, User $a, User $b): Builder
    {
        return $query->where('user_low_id', min($a->id, $b->id))
            ->where('user_high_id', max($a->id, $b->id));
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    public function otherUser(User $me): User
    {
        return $this->requester_id === $me->id ? $this->addressee : $this->requester;
    }
}
