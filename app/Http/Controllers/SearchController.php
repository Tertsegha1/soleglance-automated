<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Search\TopicSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, TopicSearch $search): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:all,people,posts,news'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $q = trim($filters['q'] ?? '');
        $type = $filters['type'] ?? 'all';
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : null;
        $country = isset($filters['country']) ? strtoupper($filters['country']) : null;
        $viewer = $request->user();

        $people = $posts = $articles = collect();

        if ($q !== '') {
            if (in_array($type, ['all', 'people'], true)) {
                // Names and usernames only; emails are never searchable.
                $like = '%' . TopicSearch::escapeLike($q) . '%';
                $people = User::active()
                    ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like))
                    ->when($country, fn ($w) => $w->where('country', $country))
                    ->limit($type === 'people' ? 50 : 6)->get();
            }

            if (in_array($type, ['all', 'posts'], true)) {
                $posts = $search->posts($q, $viewer, $from, $to, $country)
                    ->with(PostController::EAGER)->withCount('comments')
                    ->latest()->limit($type === 'posts' ? 50 : 10)->get();
                PostController::attachReactionSummary($posts, $viewer);
            }

            if (in_array($type, ['all', 'news'], true)) {
                $articles = $search->articles($q, $from, $to, $country)
                    ->with('source')->latest('published_at')->limit($type === 'news' ? 50 : 10)->get();
            }
        }

        return view('search.index', compact('q', 'type', 'people', 'posts', 'articles') + [
            'filters' => $filters,
        ]);
    }
}
