<?php

use App\Enums\Role;
use App\Enums\Visibility;
use App\Jobs\AnalysePost;
use App\Jobs\SyncFeedback;
use App\Models\Article;
use App\Models\HealthAlert;
use App\Models\HealthMention;
use App\Models\LabelFeedback;
use App\Models\MisinformationReview;
use App\Models\Post;
use App\Models\User;
use App\Notifications\HealthAlertRaised;
use App\Notifications\PostFlaggedForReview;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Health\HealthRecorder;
use App\Services\Health\SignalDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['analytics.url' => 'http://analytics.test', 'analytics.token' => 'secret']);
});

function healthResult(array $overrides = []): array
{
    return array_merge([
        'is_health' => true, 'health_score' => 0.9, 'diseases' => ['lassa_fever'], 'symptoms' => ['fever'],
        'regions' => ['NG-ON'], 'countries' => ['NG'], 'misinformation_score' => 0.1, 'flag_misinformation' => false,
    ], $overrides);
}

function fakeHealthAnalytics(array $result): void
{
    Http::fake([
        'analytics.test/v1/sentiment' => fn ($r) => Http::response(['model' => 'm', 'results' => array_map(fn () => [
            'label' => 'negative', 'score' => 0.8, 'scores' => ['positive' => 0.1, 'neutral' => 0.1, 'negative' => 0.8], 'language' => 'pcm'], $r['texts'])]),
        'analytics.test/v1/topics' => fn ($r) => Http::response(['results' => array_map(fn () => ['category' => 'health', 'confidence' => 0.9], $r['texts'])]),
        'analytics.test/v1/health/analyse' => fn ($r) => Http::response(['models' => [], 'results' => array_map(fn () => $result, $r['texts'])]),
        'analytics.test/v1/health/signals' => Http::response(['method' => 'c2', 'alerts' => [
            ['key' => 'lassa_fever|NG', 'start' => 55, 'end' => 55, 'peak' => 55, 'statistic' => 6.5, 'count' => 9],
        ]]),
        'analytics.test/v1/feedback' => fn ($r) => Http::response(['stored' => count($r['items'])]),
        'analytics.test/v1/admin/retrain' => Http::response(['started' => true], 202),
        'partner.test/*' => Http::response(['ok' => true]),
    ]);
}

test('analysing a post records a health mention with its place', function () {
    fakeHealthAnalytics(healthResult());
    $post = Post::factory()->create(['body' => 'Lassa fever don reach Owo', 'country' => 'PK']);

    (new AnalysePost($post))->handle(app(AnalyticsClient::class), app(HealthRecorder::class));

    $m = HealthMention::sole();
    expect($m->disease)->toBe('lassa_fever')
        ->and($m->country)->toBe('NG')       // place in the text beats the author's country
        ->and($m->region)->toBe('NG-ON')
        ->and($m->is_public)->toBeTrue();
});

test('non-health results store nothing and clear stale mentions', function () {
    $post = Post::factory()->create();
    app(HealthRecorder::class)->record($post, healthResult());
    app(HealthRecorder::class)->record($post, healthResult(['is_health' => false]));
    expect(HealthMention::count())->toBe(0);
});

test('likely misinformation on public posts goes to human review, never on private posts', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $public = Post::factory()->create();
    $private = Post::factory()->visibility(Visibility::Friends)->create();

    app(HealthRecorder::class)->record($public, healthResult(['misinformation_score' => 0.9]));
    app(HealthRecorder::class)->record($private, healthResult(['misinformation_score' => 0.95]));

    expect(MisinformationReview::pluck('post_id')->all())->toBe([$public->id]);
    expect($public->fresh()->hidden_at)->toBeNull();  // not removed automatically
    Notification::assertSentTo($admin, PostFlaggedForReview::class);
});

test('signal detection sends public counts only, raises one alert, notifies officers and the webhook', function () {
    Notification::fake();
    fakeHealthAnalytics(healthResult());
    config(['health.alert_webhook' => 'https://partner.test/hook', 'health.webhook_secret' => 'shh']);
    $officer = User::factory()->create();
    $officer->forceFill(['role' => Role::HealthOfficer])->save();
    $regular = User::factory()->create();

    $public = Post::factory()->create();
    $private = Post::factory()->visibility(Visibility::OnlyMe)->create();
    foreach ([$public, $private] as $p) {
        app(HealthRecorder::class)->record($p, healthResult());
    }

    $alerts = app(SignalDetector::class)->run();
    expect($alerts)->toHaveCount(1);
    expect(HealthAlert::sole()->only('disease', 'country', 'count', 'status'))
        ->toBe(['disease' => 'lassa_fever', 'country' => 'NG', 'count' => 9, 'status' => 'open']);

    Http::assertSent(function ($r) {
        if (!str_ends_with($r->url(), '/v1/health/signals')) {
            return false;
        }
        $series = collect($r['series'])->firstWhere('key', 'lassa_fever|NG');

        return array_sum($series['values']) === 1; // the private post was not counted
    });
    Http::assertSent(fn ($r) => $r->url() === 'https://partner.test/hook'
        && $r->hasHeader('X-SoleGlance-Signature', hash_hmac('sha256', $r->body(), 'shh')));
    Notification::assertSentTo($officer, HealthAlertRaised::class);
    Notification::assertNotSentTo($regular, HealthAlertRaised::class);

    // Cooldown: a second run does not duplicate the open alert.
    expect(app(SignalDetector::class)->run())->toHaveCount(0);
});

