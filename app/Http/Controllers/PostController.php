<?php

namespace App\Http\Controllers;

use App\Enums\ReactionType;
use App\Enums\Visibility;
use App\Jobs\AnalysePost;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public const EAGER = ['user:id,name,username,avatar_path', 'media', 'misinformationReview'];

    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const VIDEO_MIMES = ['mp4', 'webm', 'mov'];

    /**
     * Adds reaction counts and the viewer's own reaction to each post in two queries.
     */
    public static function attachReactionSummary(Collection $posts, ?User $viewer): void
    {
        $ids = $posts->pluck('id');
        $counts = Reaction::whereIn('post_id', $ids)
            ->select('post_id', 'type', DB::raw('count(*) as n'))
            ->groupBy('post_id', 'type')->get()->groupBy('post_id');
        $mine = $viewer
            ? Reaction::whereIn('post_id', $ids)->where('user_id', $viewer->id)->pluck('type', 'post_id')
            : collect();

        foreach ($posts as $post) {
            $byType = ($counts[$post->id] ?? collect())->mapWithKeys(fn ($r) => [$r->type->value => (int) $r->n]);
            $post->setAttribute('reaction_counts', $byType->sortDesc()->all());
            $post->setAttribute('reaction_total', $byType->sum());
            $post->setAttribute('my_reaction', $mine[$post->id] ?? null);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'audience' => ['array', 'required_if:visibility,custom'],
            'audience.*' => ['integer'],
            'media' => ['array', 'max:4'],
            'media.*' => ['file', 'max:51200', 'mimes:' . implode(',', [...self::IMAGE_MIMES, ...self::VIDEO_MIMES])],
        ]);

        $post = DB::transaction(function () use ($user, $data, $request) {
            $post = new Post([
                'body' => $data['body'],
                'visibility' => $data['visibility'],
                'country' => $user->country,
            ]);
            $post->user()->associate($user);
            $post->save();

            if ($post->visibility === Visibility::Custom) {
                // Only the author's accepted friends may be chosen.
                $allowed = $user->friendIds()->intersect($data['audience'] ?? [])->values();
                $post->audience()->sync($allowed);
            }

            foreach ($request->file('media', []) as $file) {
                $ext = strtolower((string) $file->guessExtension());
                $type = in_array($ext, self::IMAGE_MIMES, true) ? 'image' : 'video';
                // store() names the file with a random hash and a content-derived extension.
                $path = $file->store("posts/{$post->id}", 'public');
                $post->media()->create([
                    'type' => $type,
                    'path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $post;
        });

        AnalysePost::dispatch($post)->afterCommit();

        return redirect()->route('feed')->with('status', 'Post published.');
    }

    public function show(Request $request, Post $post): View
    {
        Gate::authorize('view', $post);

        $post->load([...self::EAGER, 'comments' => fn ($q) => $q->with('user:id,name,username,avatar_path')->oldest()]);
        self::attachReactionSummary(collect([$post]), $request->user());

        return view('posts.show', [
            'post' => $post,
            'reactionTypes' => ReactionType::cases(),
        ]);
    }

    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.edit', ['post' => $post, 'visibilities' => Visibility::cases()]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'visibility' => ['required', Rule::enum(Visibility::class), Rule::notIn([Visibility::Custom->value])],
        ]);

        $bodyChanged = $post->body !== $data['body'];
        $post->update($data);
        if ($post->visibility !== Visibility::Custom) {
            $post->audience()->detach();
        }
        if ($bodyChanged) {
            $post->forceFill(['analysed_at' => null])->save();
            AnalysePost::dispatch($post);
        }

        return redirect()->route('posts.show', $post)->with('status', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);
        $post->delete();

        return redirect()->route('feed')->with('status', 'Post deleted.');
    }
}
