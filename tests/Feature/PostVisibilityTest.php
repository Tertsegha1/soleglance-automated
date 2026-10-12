<?php

use App\Enums\Visibility;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->author = User::factory()->create(['username' => 'author']);
    $this->friend = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->chosen = User::factory()->create();
    befriend($this->author, $this->friend);
    befriend($this->author, $this->chosen);

    $this->posts = collect(Visibility::cases())->mapWithKeys(fn ($v) => [
        $v->value => Post::factory()->for($this->author)->visibility($v)->create(['body' => "secret-{$v->value}-post"]),
    ]);
    $this->posts['custom']->audience()->attach($this->chosen);
});

dataset('matrix', [
    // viewer,      public, friends, only_me, custom
    ['author',     true,   true,    true,    true],
    ['friend',     true,   true,    false,   false],
    ['chosen',     true,   true,    false,   true],
    ['stranger',   true,   false,   false,   false],
]);

test('feed, profile and post pages respect visibility', function (string $viewer, bool ...$expected) {
    $user = $this->{$viewer};
    $visible = array_combine(['public', 'friends', 'only_me', 'custom'], $expected);

    $feed = $this->actingAs($user)->get('/feed')->assertOk();
    $profile = $this->actingAs($user)->get('/@author')->assertOk();

    foreach ($visible as $type => $canSee) {
        $text = "secret-{$type}-post";
        $canSee ? $feed->assertSee($text) : $feed->assertDontSee($text);
        $canSee ? $profile->assertSee($text) : $profile->assertDontSee($text);
        $this->actingAs($user)->get(route('posts.show', $this->posts[$type]))->assertStatus($canSee ? 200 : 403);
    }
})->with('matrix');

test('search respects visibility', function () {
    $this->actingAs($this->stranger)->get('/search?q=secret')
        ->assertSee('secret-public-post')->assertDontSee('secret-friends-post')
        ->assertDontSee('secret-only_me-post')->assertDontSee('secret-custom-post');
});

test('hidden posts and posts by suspended users disappear', function () {
    $this->posts['public']->forceFill(['hidden_at' => now()])->save();
    $this->actingAs($this->stranger)->get('/feed')->assertDontSee('secret-public-post');

    $other = User::factory()->suspended()->create();
    Post::factory()->for($other)->create(['body' => 'from-a-suspended-user']);
    $this->actingAs($this->stranger)->get('/feed')->assertDontSee('from-a-suspended-user');
});

test('guests can view nothing behind login', function () {
    $this->get(route('posts.show', $this->posts['public']))->assertRedirect('/login');
});

test('unfriending revokes access to friends-only posts', function () {
    $this->actingAs($this->friend)->get(route('posts.show', $this->posts['friends']))->assertOk();
    $this->actingAs($this->author)->delete(route('friends.destroy', $this->friend));
    $this->actingAs($this->friend)->get(route('posts.show', $this->posts['friends']))->assertForbidden();
});
