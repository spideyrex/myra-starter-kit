<?php

namespace App\Admin\Dashboard;

use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * THE resolution chain: personal row → highest-priority role default → nothing.
 *
 * Deliberately UNCACHED. Losing a role, deactivating a role and deleting a role
 * all correct themselves on the very next request because entitlement is
 * re-derived from model_has_roles every time.
 */
final class LayoutSource
{
    public static function resolve(?Authenticatable $user, string $key): ResolvedLayout
    {
        if (! DashboardKey::allows($key) || $user === null) {
            return ResolvedLayout::none();
        }

        $roleRow = self::roleDefault($user, $key);
        $roleName = $roleRow['role'] ?? null;
        $hasRoleDefault = $roleRow !== null;

        if ($user instanceof User) {
            $personal = DashboardLayout::forUser($user)
                ->where('dashboard_key', $key)
                ->first();

            // A personal row wins UNCONDITIONALLY — including one whose entries
            // are all hidden. "I want an empty dashboard" is a personalisation.
            if ($personal !== null) {
                return ResolvedLayout::personal(
                    is_array($personal->payload) ? $personal->payload : [],
                    $roleName,
                    $hasRoleDefault,
                );
            }
        }

        if ($roleRow !== null) {
            return ResolvedLayout::role($roleRow['payload'], (string) $roleName);
        }

        return ResolvedLayout::none();
    }

    /**
     * One indexed query, ordering in SQL. The pivot is polymorphic, so the
     * model_type predicate is REQUIRED.
     *
     * @return array{payload:array,role:string}|null
     */
    private static function roleDefault(Authenticatable $user, string $key): ?array
    {
        if (! method_exists($user, 'getMorphClass')) {
            return null;
        }

        $tables = (array) config('permission.table_names', []);
        $roles = (string) ($tables['roles'] ?? 'roles');
        $pivot = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $rolePivotKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $guard = (string) config('auth.defaults.guard', 'web');

        try {
            $row = DB::table('role_dashboards')
                ->join($roles, "{$roles}.id", '=', 'role_dashboards.role_id')
                ->join($pivot, "{$pivot}.{$rolePivotKey}", '=', "{$roles}.id")
                ->where("{$pivot}.model_type", $user->getMorphClass())
                ->where("{$pivot}.{$morphKey}", $user->getAuthIdentifier())
                ->where("{$roles}.guard_name", $guard)
                ->where("{$roles}.is_active", true)
                ->where('role_dashboards.dashboard_key', $key)
                ->orderByDesc("{$roles}.priority")
                ->orderBy("{$roles}.id")
                ->limit(1)
                ->select([
                    'role_dashboards.payload as payload',
                    "{$roles}.name as role_name",
                ])
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if ($row === null) {
            return null;
        }

        $payload = is_string($row->payload) ? json_decode($row->payload, true) : $row->payload;

        if (! is_array($payload)) {
            return null;
        }

        return ['payload' => $payload, 'role' => (string) $row->role_name];
    }
}
