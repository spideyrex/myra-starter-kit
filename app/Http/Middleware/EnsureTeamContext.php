<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->current_team_id && $user->teams()->exists()) {
            $user->update([
                'current_team_id' => $user->teams()->first()->id,
            ]);
            $user->refresh();
        }

        return $next($request);
    }
}