test('health dashboard shows only public content', function () {
    $viewer = User::factory()->create();
    $public = Post::factory()->create(['body' => 'public-cholera-post']);
    $private = Post::factory()->visibility(Visibility::Friends)->create(['body' => 'private-cholera-post']);
    foreach ([$public, $private] as $p) {
        app(HealthRecorder::class)->record($p, healthResult(['diseases' => ['cholera']]));
    }

    $this->actingAs($viewer)->get('/health')->assertOk()
        ->assertSee('public-cholera-post')->assertDontSee('private-cholera-post');
});

test('health admin screens require the health role', function () {
    $user = User::factory()->create();
    $officer = User::factory()->create();
    $officer->forceFill(['role' => Role::HealthOfficer])->save();

    $this->actingAs($user)->get('/admin/health/alerts')->assertForbidden();
    $this->actingAs($officer)->get('/admin/health/alerts')->assertOk();
    $this->actingAs($officer)->get('/admin/health/misinformation')->assertOk();
    $this->actingAs($officer)->get('/admin')->assertForbidden(); // not a full admin
});

test('confirming misinformation shows a correction on the post and records feedback', function () {
    $officer = User::factory()->create();
    $officer->forceFill(['role' => Role::HealthOfficer])->save();
    $post = Post::factory()->create(['body' => 'Drinking salt water cures Lassa']);
    $review = new MisinformationReview(['post_id' => $post->id, 'score' => 0.9, 'status' => 'pending']);
    $review->save();

    $this->actingAs($officer)->patch(route('admin.misinformation.update', $review), [
        'status' => 'confirmed', 'correction' => 'There is no evidence salt water treats Lassa fever. Seek care early.',
        'source_url' => 'https://ncdc.gov.ng',
    ])->assertRedirect();

    $this->actingAs(User::factory()->create())->get(route('posts.show', $post))
        ->assertSee('Health fact-check')->assertSee('no evidence salt water');
    expect(LabelFeedback::sole()->only('task', 'corrected'))->toBe(['task' => 'misinformation', 'corrected' => 'false']);
});

test('users can correct sentiment; only health staff can correct health labels', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user)->post('/feedback', ['subject_type' => 'post', 'subject_id' => $post->id, 'task' => 'sentiment', 'corrected' => 'positive'])
        ->assertRedirect();
    $this->actingAs($user)->post('/feedback', ['subject_type' => 'post', 'subject_id' => $post->id, 'task' => 'health_mention', 'corrected' => 'other'])
        ->assertForbidden();
    $this->actingAs($user)->post('/feedback', ['subject_type' => 'post', 'subject_id' => $post->id, 'task' => 'sentiment', 'corrected' => 'banana'])
        ->assertStatus(422);

    $hidden = Post::factory()->visibility(Visibility::OnlyMe)->create();
    $this->actingAs($user)->post('/feedback', ['subject_type' => 'post', 'subject_id' => $hidden->id, 'task' => 'sentiment', 'corrected' => 'positive'])
        ->assertForbidden();
    expect(LabelFeedback::count())->toBe(1);
});

test('feedback sync sends only public text and triggers retraining at the threshold', function () {
    fakeHealthAnalytics(healthResult());
    config(['health.retrain_after_feedback' => 2]);
    $author = User::factory()->create();
    $public = Post::factory()->for($author)->create(['body' => 'public text']);
    $friendsOnly = Post::factory()->for($author)->visibility(Visibility::Friends)->create(['body' => 'friends-only text']);
    $article = Article::factory()->create(['title' => 'News title', 'summary' => 'Summary']);

    foreach ([$public, $friendsOnly, $article] as $subject) {
        $fb = new LabelFeedback(['user_id' => $author->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'task' => 'sentiment', 'corrected' => 'positive']);
        $fb->save();
    }

    (new SyncFeedback)->handle(app(AnalyticsClient::class));

    Http::assertSent(function ($r) {
        if (!str_ends_with($r->url(), '/v1/feedback')) {
            return false;
        }
        $texts = collect($r['items'])->pluck('text');

        return $texts->contains('public text') && !$texts->contains('friends-only text') && $texts->count() === 2;
    });
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/admin/retrain'));
    expect(LabelFeedback::whereNull('sent_at')->count())->toBe(0);
});

test('health dashboard keeps the selected period', function () {
    $this->actingAs(User::factory()->create())->get('/health?days=30')
        ->assertOk()->assertSee('<option value="30" selected', false);
});
