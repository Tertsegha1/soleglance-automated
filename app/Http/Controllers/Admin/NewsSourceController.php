<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Category;
use App\Http\Controllers\Controller;
use App\Jobs\AnalysePendingArticles;
use App\Jobs\FetchNewsSource;
use App\Models\NewsSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsSourceController extends Controller
{
    public function index(): View
    {
        return view('admin.news-sources', [
            'sources' => NewsSource::withCount('articles')->orderBy('name')->get(),
            'categories' => Category::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'feed_url' => ['required', 'url:https,http', 'max:255', 'unique:news_sources,feed_url'],
            'category' => ['nullable', Rule::enum(Category::class)],
            'country' => ['nullable', 'string', 'size:2'],
            'language' => ['required', 'string', 'max:12'],
        ]);
        $data['country'] = isset($data['country']) ? strtoupper($data['country']) : null;

        $source = NewsSource::create($data);
        FetchNewsSource::dispatch($source);
        AnalysePendingArticles::dispatch()->delay(now()->addMinute());

        return back()->with('status', 'Source added; first fetch queued.');
    }

    public function toggle(NewsSource $source): RedirectResponse
    {
        $source->update(['active' => !$source->active]);

        return back();
    }

    public function destroy(NewsSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('status', 'Source and its articles removed.');
    }
}
