<?php

use App\Jobs\AnalysePost;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\PostCommented;
use App\Notifications\PostReacted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('a user can publish a post with an image, which is queued for analysis', function () {
    Queue::fake();
    Storage::fake('public');
    $user = User::factory()->create(['country' => 'PK']);

    $this->actingAs($user)->post('/posts', [
        'body' => 'Hello world',
        'visibility' => 'public',
        'media' => [UploadedFile::fake()->image('photo.jpg')],
    ])->assertRedirect('/feed');

    $post = Post::sole();
    expect($post->body)->toBe('Hello world')
        ->and($post->country)->toBe('PK')
        ->and($post->media)->toHaveCount(1);
    expect($post->media[0]->path)->not->toContain('photo');
    Storage::disk('public')->assertExists($post->media[0]->path);
    Queue::assertPushed(AnalysePost::class);
});

test('executable uploads are rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post('/posts', [
        'body' => 'x', 'visibility' => 'public',
        'media' => [UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>')],
    ])->assertSessionHasErrors('media.0');

    expect(Post::count())->toBe(0);
});

test('custom audience is limited to the author\'s friends', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    befriend($user, $friend);

    $this->actingAs($user)->post('/posts', [
        'body' => 'for some', 'visibility' => 'custom', 'audience' => [$friend->id, $stranger->id],
    ]);

    expect(Post::sole()->audience->pluck('id')->all())->toBe([$friend->id]);
});

test('only the author can edit; author or admin can delete', function () {
    $post = Post::factory()->create();
    $other = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($other)->patch(route('posts.update', $post), ['body' => 'hacked', 'visibility' => 'public'])->assertForbidden();
    $this->actingAs($admin)->patch(route('posts.update', $post), ['body' => 'hacked', 'visibility' => 'public'])->assertForbidden();
    $this->actingAs($other)->delete(route('posts.destroy', $post))->assertForbidden();

    $this->actingAs($post->user)->patch(route('posts.update', $post), ['body' => 'edited', 'visibility' => 'friends'])->assertRedirect();
    expect($post->fresh()->body)->toBe('edited');

    $this->actingAs($admin)->delete(route('posts.destroy', $post))->assertRedirect();
    expect(Post::count())->toBe(0);
});

test('reactions toggle and are always attributed to the signed-in user', function () {
    Notification::fake();
    $post = Post::factory()->create();
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('posts.react', $post), ['type' => 'love', 'user_id' => $post->user_id]);
    expect(Reaction::sole()->user_id)->toBe($me->id);
    Notification::assertSentTo($post->user, PostReacted::class);

    $this->actingAs($me)->post(route('posts.react', $post), ['type' => 'haha']);
    expect(Reaction::sole()->type->value)->toBe('haha');

    $this->actingAs($me)->post(route('posts.react', $post), ['type' => 'haha']);
    expect(Reaction::count())->toBe(0);
});

test('cannot react to or comment on posts you cannot see', function () {
    $post = Post::factory()->visibility(\App\Enums\Visibility::Friends)->create();
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('posts.react', $post), ['type' => 'like'])->assertForbidden();
    $this->actingAs($me)->post(route('comments.store', $post), ['body' => 'hi'])->assertForbidden();
});

test('comments notify the author and can be removed by the post owner', function () {
    Notification::fake();
    Queue::fake();
    $post = Post::factory()->create();
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('comments.store', $post), ['body' => 'Nice!'])->assertRedirect();
    Notification::assertSentTo($post->user, PostCommented::class);

    $comment = $post->comments()->sole();
    $this->actingAs(User::factory()->create())->delete(route('comments.destroy', $comment))->assertForbidden();
    $this->actingAs($post->user)->delete(route('comments.destroy', $comment))->assertRedirect();
    expect($post->comments()->count())->toBe(0);
});
