<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyseComment;
use App\Models\Comment;
use App\Models\Post;
use App\Notifications\PostCommented;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('view', $post);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $user = $request->user();

        $comment = new Comment($data);
        $comment->post()->associate($post);
        $comment->user()->associate($user);
        $comment->save();

        AnalyseComment::dispatch($comment);

        if ($post->user_id !== $user->id) {
            $post->user->notify(new PostCommented($user, $post->id));
        }

        return redirect()->to(route('posts.show', $post) . '#comment-' . $comment->id);
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);
        $comment->delete();

        return back()->with('status', 'Comment deleted.');
    }
}
