<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs suspended users out on their next request.
 */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended.' . ($user->suspension_reason ? " Reason: {$user->suspension_reason}" : ''),
            ]);
        }

        return $next($request);
    }
}
