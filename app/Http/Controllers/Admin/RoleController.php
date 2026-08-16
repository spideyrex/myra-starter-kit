<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $isSuper = $this->isSuperAdmin($request);

        $roles = Role::withCount('users')
            ->with('permissions')
            // Non-super-admins never see the super-admin role or any hidden role.
            ->when(! $isSuper, fn ($q) => $q
                ->where('name', '!=', config('shield.super_admin_role', 'super-admin'))
                ->where('visible', true))
            // >>> MYRA v2.7 [C] START
            // Highest priority first; ties broken by id so the order is the same
            // one the dashboard resolver walks.
            ->orderByDesc('priority')
            ->orderBy('id')
            // <<< MYRA v2.7 [C] END
            ->get();

        // >>> MYRA v2.7 [C] START
        $rolesWithDashboard = Schema::hasTable('role_dashboards')
            ? array_map('intval', DB::table('role_dashboards')->distinct()->pluck('role_id')->all())
            : [];
        // <<< MYRA v2.7 [C] END

        $allPermissions = Permission::all();

        // Permissions grouped by module (for matrix rows)
        $permissionMatrix = $allPermissions->groupBy(function ($permission) {
            return explode('.', $permission->name)[0] ?? 'general';
        })->map(fn ($perms) => $perms->pluck('name')->sort()->values());

        // Role names for matrix columns
        $roleNames = $roles->pluck('name');

        // Mapping of role name → array of permission names
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->name] = $role->permissions->pluck('name')->toArray();
        }

        // Total unique users that have at least one role
        $totalUsersWithRoles = \App\Models\User::whereHas('roles')->count();

        $rolesData = $roles->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'users_count' => $role->users_count,
            'permissions' => $role->permissions->pluck('name'),
            'is_active' => $role->is_active,
            'visible' => $role->visible,
            'is_locked' => $role->isLocked(),
            'is_privileged' => $role->isPrivileged(),
            // >>> MYRA v2.7 [C] START
            'priority' => (int) ($role->priority ?? 0),
            'has_dashboard' => in_array((int) $role->id, $rolesWithDashboard, true),
            // <<< MYRA v2.7 [C] END
            'created_at' => $role->created_at->toDateTimeString(),
        ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $rolesData,
            'permissionMatrix' => $permissionMatrix,
            'roleNames' => $roleNames,
            'rolePermissions' => $rolePermissions,
            'totalUsersWithRoles' => $totalUsersWithRoles,
            'totalPermissions' => $allPermissions->count(),
            'isSuperAdmin' => $isSuper,
            // >>> MYRA v2.7 [C] START
            'canManageRoleDashboards' => $request->user()?->can('dashboard.manage-roles') ?? false,
            // <<< MYRA v2.7 [C] END
        ]);
    }

    // >>> MYRA v2.7 [C] START
    /**
     * Assign priority from an ordered id list, highest first. Gaps of 10 so a
     * later insertion does not force a renumber.
     */
    public function reorder(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $count = count($ids);

        DB::transaction(function () use ($ids, $count) {
            foreach ($ids as $index => $id) {
                Role::whereKey($id)->update(['priority' => ($count - $index) * 10]);
            }
        });

        return back()->with('success', 'Role priority updated.');
    }
    // <<< MYRA v2.7 [C] END

    public function toggleActive(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403);

        if ($role->isLocked()) {
            return back()->with('error', 'The super-admin role cannot be disabled.');
        }

        $role->update(['is_active' => ! $role->is_active]);

        return back()->with('success', "Role \"{$role->name}\" " . ($role->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function toggleVisible(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403);

        if ($role->isLocked()) {
            return back()->with('error', 'The super-admin role cannot be hidden.');
        }

        $role->update(['visible' => ! $role->visible]);

        return back()->with('success', "Role \"{$role->name}\" " . ($role->visible ? 'shown' : 'hidden') . '.');
    }

    private function isSuperAdmin(Request $request): bool
    {
        return $request->user()?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false;
    }

    public function create(): Response
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0] ?? 'general';
        });

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'permissionGroups' => $permissions->map(fn ($perms) => $perms->pluck('name')),
        ]);
    }

    public function edit(Role $role): Response
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0] ?? 'general';
        });

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => $permissions->map(fn ($perms) => $perms->pluck('name')),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super-admin') {
            return redirect()->route('admin.roles.index')->with('error', 'The super-admin role cannot be modified.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function clone(Role $role): RedirectResponse
    {
        $baseName = $role->name . '-copy';
        $name = $baseName;
        $suffix = 1;

        while (Role::where('name', $name)->exists()) {
            $name = $baseName . '-' . $suffix;
            $suffix++;
        }

        $newRole = Role::create(['name' => $name, 'guard_name' => $role->guard_name]);
        $newRole->syncPermissions($role->permissions);

        return redirect()->route('admin.roles.edit', $newRole->id)->with('success', "Role cloned as \"{$name}\".");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return back()->with('error', 'Cannot delete system roles.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
}
