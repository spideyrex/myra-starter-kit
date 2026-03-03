<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::with('roles')->get()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0] ?? 'general';
        });

        return Inertia::render('Admin/Permissions/Index', [
            'permissionGroups' => $permissions->map(fn ($perms) => $perms->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'roles' => $p->roles->pluck('name')->toArray(),
                'created_at' => $p->created_at->toDateTimeString(),
            ])->values()),
        ]);
    }
}
