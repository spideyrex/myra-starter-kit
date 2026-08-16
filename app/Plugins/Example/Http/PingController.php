<?php

namespace App\Plugins\Example\Http;

use Illuminate\Http\JsonResponse;

/** Invokable so the route stays serialisable for `route:cache`. */
class PingController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['pong' => true]);
    }
}
