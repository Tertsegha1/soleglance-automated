<?php

namespace App\Services\Search;

use App\Models\Article;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Keyword search over posts and articles with the date and region filters
 * from the SRS (section 1.2.3). Shared by search, topic pages and reports.
 */
class TopicSearch
{
    public static function escapeLike(string $term): string
    {
        return addcslashes($term, '%_\\');
    }

    public function posts(string $term, ?User $viewer, ?Carbon $from = null, ?Carbon $to = null, ?string $country = null): Builder
    {
        return Post::query()
            ->visibleTo($viewer)
            ->where('body', 'like', '%' . self::escapeLike($term) . '%')
            ->when($from, fn ($q) => $q->where('posts.created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('posts.created_at', '<=', $to->copy()->endOfDay()))
            ->when($country, fn ($q) => $q->where('posts.country', $country));
    }

    /** Public posts only: used for aggregate analytics so private posts never leak into reports. */
    public function publicPosts(string $term, ?Carbon $from = null, ?Carbon $to = null, ?string $country = null): Builder
    {
        return Post::query()
            ->publiclyAnalysable()
            ->whereHas('user', fn ($q) => $q->whereNull('suspended_at'))
            ->where('body', 'like', '%' . self::escapeLike($term) . '%')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->when($country, fn ($q) => $q->where('country', $country));
    }

    public function articles(string $term, ?Carbon $from = null, ?Carbon $to = null, ?string $country = null): Builder
    {
        $like = '%' . self::escapeLike($term) . '%';

        return Article::query()
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like))
            ->when($from, fn ($q) => $q->where('published_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('published_at', '<=', $to->copy()->endOfDay()))
            ->when($country, fn ($q) => $q->where('country', $country));
    }

    /**
     * Daily sentiment counts for a topic across public posts and articles.
     *
     * @return array{days: list<string>, positive: list<int>, neutral: list<int>, negative: list<int>, totals: array<string, int>}
     */
    public function sentimentTimeline(string $term, Carbon $from, Carbon $to, ?string $country = null): array
    {
        // Aggregate in SQL; only (day, label, count) rows come back.
        $count = fn ($query, string $column) => $query->whereNotNull('sentiment')
            ->selectRaw("date({$column}) as day, sentiment, count(*) as n")
            ->groupBy('day', 'sentiment')->toBase()->get();
        $rows = $count($this->publicPosts($term, $from, $to, $country), 'created_at')
            ->concat($count($this->articles($term, $from, $to, $country), 'published_at'));

        $days = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $totals = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        foreach ($rows as $row) {
            if (isset($days[$row->day][$row->sentiment])) {
                $days[$row->day][$row->sentiment] += (int) $row->n;
                $totals[$row->sentiment] += (int) $row->n;
            }
        }

        return [
            'days' => array_keys($days),
            'positive' => array_column($days, 'positive'),
            'neutral' => array_column($days, 'neutral'),
            'negative' => array_column($days, 'negative'),
            'totals' => $totals,
        ];
    }
}
