<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict permissions/features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Enforce HTTPS + Content-Security-Policy (production only, so Vite HMR
        // works locally). 'unsafe-inline' for scripts is required by Ziggy's
        // @routes block; styles are injected at runtime by Vue. https:/wss: in
        // connect-src cover Firebase, Pusher/Reverb, and the AI providers.
        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

            if (! $response->headers->has('Content-Security-Policy')) {
                $response->headers->set('Content-Security-Policy', implode('; ', [
                    "default-src 'self'",
                    "script-src 'self' 'unsafe-inline'",
                    "style-src 'self' 'unsafe-inline'",
                    "img-src 'self' data: https: blob:",
                    "font-src 'self' data:",
                    "connect-src 'self' https: wss:",
                    // MapLibre GL parses tiles in a blob: web worker; without these
                    // it falls back to default-src and the map renders blank.
                    "worker-src 'self' blob:",
                    "child-src 'self' blob:",
                    "object-src 'none'",
                    "base-uri 'self'",
                    "frame-ancestors 'self'",
                    "form-action 'self'",
                ]));
            }
        }

        return $response;
    }
}
