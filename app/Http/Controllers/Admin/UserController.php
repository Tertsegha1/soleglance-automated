<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\User;
use App\Notifications\AccountReinstated;
use App\Notifications\AccountSuspended;
use App\Services\Search\TopicSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . TopicSearch::escapeLike($q) . '%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($status === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($status === 'admins', fn ($query) => $query->where('role', 'admin'))
            ->withCount('posts')
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users', compact('users', 'q', 'status'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        abort_if($user->isAdmin(), 422, 'Administrators cannot be suspended.');

        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason'] ?? null])->save();
        $this->log($request, 'suspend', $user, $data['reason'] ?? null);
        $user->notify(new AccountSuspended($data['reason'] ?? null));

        return back()->with('status', "{$user->name} has been suspended.");
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        $this->log($request, 'unsuspend', $user);
        $user->notify(new AccountReinstated);

        return back()->with('status', "{$user->name} has been reinstated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:' . $user->username]], [
            'confirm.in' => 'Type the username exactly to confirm deletion.',
        ]);
        abort_if($user->isAdmin(), 422, 'Administrators cannot be deleted here.');

        $this->log($request, 'delete', $user);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    private function log(Request $request, string $action, User $user, ?string $reason = null): void
    {
        ModerationAction::create([
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'action' => $action,
            'reason' => $reason,
        ]);
    }
}
