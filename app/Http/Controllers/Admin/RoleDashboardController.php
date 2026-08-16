<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\LayoutShape;
use App\Admin\Dashboard\RolePrincipal;
use App\Admin\Dashboard\StaticWidgetRegistry;
use App\Admin\Dashboard\WidgetInstance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleDashboardRequest;
use App\Models\Role;
use App\Models\RoleDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// >>> MYRA v2.7 [B] START
/**
 * Authoring surface for the per-role default dashboard.
 *
 * The write path resolves every instance against the TARGET ROLE's principal,
 * never the author's, so a role dashboard is structurally incapable of being
 * authored containing a widget that role cannot see. The render-time per-viewer
 * filter is a second, independent line of defence.
 */
class RoleDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RoleDashboard::class);

        $rows = RoleDashboard::query()
            ->where('dashboard_key', DashboardKey::MAIN)
            ->get()
            ->keyBy('role_id');

        $roles = Role::query()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/RoleDashboards/Index', [
            'roles' => $roles->map(function (Role $role) use ($rows) {
                $row = $rows->get($role->id);

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'priority' => (int) $role->priority,
                    'is_active' => (bool) $role->is_active,
                    'is_locked' => $role->isLocked(),
                    'configured' => $row !== null,
                    'widget_count' => self::widgetCount($row?->payload),
                    'updated_at' => $row?->updated_at?->toISOString(),
                ];
            })->values(),
            'dashboardKey' => DashboardKey::MAIN,
            'isSuperAdmin' => (bool) $request->user()?->hasRole(config('shield.super_admin_role', 'super-admin')),
        ]);
    }

    public function update(RoleDashboardRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('manage', RoleDashboard::class);

        $data = $request->validated();

        abort_unless(DashboardKey::allows($data['dashboard_key']), 404);
        $this->abortIfLocked($request, $role);

        $payload = LayoutShape::assert($data['payload'], 'payload');
        $principal = new RolePrincipal($role);

        // Role-correct, not author-correct: the super-admin bypass never applies.
        $payload['instances'] = array_values(array_filter(
            $payload['instances'],
            fn (array $i) => WidgetInstance::resolve($i, $principal) !== null,
        ));

        $allowed = array_merge(
            array_column($payload['instances'], 'key'),
            StaticWidgetRegistry::visibleTo($principal),
        );

        $payload['entries'] = array_values(array_filter(
            $payload['entries'],
            fn (array $e) => in_array($e['key'], $allowed, true),
        ));

        // Ownership is assigned outside mass assignment.
        DB::transaction(function () use ($role, $data, $payload) {
            $row = RoleDashboard::firstOrNew([
                'role_id' => $role->id,
                'dashboard_key' => $data['dashboard_key'],
            ]);

            $row->role_id = $role->id;
            $row->dashboard_key = $data['dashboard_key'];
            $row->payload = $payload;
            $row->save();
        });

        return back()->with('success', __('roleDashboardAdmin.saved', ['role' => $role->name]));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', RoleDashboard::class);

        $this->abortIfLocked($request, $role);

        RoleDashboard::query()
            ->where('role_id', $role->id)
            ->where('dashboard_key', DashboardKey::MAIN)
            ->delete();

        return back()->with('success', __('roleDashboardAdmin.cleared', ['role' => $role->name]));
    }

    /** The locked role's dashboard is authored by a super-admin only. */
    private function abortIfLocked(Request $request, Role $role): void
    {
        abort_if(
            $role->isLocked() && ! $request->user()?->hasRole(config('shield.super_admin_role', 'super-admin')),
            403,
        );
    }

    /** Tiles a member of the role would actually see: visible entries plus added widgets. */
    public static function widgetCount(mixed $payload): int
    {
        if (! is_array($payload)) {
            return 0;
        }

        $entries = $payload['entries'] ?? [];

        if (! is_array($entries)) {
            return 0;
        }

        return count(array_filter(
            $entries,
            fn ($entry) => is_array($entry) && ($entry['hidden'] ?? false) !== true,
        ));
    }
}
// <<< MYRA v2.7 [B] END
