<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class ShieldGenerateCommand extends Command
{
    protected $signature = 'shield:generate';

    protected $description = 'Sync Spatie permissions from config/shield.php (one per module ability) and grant them all to the admin role';

    public function handle(): int
    {
        $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);
        $permissionModel = config('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        $guard = config('auth.defaults.guard', 'web');

        $modules = config('shield.modules', []);
        $created = 0;
        $all = [];

        foreach ($modules as $module => $abilities) {
            foreach ($abilities as $ability) {
                $name = "{$module}.{$ability}";
                $all[] = $name;

                $permission = $permissionModel::firstOrCreate(
                    ['name' => $name, 'guard_name' => $guard]
                );

                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        // Ensure the privileged roles exist; grant the admin role every permission.
        $roleModel::firstOrCreate(['name' => config('shield.super_admin_role', 'super-admin'), 'guard_name' => $guard]);
        $admin = $roleModel::firstOrCreate(['name' => config('shield.admin_role', 'admin'), 'guard_name' => $guard]);
        $admin->givePermissionTo($all);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->components->info(sprintf(
            'Shield synced: %d permissions across %d modules (%d newly created). Admin role now has all %d.',
            count($all),
            count($modules),
            $created,
            count($all),
        ));

        return self::SUCCESS;
    }
}
