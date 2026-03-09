<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->with('permissions')
            ->get();

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
            'created_at' => $role->created_at->toDateTimeString(),
        ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $rolesData,
            'permissionMatrix' => $permissionMatrix,
            'roleNames' => $roleNames,
            'rolePermissions' => $rolePermissions,
            'totalUsersWithRoles' => $totalUsersWithRoles,
            'totalPermissions' => $allPermissions->count(),
        ]);
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
