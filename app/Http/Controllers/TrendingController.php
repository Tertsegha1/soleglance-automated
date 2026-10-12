<?php

namespace App\Http\Controllers;

use App\Enums\Category;
use App\Models\Article;
use App\Models\Post;
use App\Models\Trend;
use App\Services\Search\TopicSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class TrendingController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['category' => ['nullable', Rule::enum(Category::class)]]);
        $latest = Trend::max('window_end');

        $trends = $latest
            ? Trend::where('window_end', $latest)
                ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
                ->orderByDesc('score')->get()
            : collect();

        // Sentiment mix per category for the dashboard tiles (last 7 days, public content only).
        $since = now()->subDays(7);
        $categoryMix = Article::where('published_at', '>=', $since)->whereNotNull('category')->whereNotNull('sentiment')
            ->selectRaw('category, sentiment, count(*) as n')->groupBy('category', 'sentiment')->get()
            ->concat(Post::publiclyAnalysable()->where('created_at', '>=', $since)->whereNotNull('category')->whereNotNull('sentiment')
                ->selectRaw('category, sentiment, count(*) as n')->groupBy('category', 'sentiment')->get())
            ->groupBy(fn ($r) => $r->category->value)
            ->map(fn ($rows) => [
                'positive' => $rows->filter(fn ($r) => $r->sentiment->value === 'positive')->sum('n'),
                'neutral' => $rows->filter(fn ($r) => $r->sentiment->value === 'neutral')->sum('n'),
                'negative' => $rows->filter(fn ($r) => $r->sentiment->value === 'negative')->sum('n'),
            ]);

        return view('trending.index', [
            'trends' => $trends,
            'categories' => Category::cases(),
            'selected' => $data['category'] ?? null,
            'categoryMix' => $categoryMix,
            'computedAt' => $latest ? Carbon::parse($latest) : null,
        ]);
    }

    /**
     * Topic page: sentiment over time plus matching posts and articles.
     */
    public function show(Request $request, TopicSearch $search): View
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $term = trim($data['q']);
        $to = isset($data['to']) ? Carbon::parse($data['to']) : now();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->subDays(13);
        abort_if($from->diffInDays($to) > 366, 422, 'Please choose a range of at most one year.');
        $country = isset($data['country']) ? strtoupper($data['country']) : null;
        $viewer = $request->user();

        $posts = $search->posts($term, $viewer, $from, $to, $country)
            ->with(PostController::EAGER)->withCount('comments')->latest()->limit(20)->get();
        PostController::attachReactionSummary($posts, $viewer);

        return view('trending.show', [
            'term' => $term,
            'from' => $from,
            'to' => $to,
            'country' => $country,
            'timeline' => $search->sentimentTimeline($term, $from, $to, $country),
            'posts' => $posts,
            'articles' => $search->articles($term, $from, $to, $country)->with('source')->latest('published_at')->limit(20)->get(),
            'history' => Trend::where('term', mb_strtolower($term))->where('window_end', '>=', $from)
                ->orderBy('window_end')->get(['window_end', 'mentions', 'score']),
        ]);
    }
}
