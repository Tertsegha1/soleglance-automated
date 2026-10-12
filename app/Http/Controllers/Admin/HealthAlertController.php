<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HealthAlertController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('admin.health-alerts', [
            'alerts' => HealthAlert::with('handler:id,name')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('window_end')->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function update(Request $request, HealthAlert $alert): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['acknowledged', 'resolved', 'dismissed', 'open'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $alert->fill($data);
        $alert->handled_by = $request->user()->id;
        $alert->save();

        return back()->with('status', 'Alert updated.');
    }
}
