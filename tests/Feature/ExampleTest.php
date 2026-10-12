<?php

use App\Models\User;

test('guests see the landing page', function () {
    $this->get('/')->assertOk()->assertSee('SoleGlance');
});

test('signed-in users are sent to their feed', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('feed'));
});

test('unverified users cannot reach the app', function () {
    $this->actingAs(User::factory()->unverified()->create())->get('/feed')->assertRedirect(route('verification.notice'));
});
