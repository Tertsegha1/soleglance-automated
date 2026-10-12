<?php

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Support\Facades\Notification;

test('full request, accept and unfriend cycle', function () {
    Notification::fake();
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->post(route('friends.store', $b))->assertRedirect();
    Notification::assertSentTo($b, FriendRequestReceived::class);
    expect($a->isFriendsWith($b))->toBeFalse();

    $this->actingAs($b)->post(route('friends.accept', $a))->assertRedirect();
    Notification::assertSentTo($a, FriendRequestAccepted::class);
    expect($a->fresh()->isFriendsWith($b))->toBeTrue()
        ->and($a->friendIds()->all())->toBe([$b->id]);

    $this->actingAs($a)->delete(route('friends.destroy', $b));
    expect(Friendship::count())->toBe(0);
});

test('a friendship cannot be accepted without a pending request from that user', function () {
    [$me, $stranger] = User::factory()->count(2)->create();

    $this->actingAs($me)->post(route('friends.accept', $stranger))->assertNotFound();
    expect(Friendship::count())->toBe(0);

    // Nor can the requester accept their own request.
    Friendship::create(['requester_id' => $me->id, 'addressee_id' => $stranger->id, 'status' => FriendshipStatus::Pending]);
    $this->actingAs($me)->post(route('friends.accept', $stranger))->assertNotFound();
    expect($me->isFriendsWith($stranger))->toBeFalse();
});

test('a mirrored request accepts the existing one instead of duplicating', function () {
    Notification::fake();
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->post(route('friends.store', $b));
    $this->actingAs($b)->post(route('friends.store', $a));

    expect(Friendship::count())->toBe(1)->and($a->isFriendsWith($b))->toBeTrue();
});

test('declining removes the request; addressees cannot use cancel to decline silently', function () {
    [$a, $b] = User::factory()->count(2)->create();
    Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => FriendshipStatus::Pending]);

    $this->actingAs($b)->delete(route('friends.destroy', $a))->assertNotFound();
    $this->actingAs($b)->post(route('friends.decline', $a))->assertRedirect();
    expect(Friendship::count())->toBe(0);
});

test('you cannot befriend yourself or a suspended user', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('friends.store', $me))->assertStatus(422);
    $this->actingAs($me)->post(route('friends.store', User::factory()->suspended()->create()))->assertStatus(422);
});
