<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Models\Post;
use App\Models\Trend;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $posts = Post::query()
            ->visibleTo($user)
            ->with(PostController::EAGER)
            ->withCount('comments')
            ->latest()
            ->simplePaginate(15);

        PostController::attachReactionSummary($posts->getCollection(), $user);

        return view('feed.index', [
            'posts' => $posts,
            'friends' => $user->friends()->orderBy('name')->get(['id', 'name', 'username']),
            'visibilities' => Visibility::cases(),
            'trends' => Trend::query()->latest('window_end')->limit(1)->value('window_end')
                ? Trend::where('window_end', Trend::max('window_end'))->orderByDesc('score')->limit(5)->get()
                : collect(),
        ]);
    }
}
