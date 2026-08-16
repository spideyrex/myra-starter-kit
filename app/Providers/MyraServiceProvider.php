<?php

namespace App\Providers;

use App\Admin\Plugin\PluginRegistry;
use App\Support\Myra;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Applies every plugin manifest. A plugin that throws is quarantined, not
 * fatal, unless `myra.extensions.strict` resolves true.
 */
class MyraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        PluginRegistry::load();

        foreach (PluginRegistry::manifests() as $manifest) {
            // Deep merge: mergeConfigFrom() cannot be used, shield.modules is nested.
            config(['shield.modules' => array_replace_recursive(
                (array) config('shield.modules', []),
                $manifest->permissionModules(),
            )]);

            config(['myra.reports.definitions' => array_merge(
                (array) config('myra.reports.definitions', []),
                $manifest->reportDefinitions(),
            )]);

            config(['myra.imports.resources' => array_merge(
                (array) config('myra.imports.resources', []),
                $manifest->importResources(),
            )]);
        }

        // >>> MYRA v2.7 [C] START
        // No merge here on purpose. SectionRegistry::seed() already spreads
        // myra.pagebuilder_extra.extra_sections alongside the core list, so
        // folding it into myra.pagebuilder.sections counted every extra twice.
        // <<< MYRA v2.7 [C] END
    }

    public function boot(): void
    {
        foreach (PluginRegistry::manifests() as $id => $manifest) {
            try {
                foreach ($manifest->migrationPaths() as $path) {
                    $this->loadMigrationsFrom($path);
                }

                foreach ($manifest->translationPaths() as $translation) {
                    $this->loadJsonTranslationsFrom($translation['path']);
                }

                foreach ($manifest->policyMap() as $model => $policy) {
                    Gate::policy($model, $policy);
                }

                if ($this->app->runningInConsole() && $manifest->commandClasses()) {
                    $this->commands($manifest->commandClasses());
                }

                foreach ($manifest->routeCallbacks() as $callback) {
                    Myra::adminRoutes($callback);
                }

                foreach ($manifest->publicRouteCallbacks() as $callback) {
                    Myra::publicRoutes($callback);
                }

                // OPTIONAL cross-bundle hook: the navigation bundle may not be present.
                if (class_exists(\App\Admin\Navigation\NavRegistry::class)) {
                    foreach ($manifest->navItemArrays() as $item) {
                        \App\Admin\Navigation\NavRegistry::addRaw(
                            $item['group'] ?? config('myra.nav_group', 'Custom'),
                            $item,
                            $id,
                        );
                    }
                }
            } catch (Throwable $e) {
                PluginRegistry::recordFailure($id, $e);
                report($e);

                if (PluginRegistry::strict()) {
                    throw $e;
                }
            }
        }

        foreach (PluginRegistry::loaded() as $plugin) {
            try {
                $plugin->boot();
            } catch (Throwable $e) {
                report($e);

                if (PluginRegistry::strict()) {
                    throw $e;
                }
            }
        }
    }
}
