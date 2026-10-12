<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        // Authors, the owner of the post, and admins can remove a comment.
        return $comment->user_id === $user->id
            || $comment->post->user_id === $user->id
            || $user->isAdmin();
    }
}
