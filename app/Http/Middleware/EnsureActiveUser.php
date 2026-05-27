<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out and blocks any authenticated user whose account is no longer active
 * (suspended or pending), so account state changes take effect immediately on
 * the next request rather than only at the next login.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'Your account is not active.');
            }

            return redirect()->route('login')->with('error', 'Your account is not active. Please contact an administrator.');
        }

        return $next($request);
    }
}
