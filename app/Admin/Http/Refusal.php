<?php

namespace App\Admin\Http;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
// Symfony's Response, not Illuminate's: a JsonResponse is not an Illuminate\Http\Response.
use Symfony\Component\HttpFoundation\Response;

/**
 * A refusal must not reach the exception handler: the debug renderer returns a
 * full HTML page carrying request/user context — a leak, and unparseable to an
 * XHR caller that expected JSON.
 */
final class Refusal
{
    public static function respond(
        Request $request,
        string $message,
        array $extra = [],
        int $status = 422,
    ): Response {
        $response = ($request->expectsJson() || $request->ajax())
            ? response()->json(['message' => $message] + $extra, $status)
            : response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);

        return $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function throw(
        Request $request,
        string $message,
        array $extra = [],
        int $status = 422,
    ): never {
        throw new HttpResponseException(self::respond($request, $message, $extra, $status));
    }
}
