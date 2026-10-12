<?php

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    [$this->a, $this->b, $this->c] = User::factory()->count(3)->create();
    befriend($this->a, $this->b);
});

test('friends can open a conversation and exchange messages in real time', function () {
    Event::fake([MessageSent::class]);

    $this->actingAs($this->a)->post(route('chat.with', $this->b))->assertRedirect();
    $conversation = Conversation::sole();

    $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'Hi <b>there</b>'])
        ->assertCreated()->assertJsonPath('body', 'Hi <b>there</b>')->assertJsonPath('user.id', $this->a->id);
    Event::assertDispatched(MessageSent::class, fn ($e) => $e->broadcastOn()[0]->name === "private-conversations.{$conversation->id}");

    // The page escapes message bodies.
    $this->actingAs($this->b)->get(route('chat.show', $conversation))
        ->assertOk()->assertSee('Hi &lt;b&gt;there&lt;/b&gt;', false);
});

test('non-friends cannot start chats and outsiders cannot read them', function () {
    $this->actingAs($this->a)->post(route('chat.with', $this->c))->assertForbidden();

    $conversation = Conversation::between($this->a, $this->b);
    $this->actingAs($this->c)->get(route('chat.show', $conversation))->assertForbidden();
    $this->actingAs($this->c)->postJson(route('chat.send', $conversation), ['body' => 'x'])->assertForbidden();
});

test('messaging stops after unfriending', function () {
    $conversation = Conversation::between($this->a, $this->b);
    $this->actingAs($this->a)->delete(route('friends.destroy', $this->b));
    $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'x'])->assertForbidden();
});

test('only the sender can delete a message', function () {
    Event::fake([MessageSent::class, MessageDeleted::class]);
    $conversation = Conversation::between($this->a, $this->b);
    $id = $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'oops'])->json('id');

    $this->actingAs($this->b)->deleteJson(route('chat.messages.destroy', [$conversation, $id]))->assertForbidden();
    $this->actingAs($this->a)->deleteJson(route('chat.messages.destroy', [$conversation, $id]))->assertOk();
    expect(Message::count())->toBe(0);
    Event::assertDispatched(MessageDeleted::class);
});

test('clearing a chat hides history only for that participant', function () {
    Event::fake([MessageSent::class]);
    $conversation = Conversation::between($this->a, $this->b);
    $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'old-message']);

    $this->travel(1)->seconds();
    $this->actingAs($this->a)->delete(route('chat.clear', $conversation));

    $this->actingAs($this->a)->get(route('chat.show', $conversation))->assertDontSee('old-message');
    $this->actingAs($this->b)->get(route('chat.show', $conversation))->assertSee('old-message');
});

test('message search finds matching messages only', function () {
    Event::fake([MessageSent::class]);
    $conversation = Conversation::between($this->a, $this->b);
    $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'meet at the library']);
    $this->actingAs($this->a)->postJson(route('chat.send', $conversation), ['body' => 'bring snacks']);

    $html = $this->actingAs($this->b)->get(route('chat.show', [$conversation, 'q' => 'library']))->getContent();
    $thread = str($html)->after('data-messages')->before('</ol>');

    expect((string) $thread)->toContain('meet at the library')->not->toContain('bring snacks');
});

test('broadcast channel authorisation only admits participants', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'k',
        'broadcasting.connections.reverb.secret' => 's',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    \Illuminate\Support\Facades\Broadcast::purge();
    require base_path('routes/channels.php');
    $conversation = Conversation::between($this->a, $this->b);
    $payload = ['socket_id' => '1234.5678', 'channel_name' => "private-conversations.{$conversation->id}"];

    $this->actingAs($this->a)->post('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($this->c)->post('/broadcasting/auth', $payload)->assertForbidden();
});
