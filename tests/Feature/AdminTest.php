<?php

use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use App\Notifications\AccountReinstated;
use App\Notifications\AccountSuspended;
use Illuminate\Support\Facades\Notification;

test('admin area is restricted to admins', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

test('role cannot be set through registration or profile update', function () {
    $this->post('/register', [
        'name' => 'Eve', 'username' => 'eve', 'email' => 'eve@example.com',
        'password' => 'password-123', 'password_confirmation' => 'password-123', 'role' => 'admin',
    ]);
    $eve = User::where('email', 'eve@example.com')->sole();
    expect($eve->isAdmin())->toBeFalse();

    $this->actingAs($eve)->patch('/profile', ['name' => 'Eve', 'username' => 'eve', 'email' => 'eve@example.com', 'role' => 'admin']);
    expect($eve->fresh()->isAdmin())->toBeFalse();
});

test('suspending a user signs them out, notifies them and is audited', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $user), ['reason' => 'Spam'])->assertRedirect();

    expect($user->fresh()->isSuspended())->toBeTrue();
    Notification::assertSentTo($user, AccountSuspended::class);
    expect(ModerationAction::sole()->only('admin_id', 'target_user_id', 'action', 'reason'))
        ->toBe(['admin_id' => $admin->id, 'target_user_id' => $user->id, 'action' => 'suspend', 'reason' => 'Spam']);

    $this->actingAs($user->fresh())->get('/feed')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->actingAs($admin)->post(route('admin.users.unsuspend', $user));
    expect($user->fresh()->isSuspended())->toBeFalse();
    Notification::assertSentTo($user, AccountReinstated::class);
});

test('suspended users cannot log in', function () {
    $user = User::factory()->suspended('Abuse')->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->get('/feed')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('admins cannot be suspended or deleted from the panel', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.users.suspend', $other))->assertStatus(422);
    $this->actingAs($admin)->delete(route('admin.users.destroy', $other), ['confirm' => $other->username])->assertStatus(422);
});

test('deleting a user requires typing their username', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['username' => 'victim']);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user), ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
    expect(User::whereKey($user->id)->exists())->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user), ['confirm' => 'victim']);
    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

test('hiding a post removes it from feeds', function () {
    $admin = User::factory()->admin()->create();
    $post = Post::factory()->create(['body' => 'abusive-content']);
    $viewer = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.posts.hide', $post), ['reason' => 'Abuse']);
    $this->actingAs($viewer)->get('/feed')->assertDontSee('abusive-content');

    $this->actingAs($admin)->post(route('admin.posts.unhide', $post));
    $this->actingAs($viewer)->get('/feed')->assertSee('abusive-content');
});

test('moderation list never exposes private posts', function () {
    $admin = User::factory()->admin()->create();
    Post::factory()->visibility(\App\Enums\Visibility::OnlyMe)->create(['body' => 'my-diary']);
    $this->actingAs($admin)->get(route('admin.posts.index'))->assertDontSee('my-diary');
});
