<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'recent');

        // Moderators see public posts and anything already hidden; private
        // ("only me") posts are never listed.
        $posts = Post::query()
            ->with('user:id,name,username')
            ->where('visibility', '!=', Visibility::OnlyMe)
            ->when($filter === 'hidden', fn ($q) => $q->whereNotNull('hidden_at'))
            ->when($filter === 'negative', fn ($q) => $q->where('sentiment', 'negative')->orderByDesc('sentiment_score'))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.posts', compact('posts', 'filter'));
    }

    public function hide(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $post->forceFill(['hidden_at' => now(), 'hidden_reason' => $data['reason'] ?? null])->save();
        $this->log($request, 'hide_post', $post, $data['reason'] ?? null);

        return back()->with('status', 'Post hidden.');
    }

    public function unhide(Request $request, Post $post): RedirectResponse
    {
        $post->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();
        $this->log($request, 'unhide_post', $post);

        return back()->with('status', 'Post restored.');
    }

    private function log(Request $request, string $action, Post $post, ?string $reason = null): void
    {
        ModerationAction::create([
            'admin_id' => $request->user()->id,
            'target_user_id' => $post->user_id,
            'target_post_id' => $post->id,
            'action' => $action,
            'reason' => $reason,
        ]);
    }
}
