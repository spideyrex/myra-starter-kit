<?php

namespace App\Admin\Plugin;

/**
 * One class, one abstract, one required method. Everything a plugin contributes
 * is declared in the Manifest — there is no per-panel registration call.
 */
abstract class MyraPlugin
{
    /** Globally unique, kebab-case. A duplicate id is a registration failure. */
    abstract public static function id(): string;

    abstract public function manifest(Manifest $manifest): Manifest;

    /** Runs after every plugin's manifest has been applied. */
    public function boot(): void {}

    /** Per-install config with zero accessor code. */
    final public static function config(string $key, mixed $default = null): mixed
    {
        return config('myra.plugin_config.'.static::id().'.'.$key, $default);
    }
}
