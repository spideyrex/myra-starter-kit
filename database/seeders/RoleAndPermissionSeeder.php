<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permissions
            'permissions.view',

            // Settings
            'settings.view',
            'settings.edit',

            // Email
            'email.view',
            'email.create',
            'email.edit',
            'email.delete',

            // Activity Log
            'activity-log.view',

            // Media
            'media.view',
            'media.create',
            'media.delete',

            // System Health
            'system-health.view',

            // Backups
            'backups.view',
            'backups.create',
            'backups.delete',

            // API Tokens
            'api-tokens.view',
            'api-tokens.create',
            'api-tokens.delete',

            // Notifications
            'notifications.view',
            'notifications.create',

            // Firebase
            'firebase.view',
            'firebase.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin - gets all via Gate::before
        Role::firstOrCreate(['name' => 'super-admin']);

        // Admin - all permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        // Manager - most permissions except system
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'users.view', 'users.create', 'users.edit',
            'roles.view',
            'permissions.view',
            'email.view', 'email.create', 'email.edit',
            'activity-log.view',
            'media.view', 'media.create',
            'notifications.view', 'notifications.create',
            'firebase.view',
        ]);

        // Editor - content permissions
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'media.view', 'media.create',
            'email.view',
        ]);

        // Viewer - read-only
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([
            'users.view',
            'media.view',
        ]);
    }
}
