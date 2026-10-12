<?php

use App\Models\Article;
use App\Models\Post;
use App\Models\User;

test('people search matches names and usernames but never emails', function () {
    $me = User::factory()->create();
    User::factory()->create(['name' => 'Zara Malik', 'username' => 'zaram', 'email' => 'hidden.person@example.com']);

    $this->actingAs($me)->get('/search?q=zara&type=people')->assertSee('Zara Malik')->assertDontSee('hidden.person@example.com');
    $this->actingAs($me)->get('/search?q=hidden.person&type=people')->assertDontSee('Zara Malik');
});

test('date and region filters apply to posts and news', function () {
    $me = User::factory()->create();
    Post::factory()->create(['body' => 'budget talk PK', 'country' => 'PK', 'created_at' => now()->subDays(2)]);
    Post::factory()->create(['body' => 'budget talk GB', 'country' => 'GB', 'created_at' => now()->subDays(2)]);
    Post::factory()->create(['body' => 'budget talk old', 'country' => 'PK', 'created_at' => now()->subDays(40)]);
    Article::factory()->create(['title' => 'Budget news PK', 'country' => 'PK', 'published_at' => now()->subDay()]);

    $this->actingAs($me)->get('/search?' . http_build_query(['q' => 'budget', 'country' => 'pk', 'from' => now()->subDays(7)->toDateString()]))
        ->assertSee('budget talk PK')->assertDontSee('budget talk GB')->assertDontSee('budget talk old')
        ->assertSee('Budget news PK');
});

test('LIKE wildcards in queries are treated literally', function () {
    $me = User::factory()->create();
    Post::factory()->create(['body' => 'anything at all']);
    $this->actingAs($me)->get('/search?q=%25&type=posts')->assertDontSee('anything at all');
});

test('topic page shows the sentiment timeline for analysed public content', function () {
    $me = User::factory()->create();
    Post::factory()->create(['body' => 'budget is great'])->forceFill(['sentiment' => 'positive', 'sentiment_score' => 0.9])->save();
    Article::factory()->create(['title' => 'Budget criticised', 'published_at' => now()->subDay()])
        ->forceFill(['sentiment' => 'negative', 'sentiment_score' => 0.8])->save();

    $this->actingAs($me)->get('/topic?q=budget')
        ->assertOk()->assertSee('data-chart="stacked"', false)->assertSee('budget is great')->assertSee('Budget criticised');
});

test('every main page renders for a signed-in user', function (string $path) {
    $this->actingAs(User::factory()->create(['username' => 'viewer']))->get($path)->assertOk();
})->with(['/feed', '/friends', '/messages', '/notifications', '/search?q=x', '/trending', '/topic?q=x', '/sentiment', '/reports', '/profile', '/@viewer']);
