<?php

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function befriend(User $a, User $b): Friendship
{
    return Friendship::create([
        'requester_id' => $a->id,
        'addressee_id' => $b->id,
        'status' => FriendshipStatus::Accepted,
        'accepted_at' => now(),
    ]);
}
