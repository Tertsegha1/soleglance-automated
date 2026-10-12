<?php

namespace App\Http\Controllers;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();

        return view('friends.index', [
            'friends' => $me->friends()->orderBy('name')->get(),
            'incoming' => Friendship::with('requester')->where('addressee_id', $me->id)
                ->where('status', FriendshipStatus::Pending)->latest()->get(),
            'outgoing' => Friendship::with('addressee')->where('requester_id', $me->id)
                ->where('status', FriendshipStatus::Pending)->latest()->get(),
            'suggestions' => $this->suggestions($me),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($user->id === $me->id || $user->isSuspended(), 422, 'You cannot add this user.');

        $existing = $me->friendshipWith($user);

        // If they already asked us, sending a request back accepts theirs.
        if ($existing && $existing->status === FriendshipStatus::Pending && $existing->requester_id === $user->id) {
            return $this->accept($request, $user);
        }

        if ($existing) {
            return back()->with('status', $existing->status === FriendshipStatus::Accepted ? 'You are already friends.' : 'Request already sent.');
        }

        try {
            Friendship::create(['requester_id' => $me->id, 'addressee_id' => $user->id, 'status' => FriendshipStatus::Pending]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('status', 'Request already sent.');
        }

        $user->notify(new FriendRequestReceived($me));

        return back()->with('status', 'Friend request sent.');
    }

    public function accept(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $pending = Friendship::where('requester_id', $user->id)->where('addressee_id', $me->id)
            ->where('status', FriendshipStatus::Pending)->firstOrFail();

        $pending->update(['status' => FriendshipStatus::Accepted, 'accepted_at' => now()]);
        $this->clearRequestNotification($me, $user);
        $user->notify(new FriendRequestAccepted($me));

        return back()->with('status', "You and {$user->name} are now friends.");
    }

    public function decline(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        Friendship::where('requester_id', $user->id)->where('addressee_id', $me->id)
            ->where('status', FriendshipStatus::Pending)->firstOrFail()->delete();
        $this->clearRequestNotification($me, $user);

        return back()->with('status', 'Request declined.');
    }

    /**
     * Cancels an outgoing request or removes an existing friend.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $friendship = $me->friendshipWith($user);
        abort_unless($friendship, 404);

        // Declining someone else's request goes through decline(), not here.
        abort_if($friendship->status === FriendshipStatus::Pending && $friendship->requester_id !== $me->id, 404);

        $friendship->delete();

        return back()->with('status', $friendship->status === FriendshipStatus::Accepted ? 'Friend removed.' : 'Request cancelled.');
    }

    private function clearRequestNotification(User $me, User $from): void
    {
        $me->notifications()
            ->where('type', FriendRequestReceived::class)
            ->get()
            ->filter(fn ($n) => ($n->data['actor_id'] ?? null) === $from->id)
            ->each->markAsRead();
    }

    /**
     * Friends of friends that the user is not connected to yet.
     */
    private function suggestions(User $me)
    {
        $friendIds = $me->friendIds();
        if ($friendIds->isEmpty()) {
            return User::active()->whereKeyNot($me->id)->whereNotNull('email_verified_at')
                ->whereNotIn('id', $this->connectedIds($me))->inRandomOrder()->limit(5)->get();
        }

        $fof = Friendship::where('status', FriendshipStatus::Accepted)
            ->where(fn ($q) => $q->whereIn('requester_id', $friendIds)->orWhereIn('addressee_id', $friendIds))
            ->get(['requester_id', 'addressee_id'])
            ->flatMap(fn ($f) => [$f->requester_id, $f->addressee_id])
            ->countBy()
            ->except([$me->id, ...$this->connectedIds($me)])
            ->sortDesc()->keys()->take(5);

        return User::active()->whereIn('id', $fof)->get();
    }

    private function connectedIds(User $me): array
    {
        return Friendship::where('requester_id', $me->id)->orWhere('addressee_id', $me->id)
            ->get(['requester_id', 'addressee_id'])
            ->flatMap(fn ($f) => [$f->requester_id, $f->addressee_id])
            ->unique()->values()->all();
    }
}
