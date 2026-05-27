<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the public registration routes when sign-up is disabled in
 * Settings → General. GET requests redirect to login with a message; POST
 * requests are aborted with 403.
 */
class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = true;

        try {
            $enabled = app(GeneralSettings::class)->registration_enabled;
        } catch (\Throwable) {
            // Setting missing (pre-migration) — fail open so install isn't blocked.
        }

        if (! $enabled) {
            if ($request->isMethod('get')) {
                return redirect()->route('login')->with('error', 'Registration is currently disabled.');
            }

            abort(403, 'Registration is currently disabled.');
        }

        return $next($request);
    }
}
