<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile accepts username, bio, location and an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => 'New Name', 'username' => 'new_name', 'email' => $user->email,
        'bio' => 'Hello', 'country' => 'pk', 'city' => 'Lahore',
        'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->only('username', 'bio', 'country', 'city'))->toBe(['username' => 'new_name', 'bio' => 'Hello', 'country' => 'PK', 'city' => 'Lahore']);
    Storage::disk('public')->assertExists($user->avatar_path);
    $this->actingAs($user)->get('/@new_name')->assertOk()->assertSee('New Name');
});

test('usernames must be unique and well formed', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['name' => 'x', 'username' => 'taken', 'email' => $user->email])->assertSessionHasErrors('username');
    $this->actingAs($user)->patch('/profile', ['name' => 'x', 'username' => 'bad name!', 'email' => $user->email])->assertSessionHasErrors('username');
});

test('initials avatar is served as SVG and escapes the name', function () {
    User::factory()->create(['name' => '<script> Doe', 'username' => 'svgtest']);
    $this->get('/avatar/svgtest.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertDontSee('<script>', false);
});
