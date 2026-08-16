<?php

namespace App\Appearance;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;

/**
 * The single source of truth for the guest-page layouts.
 *
 * A signature-for-signature mirror of App\Homepage\TemplateRegistry: same
 * declaration ordering, same idempotent seed, same forget() semantics that
 * deliberately leave the seeded flag set, same flush().
 */
final class AuthLayoutRegistry
{
    public const FALLBACK = 'split';

    /** @var array<string,array{order:int,source:string,layout:AuthLayout}> */
    private static array $layouts = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    public static function add(AuthLayout $l, string $source = 'core'): void
    {
        self::$layouts[$l->key()] = [
            'order' => self::$layouts[$l->key()]['order'] ?? self::$seq++,
            'source' => $source,
            'layout' => $l,
        ];
    }

    public static function has(string $key): bool
    {
        self::seed();

        return isset(self::$layouts[$key]);
    }

    public static function get(string $key): ?AuthLayout
    {
        self::seed();

        return self::$layouts[$key]['layout'] ?? null;
    }

    /** @return array<int,AuthLayout> declaration-ordered */
    public static function all(): array
    {
        self::seed();

        $rows = array_values(self::$layouts);
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn (array $row) => $row['layout'], $rows);
    }

    /** @return array<int,string> */
    public static function ids(): array
    {
        return array_map(fn (AuthLayout $l) => $l->key(), self::all());
    }

    /** @return array<int,array> */
    public static function toClientSchema(): array
    {
        return array_map(fn (AuthLayout $l) => $l->toClientSchema(), self::all());
    }

    public static function forget(string $source): void
    {
        self::seed();

        self::$layouts = array_filter(self::$layouts, fn (array $row) => $row['source'] !== $source);
    }

    public static function flush(): void
    {
        self::$layouts = [];
        self::$seq = 0;
        self::$seeded = false;
    }

    /** Idempotent. Reads the class list from config('myra.appearance.auth_layouts'). */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        foreach ((array) config('myra.appearance.auth_layouts', []) as $class) {
            if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'define')) {
                continue;
            }

            $layout = $class::define();

            if ($layout instanceof AuthLayout) {
                self::add($layout, 'core');
            }
        }
    }

    /**
     * The stored key, unless the actor may preview via ?authLayout=.
     *
     * An unknown, renamed, deleted or traversal-shaped key ALWAYS degrades to
     * 'split'. The terminal fallback is hardcoded, so an empty or de-seeded
     * registry still yields a mountable shell.
     */
    public static function resolve(?string $stored, ?Request $request = null): AuthLayout
    {
        self::seed();

        $requested = $request?->query('authLayout');

        if (is_string($requested) && $requested !== '' && self::mayPreview($request)) {
            $draft = self::get($requested);

            if ($draft !== null) {
                return $draft;
            }
        }

        return (is_string($stored) ? self::get($stored) : null)
            ?? self::get(self::FALLBACK)
            ?? AuthLayout::make(self::FALLBACK)->component(AuthLayout::FALLBACK_COMPONENT);
    }

    private static function mayPreview(?Request $request): bool
    {
        $user = $request?->user();

        return $user instanceof Authorizable && $user->can('settings.edit');
    }
}
