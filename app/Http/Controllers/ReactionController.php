<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Models\Post;
use App\Models\Reaction;
use App\Notifications\PostReacted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    /**
     * React to a post. Sending the same reaction again removes it.
     */
    public function store(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('view', $post);
        $data = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)]]);
        $user = $request->user();

        $existing = Reaction::where('post_id', $post->id)->where('user_id', $user->id)->first();

        if ($existing && $existing->type->value === $data['type']) {
            $existing->delete();

            return back();
        }

        Reaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $user->id],
            ['type' => $data['type']],
        );

        if (!$existing && $post->user_id !== $user->id) {
            $post->user->notify(new PostReacted($user, $post->id, $data['type']));
        }

        return back();
    }
}
