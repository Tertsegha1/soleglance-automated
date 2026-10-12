<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Services\Search\TopicSearch;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the downloadable analytical report for a topic (SRS 1.2.7).
 * Only public posts and news articles are included.
 */
class ReportBuilder
{
    public function __construct(private TopicSearch $search)
    {
    }

    public function data(Report $report): array
    {
        $timeline = $this->search->sentimentTimeline($report->topic, $report->from, $report->to, $report->country);

        $posts = $this->search->publicPosts($report->topic, $report->from, $report->to, $report->country);
        $articles = $this->search->articles($report->topic, $report->from, $report->to, $report->country);

        return [
            'report' => $report,
            'timeline' => $timeline,
            'postCount' => (clone $posts)->count(),
            'articleCount' => (clone $articles)->count(),
            'categoryCounts' => (clone $articles)->whereNotNull('category')
                ->selectRaw('category, count(*) as n')->groupBy('category')->orderByDesc('n')->pluck('n', 'category'),
            'mostPositive' => (clone $articles)->where('sentiment', 'positive')->orderByDesc('sentiment_score')->limit(5)->get(),
            'mostNegative' => (clone $articles)->where('sentiment', 'negative')->orderByDesc('sentiment_score')->limit(5)->get(),
            'samplePosts' => (clone $posts)->with('user')->latest()->limit(10)->get(),
            'generatedAt' => now(),
        ];
    }

    public function pdf(Report $report): string
    {
        return Pdf::loadView('reports.pdf', $this->data($report))
            ->setPaper('a4')
            ->output();
    }
}
