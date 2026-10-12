<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UserController extends Controller
{
    public function show(Request $request, string $username): View
    {
        $profile = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();

        abort_if($profile->isSuspended() && !$viewer?->isAdmin(), 404);

        $posts = Post::query()->visibleTo($viewer)
            ->where('posts.user_id', $profile->id)
            ->with(PostController::EAGER)->withCount('comments')
            ->latest()->simplePaginate(15);
        PostController::attachReactionSummary($posts->getCollection(), $viewer);

        return view('users.show', [
            'profile' => $profile,
            'posts' => $posts,
            'friendship' => $viewer && $viewer->id !== $profile->id ? $viewer->friendshipWith($profile) : null,
            'friendCount' => $profile->friendIds()->count(),
        ]);
    }

    /**
     * Initials avatar as SVG so profiles without a photo need no external service.
     */
    public function avatar(string $username): Response
    {
        $user = User::where('username', $username)->firstOrFail(['name']);
        $initials = collect(preg_split('/\s+/u', trim($user->name)))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $hue = crc32($username) % 360;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="hsl(' . $hue . ',45%,45%)"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#fff">'
            . e($initials) . '</text></svg>';

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
