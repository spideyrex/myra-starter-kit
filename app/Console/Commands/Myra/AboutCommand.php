<?php

namespace App\Console\Commands\Myra;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only status / doctor command summarising the Myra install — version,
 * RBAC modules, settings groups, scaffolding commands, and record counts.
 */
class AboutCommand extends Command
{
    protected $signature = 'myra:about';

    protected $description = 'Show Myra framework version, RBAC modules, settings groups, and install status';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=magenta;options=bold>Myra Framework</> <fg=gray>v' . config('myra.version', 'dev') . '</>');
        $this->newLine();

        $modules = array_keys(config('shield.modules', []));
        $this->components->twoColumnDetail('<fg=green>Shield RBAC</>', count($modules) . ' modules');
        $this->components->twoColumnDetail('  modules', implode(', ', $modules) ?: '—');

        $components = array_keys(config('myra.components', []));
        $this->components->twoColumnDetail('<fg=green>Component keywords</>', count($components) . ' (make:myra-component)');

        $this->components->twoColumnDetail('<fg=green>Generators</>', 'page, resource, component, setting, export, import, policy, user');

        // DB-backed details (guarded so it works pre-migration).
        try {
            if (Schema::hasTable('settings')) {
                $groups = DB::table('settings')->distinct()->pluck('group');
                $this->components->twoColumnDetail('<fg=green>Settings groups</>', $groups->implode(', ') ?: '—');
            }
            if (Schema::hasTable('roles')) {
                $this->components->twoColumnDetail('<fg=green>Roles</>', (string) DB::table('roles')->count());
            }
            if (Schema::hasTable('permissions')) {
                $this->components->twoColumnDetail('<fg=green>Permissions</>', (string) DB::table('permissions')->count());
            }
            if (Schema::hasTable('users')) {
                $this->components->twoColumnDetail('<fg=green>Users</>', (string) DB::table('users')->count());
            }
        } catch (\Throwable $e) {
            $this->components->warn('Database not reachable: ' . $e->getMessage());
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
