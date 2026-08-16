<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Privileged roles
    |--------------------------------------------------------------------------
    |
    | "super_admin_role" bypasses every permission check via the Gate::before
    | callback in AppServiceProvider. "privileged_roles" may only be assigned
    | to a user by a super-admin (enforced in UserService).
    |
    */

    'super_admin_role' => 'super-admin',
    'admin_role' => 'admin',
    'privileged_roles' => ['super-admin', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Module → ability map
    |--------------------------------------------------------------------------
    |
    | `php artisan shield:generate` creates a "{module}.{ability}" permission
    | for every entry below, ensures the super-admin/admin roles exist, and
    | grants every permission to the admin role. Scaffolding commands append
    | new modules at the `myra:modules` marker so re-running shield:generate
    | keeps generated permissions intact.
    |
    */

    'modules' => [
        'users' => ['view', 'create', 'edit', 'delete'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'permissions' => ['view'],
        'settings' => ['view', 'edit'],
        'email' => ['view', 'create', 'edit', 'delete'],
        'activity-log' => ['view'],
        'media' => ['view', 'create', 'delete'],
        'system-health' => ['view'],
        'backups' => ['view', 'create', 'delete'],
        'api-tokens' => ['view', 'create', 'delete'],
        'notifications' => ['view', 'create'],
        'firebase' => ['view', 'edit'],
        'pages' => ['view', 'create', 'edit', 'delete'],
        'articles' => ['view', 'create', 'edit', 'delete'],
        'categories' => ['view', 'create', 'edit', 'delete'],
        'demo' => ['view'],
        'search' => ['view'],
        // >>> MYRA v2.3 [B] START
        'reports' => ['view', 'export', 'schedule'],
        // <<< MYRA v2.3 [B] END
        // >>> MYRA v2.6 [A] START
        'blocks' => ['view'],
        // <<< MYRA v2.6 [A] END
        // >>> MYRA v2.6 [B] START
        'examples' => ['view'],
        // <<< MYRA v2.6 [B] END
        // >>> MYRA v2.6 [C] START
        'brand' => ['view', 'update'],
        // <<< MYRA v2.6 [C] END
        /* myra:modules */
    ],

];
