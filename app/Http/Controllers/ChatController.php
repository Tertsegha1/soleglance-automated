<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessagePayload;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        return view('chat.index', [
            'conversations' => $this->conversationsFor($request->user()),
            'conversation' => null,
            'messages' => collect(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);
        $me = $request->user();
        $conversation->load('participants');

        $search = trim((string) $request->query('q'));
        $messages = $conversation->messagesFor($me)
            ->with('user:id,name,username,avatar_path')
            ->when($search !== '', fn ($q) => $q->where('body', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->latest('id')->limit(100)->get()->reverse()->values();

        $conversation->participants()->updateExistingPivot($me->id, ['last_read_at' => now()]);

        return view('chat.index', [
            'conversations' => $this->conversationsFor($me),
            'conversation' => $conversation,
            'other' => $conversation->otherParticipant($me),
            'messages' => $messages,
            'search' => $search,
        ]);
    }

    /**
     * Opens (or creates) the direct conversation with a friend.
     */
    public function with(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless($me->isFriendsWith($user), 403, 'You can only message friends.');

        return redirect()->route('chat.show', Conversation::between($me, $user));
    }

    public function send(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $me = $request->user();
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $other = $conversation->load('participants')->otherParticipant($me);
        abort_unless($other && $me->isFriendsWith($other), 403, 'You can only message friends.');

        $message = new Message($data);
        $message->conversation()->associate($conversation);
        $message->user()->associate($me);
        $message->save();

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->updateExistingPivot($me->id, ['last_read_at' => now()]);

        broadcast(new MessageSent($message))->toOthers();

        if ($request->expectsJson()) {
            return response()->json(MessagePayload::make($message), 201);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function destroyMessage(Request $request, Conversation $conversation, Message $message): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id && $message->user_id === $request->user()->id, 403);

        $message->delete();
        broadcast(new MessageDeleted($conversation->id, $message->id))->toOthers();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    /**
     * "Delete chat": hides the history for the current user only.
     */
    public function clear(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['cleared_at' => now()]);

        return redirect()->route('chat.index')->with('status', 'Chat history cleared.');
    }

    private function conversationsFor(User $me)
    {
        return $me->conversations()
            ->with(['participants:id,name,username,avatar_path', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $c) use ($me) {
                $lastRead = $c->pivot->last_read_at;
                // Don't preview a message this participant has cleared.
                if ($c->pivot->cleared_at && $c->latestMessage && $c->latestMessage->created_at <= $c->pivot->cleared_at) {
                    $c->setRelation('latestMessage', null);
                }
                $c->setAttribute('unread', $c->messages()
                    ->where('user_id', '!=', $me->id)
                    ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
                    ->when($c->pivot->cleared_at, fn ($q) => $q->where('created_at', '>', $c->pivot->cleared_at))
                    ->count());

                return $c;
            });
    }
}
