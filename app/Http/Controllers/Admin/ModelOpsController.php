<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelFeedback;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ModelOpsController extends Controller
{
    public function index(AnalyticsClient $analytics): View
    {
        return view('admin.models', [
            'enabled' => $analytics->enabled(),
            'health' => $analytics->health(),
            'metrics' => $analytics->adminGet('/v1/admin/metrics'),
            'registry' => $analytics->adminGet('/v1/admin/registry'),
            'feedback' => [
                'total' => LabelFeedback::count(),
                'unsent' => LabelFeedback::whereNull('sent_at')->count(),
                'by_task' => LabelFeedback::selectRaw('task, count(*) as n')->groupBy('task')->pluck('n', 'task'),
            ],
        ]);
    }

    public function retrain(AnalyticsClient $analytics): RedirectResponse
    {
        $result = $analytics->retrain();

        return back()->with('status', ($result['started'] ?? false) ? 'Retraining started; the new model is promoted only if it passes the gate.' : 'Retraining already running.');
    }
}
