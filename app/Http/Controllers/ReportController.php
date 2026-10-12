<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReport;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => $request->user()->hasMany(Report::class)->latest()->paginate(20),
            'prefill' => $request->only('topic', 'from', 'to', 'country'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:100'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);
        abort_if(now()->parse($data['from'])->diffInDays($data['to']) > 366, 422, 'Please choose a range of at most one year.');

        $report = new Report([...$data, 'country' => isset($data['country']) ? strtoupper($data['country']) : null]);
        $report->user()->associate($request->user());
        $report->save();

        GenerateReport::dispatch($report);

        return redirect()->route('reports.index')->with('status', 'Your report is being generated.');
    }

    public function download(Report $report): StreamedResponse
    {
        Gate::authorize('view', $report);
        abort_unless($report->status === 'ready' && $report->path, 404);

        $name = 'soleglance-' . str($report->topic)->slug() . '-' . $report->from->toDateString() . '.pdf';

        return Storage::disk('local')->download($report->path, $name);
    }
}
