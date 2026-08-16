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
            // >>> MYRA v2.5 [A] START
            'dashboard.customise',
            // <<< MYRA v2.5 [A] END

            // Firebase
            'firebase.view',
            'firebase.edit',

            // Pages
            'pages.view',
            'pages.create',
            'pages.edit',
            'pages.delete',

            // Articles
            'articles.view',
            'articles.create',
            'articles.edit',
            'articles.delete',
            // >>> MYRA v2.5 [D] START
            // AI surfaces. `ai.use` is the back-compat gate for ai/assist and is
            // granted to manager/editor below so myra.ai.gate_assist is safe to
            // flip later. The array_diff on the admin role lands all four on
            // admin and super-admin automatically.
            'ai.filter',
            'ai.schema',
            'ai.summarise',
            'ai.use',
            // <<< MYRA v2.5 [D] END

            // Categories
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            // >>> MYRA v2.3 [B] START
            // Reports. `reports.schedule.external` authorises mailing arbitrary
            // addresses, so it stays a super-admin-only ability.
            'reports.view',
            'reports.export',
            'reports.schedule',
            'reports.schedule.external',
            // <<< MYRA v2.3 [B] END
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin - gets all via Gate::before
        Role::firstOrCreate(['name' => 'super-admin']);

        // Admin - all permissions except the external-recipient escalation
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(array_values(array_diff($permissions, ['reports.schedule.external'])));

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
            'pages.view', 'pages.create', 'pages.edit',
            'articles.view', 'articles.create', 'articles.edit',
            'categories.view', 'categories.create', 'categories.edit',
            'reports.view', 'reports.export', 'reports.schedule',
            'ai.use', // >>> MYRA v2.5 [D] START <<< MYRA v2.5 [D] END
        ]);

        // Editor - content permissions
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'media.view', 'media.create',
            'email.view',
            'pages.view', 'pages.create', 'pages.edit',
            'articles.view', 'articles.create', 'articles.edit',
            'categories.view', 'categories.create',
            'ai.use', // >>> MYRA v2.5 [D] START <<< MYRA v2.5 [D] END
        ]);

        // Viewer - read-only
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions([
            'users.view',
            'media.view',
            'pages.view',
            'articles.view',
            'categories.view',
        ]);
    }
}
