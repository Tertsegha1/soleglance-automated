<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['direct_key', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public static function directKey(User $a, User $b): string
    {
        return min($a->id, $b->id) . ':' . max($a->id, $b->id);
    }

    /**
     * The single direct conversation between two users, created on first use.
     */
    public static function between(User $a, User $b): self
    {
        $conversation = static::firstOrCreate(['direct_key' => static::directKey($a, $b)]);
        $conversation->participants()->syncWithoutDetaching([$a->id, $b->id]);

        return $conversation;
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['last_read_at', 'cleared_at']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    public function otherParticipant(User $me): ?User
    {
        return $this->participants->firstWhere('id', '!=', $me->id);
    }

    /**
     * Messages visible to $user, honouring that participant clearing the chat.
     */
    public function messagesFor(User $user): HasMany
    {
        $clearedAt = $this->participants->firstWhere('id', $user->id)?->pivot?->cleared_at;

        return $this->messages()
            ->when($clearedAt, fn ($q) => $q->where('messages.created_at', '>', $clearedAt));
    }
}
