<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the two-factor challenge after a password login.
 *
 * A user who has confirmed 2FA (two_factor_confirmed_at) must complete the TOTP
 * / recovery-code challenge — which sets the session flag two_factor_confirmed —
 * before reaching any protected route. The challenge, verify, and logout routes
 * are exempt so the user can complete or abandon the challenge.
 */
class EnsureTwoFactorChallenged
{
    private const EXEMPT = ['two-factor.challenge', 'two-factor.verify', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->two_factor_confirmed_at
            && ! $request->session()->get('two_factor_confirmed')
            && ! in_array($request->route()?->getName(), self::EXEMPT, true)
        ) {
            if ($request->expectsJson()) {
                abort(423, 'Two-factor authentication challenge required.');
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
