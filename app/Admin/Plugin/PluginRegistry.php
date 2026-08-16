<?php

namespace App\Admin\Plugin;

use App\Admin\Plugin\Exceptions\PluginException;
use App\Support\Myra;
use Throwable;

/**
 * Loads the EXPLICIT plugin list from config. There is no composer
 * auto-discovery: a plugin registers routes inside the admin middleware stack
 * and merges RBAC modules, so installing a package must never be enough.
 */
final class PluginRegistry
{
    /** @var array<string,MyraPlugin> */
    private static array $loaded = [];

    /** @var array<string,Manifest> */
    private static array $manifests = [];

    /** @var array<string,Throwable> */
    private static array $failed = [];

    private static bool $booted = false;

    /** @return array<int,class-string<MyraPlugin>> */
    public static function declared(): array
    {
        return array_values((array) config('myra.extensions.plugins', []));
    }

    /**
     * OFF by default in every environment: a failing plugin is quarantined, not
     * fatal. Opt in explicitly (MYRA_PLUGINS_STRICT=true) to rethrow instead.
     * Resolved at read time so a test may flip it without rebooting the app.
     */
    public static function strict(): bool
    {
        return filter_var(config('myra.extensions.strict', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string,MyraPlugin> */
    public static function loaded(): array
    {
        return self::$loaded;
    }

    /** @return array<string,Manifest> */
    public static function manifests(): array
    {
        return self::$manifests;
    }

    /** @return array<string,Throwable> class-string (or id) => exception */
    public static function failed(): array
    {
        return self::$failed;
    }

    public static function has(string $id): bool
    {
        return isset(self::$loaded[$id]);
    }

    public static function get(string $id): ?MyraPlugin
    {
        return self::$loaded[$id] ?? null;
    }

    public static function recordFailure(string $id, Throwable $e): void
    {
        self::$failed[$id] = $e;
    }

    /** Called from MyraServiceProvider::register(). Idempotent. */
    public static function load(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        foreach (self::declared() as $class) {
            try {
                if (! is_string($class) || ! class_exists($class)) {
                    throw new PluginException('Plugin class ['.var_export($class, true).'] not found.');
                }

                $plugin = app($class);

                if (! $plugin instanceof MyraPlugin) {
                    throw new PluginException("[{$class}] does not extend MyraPlugin.");
                }

                $id = $class::id();

                if (isset(self::$loaded[$id])) {
                    throw new PluginException("Duplicate plugin id [{$id}].");
                }

                $manifest = $plugin->manifest(Manifest::make($id));

                if ($manifest->minVersion() && version_compare(Myra::version(), $manifest->minVersion(), '<')) {
                    throw new PluginException("[{$id}] requires Myra >= {$manifest->minVersion()}.");
                }

                self::$loaded[$id] = $plugin;
                self::$manifests[$id] = $manifest;
            } catch (Throwable $e) {
                self::$failed[is_string($class) ? $class : gettype($class)] = $e;
                report($e);

                if (self::strict()) {
                    throw $e;
                }
            }
        }
    }

    /** Tests only. */
    public static function flush(): void
    {
        self::$loaded = [];
        self::$manifests = [];
        self::$failed = [];
        self::$booted = false;
    }
}
