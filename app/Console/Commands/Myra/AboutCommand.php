<?php

namespace App\Console\Commands\Myra;

use App\Admin\Plugin\PluginRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only status / doctor command summarising the Myra install — version,
 * RBAC modules, settings groups, scaffolding commands, plugins, and counts.
 */
class AboutCommand extends Command
{
    protected $signature = 'myra:about';

    protected $description = 'Show Myra framework version, RBAC modules, settings groups, plugins, and install status';

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

        $generators = array_values(array_filter(
            array_keys(Artisan::all()),
            fn (string $name) => str_starts_with($name, 'make:myra-'),
        ));
        sort($generators);
        $this->components->twoColumnDetail('<fg=green>Generators</>', (string) count($generators));
        $this->components->twoColumnDetail('  commands', implode(', ', $generators) ?: '—');

        $this->renderPlugins();
        $this->renderOptionalBundles();

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

    private function renderPlugins(): void
    {
        $manifests = PluginRegistry::manifests();
        $failed = PluginRegistry::failed();

        $this->components->twoColumnDetail(
            '<fg=green>Plugins</>',
            count($manifests) . ' loaded' . (count($failed) ? ', <fg=red>' . count($failed) . ' failed</>' : '')
                . (PluginRegistry::strict() ? ' <fg=gray>(strict)</>' : ''),
        );

        foreach ($manifests as $id => $manifest) {
            $surface = $manifest->toArray();
            $plugin = PluginRegistry::get($id);
            $this->components->twoColumnDetail("  <fg=cyan>{$id}</>", $plugin ? $plugin::class : '—');
            $this->components->twoColumnDetail('    surface', sprintf(
                'permissions %d · reports %d · imports %d · routes %d · nav %d · migrations %d · commands %d',
                count($surface['permissions']),
                count($surface['reports']),
                count($surface['imports']),
                $surface['routeGroups'] + $surface['publicRouteGroups'],
                count($surface['nav']),
                count($surface['migrations']),
                count($surface['commands']),
            ));
        }

        foreach ($failed as $class => $exception) {
            $this->components->twoColumnDetail("  <fg=red>{$class}</>", '<fg=red>' . $exception->getMessage() . '</>');
        }
    }

    /** Sections that light up only when the other v2.4 bundles are present. */
    private function renderOptionalBundles(): void
    {
        if (config()->has('myra.tenancy')) {
            $enabled = (bool) config('myra.tenancy.enabled');
            $this->components->twoColumnDetail(
                '<fg=green>Tenancy</>',
                ($enabled ? '<fg=yellow>enabled</>' : 'disabled') . ' · ' . count((array) config('myra.tenancy.models', [])) . ' models',
            );
        }

        if (class_exists(\App\Admin\Navigation\NavRegistry::class)) {
            $this->components->twoColumnDetail(
                '<fg=green>Clusters</>',
                count((array) config('myra.clusters', [])) . ' registered',
            );
        }

        if (config()->has('myra.performance')) {
            $this->components->twoColumnDetail('<fg=green>Scale</>', sprintf(
                'stable_sort %s · virtualise above %s rows',
                config('myra.performance.stable_sort') ? 'on' : 'off',
                (string) config('myra.performance.virtualize_above', '—'),
            ));
        }
    }
}
