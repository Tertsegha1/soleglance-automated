<?php

use App\Jobs\AnalysePendingArticles;
use App\Jobs\AnalysePost;
use App\Models\Article;
use App\Models\Post;
use App\Models\Trend;
use App\Services\Trends\TrendService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['analytics.url' => 'http://analytics.test', 'analytics.token' => 'secret']);
});

function fakeAnalytics(): void
{
    Http::fake([
        'analytics.test/v1/sentiment' => fn ($req) => Http::response([
            'model' => 'test-model',
            'results' => array_map(fn ($t) => [
                'label' => str_contains($t, 'bad') ? 'negative' : 'positive',
                'score' => 0.9,
                'scores' => ['positive' => 0.9, 'neutral' => 0.05, 'negative' => 0.05],
                'language' => 'en',
            ], $req['texts']),
        ]),
        'analytics.test/v1/topics' => fn ($req) => Http::response([
            'results' => array_map(fn () => ['category' => 'sports', 'confidence' => 0.8], $req['texts']),
        ]),
        'analytics.test/v1/health/analyse' => fn ($req) => Http::response(['models' => [], 'results' => array_map(fn () => [
            'is_health' => false, 'health_score' => 0.0, 'diseases' => [], 'symptoms' => [], 'regions' => [], 'countries' => [],
            'misinformation_score' => null, 'flag_misinformation' => false], $req['texts'])]),
        'analytics.test/v1/trends' => Http::response(['trends' => [[
            'term' => 'cricket', 'category' => 'sports', 'mentions' => 12, 'baseline' => 1.5, 'score' => 6.2,
            'sentiment' => ['positive' => 7, 'neutral' => 3, 'negative' => 2, 'mean' => 0.3],
            'document_ids' => ['post:1', 'article:1'],
        ]]]),
    ]);
}

test('posts are scored by the analytics service with the bearer token', function () {
    fakeAnalytics();
    $post = Post::factory()->create(['body' => 'a bad day']);

    app()->call([new AnalysePost($post), 'handle']);

    $post->refresh();
    expect($post->sentiment->value)->toBe('negative')
        ->and($post->sentiment_model)->toBe('test-model')
        ->and($post->category->value)->toBe('sports')
        ->and($post->analysed_at)->not->toBeNull();
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer secret'));
});

test('analysis is skipped when the service is not configured', function () {
    config(['analytics.url' => null]);
    Http::fake();
    $post = Post::factory()->create();

    app()->call([new AnalysePost($post), 'handle']);

    expect($post->fresh()->analysed_at)->toBeNull();
    Http::assertNothingSent();
});

test('a failing service raises so the queue retries', function () {
    Http::fake(['analytics.test/*' => Http::response('boom', 500)]);
    $post = Post::factory()->create();

    expect(fn () => app()->call([new AnalysePost($post), 'handle']))
        ->toThrow(\App\Services\Analytics\AnalyticsUnavailable::class);
});

test('pending articles are analysed in batches and keep their feed category', function () {
    fakeAnalytics();
    Article::factory()->count(3)->create(['category' => null]);
    Article::factory()->create(['category' => 'politics']);

    app()->call([new AnalysePendingArticles, 'handle']);

    expect(Article::whereNull('analysed_at')->count())->toBe(0)
        ->and(Article::where('category', 'politics')->count())->toBe(1)
        ->and(Article::where('category', 'sports')->count())->toBe(3);
});

test('trend refresh sends only public posts and stores the snapshot', function () {
    fakeAnalytics();
    Post::factory()->create(['body' => 'public cricket']);
    Post::factory()->visibility(\App\Enums\Visibility::Friends)->create(['body' => 'private cricket']);
    Article::factory()->create(['title' => 'Cricket news']);

    expect(app(TrendService::class)->refresh())->toBe(1);

    Http::assertSent(function ($r) {
        if (!str_ends_with($r->url(), '/v1/trends')) {
            return false;
        }
        $texts = collect($r['documents'])->pluck('text');

        return $texts->contains('public cricket') && !$texts->contains('private cricket');
    });
    expect(Trend::sole()->only('term', 'mentions', 'positive'))->toBe(['term' => 'cricket', 'mentions' => 12, 'positive' => 7]);

    $this->actingAs(\App\Models\User::factory()->create())->get('/trending')->assertSee('cricket');
});

test('sentiment analyser page shows the model result', function () {
    fakeAnalytics();
    $this->actingAs(\App\Models\User::factory()->create())
        ->post('/sentiment', ['text' => 'what a lovely day'])
        ->assertOk()->assertSee('Positive')->assertSee('test-model');
});
