<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convention-based permission gate.
 *
 * Derives the required permission from the route name. A route named
 * "admin.{prefix}.{action}" maps to permission "{prefix}.{ability}" where the
 * action segment is normalised: index/show → view, create/store → create,
 * edit/update → edit, destroy/force-delete/bulk-action → delete. Any other
 * action falls back to "view".
 *
 * Intended for pages scaffolded by the make:myra-* commands, whose route name
 * prefix matches their permission module. Existing modules whose route name
 * differs from their permission prefix keep their explicit permission: gate.
 */
class ShieldPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if ($name) {
            $permission = $this->permissionFor($name);

            if ($permission && ! $request->user()?->can($permission)) {
                abort(403);
            }
        }

        return $next($request);
    }

    private function permissionFor(string $routeName): ?string
    {
        // Drop a leading "admin." prefix if present.
        $name = Str::startsWith($routeName, 'admin.') ? Str::after($routeName, 'admin.') : $routeName;

        $segments = explode('.', $name);
        if (count($segments) < 2) {
            return null;
        }

        $action = array_pop($segments);
        $prefix = implode('.', $segments);

        return "{$prefix}.{$this->ability($action)}";
    }

    private function ability(string $action): string
    {
        return match ($action) {
            'create', 'store' => 'create',
            'edit', 'update' => 'edit',
            'destroy', 'force-delete', 'bulk-action' => 'delete',
            default => 'view',
        };
    }
}
