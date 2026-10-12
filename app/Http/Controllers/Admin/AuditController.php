<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.audit', [
            'actions' => ModerationAction::with(['admin:id,name', 'targetUser:id,name,username'])->latest()->paginate(50),
        ]);
    }
}
