<?php

namespace App\Support;

use App\Admin\Plugin\MyraPlugin;
use App\Admin\Plugin\PluginRegistry;
use Illuminate\Support\Facades\Route;

final class Myra
{
    /**
     * The admin stack, mirroring the literal at routes/web.php's admin group.
     * 'web' is included because plugin routes register from a provider, outside
     * withRouting(web:). AdminMiddlewareParityTest proves this matches the live
     * route table and fails CI if the two drift.
     */
    public const ADMIN_MIDDLEWARE = ['web', 'auth', 'verified', 'active', '2fa'];

    public static function version(): string
    {
        return (string) config('myra.version', 'dev');
    }

    /** Register routes into the admin group without re-declaring the stack. */
    public static function adminRoutes(callable $routes): void
    {
        Route::middleware(self::ADMIN_MIDDLEWARE)
            ->prefix('admin')
            ->name('admin.')
            ->group($routes);
    }

    /** Public (non-admin) plugin routes: 'web' only. */
    public static function publicRoutes(callable $routes): void
    {
        Route::middleware(['web'])->group($routes);
    }

    public static function plugin(string $id): ?MyraPlugin
    {
        return PluginRegistry::get($id);
    }

    public static function hasPlugin(string $idOrClass): bool
    {
        if (PluginRegistry::has($idOrClass)) {
            return true;
        }

        foreach (PluginRegistry::loaded() as $plugin) {
            if ($plugin::class === $idOrClass) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,\Throwable> class-string => exception */
    public static function failedPlugins(): array
    {
        return PluginRegistry::failed();
    }
}
