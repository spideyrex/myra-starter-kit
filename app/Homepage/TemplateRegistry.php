<?php

namespace App\Homepage;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;

/**
 * The single source of truth for the landing-page templates.
 *
 * Mirrors App\Admin\Demo\DemoRegistry: declaration-ordered, idempotent seed,
 * and a resolve() that can never hand the public homepage a component the
 * client cannot mount.
 */
final class TemplateRegistry
{
    public const FALLBACK = 'classic';

    /** @var array<string,array{order:int,source:string,template:HomepageTemplate}> */
    private static array $templates = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    public static function add(HomepageTemplate $t, string $source = 'core'): void
    {
        self::$templates[$t->key()] = [
            'order' => self::$templates[$t->key()]['order'] ?? self::$seq++,
            'source' => $source,
            'template' => $t,
        ];
    }

    public static function has(string $key): bool
    {
        self::seed();

        return isset(self::$templates[$key]);
    }

    public static function get(string $key): ?HomepageTemplate
    {
        self::seed();

        return self::$templates[$key]['template'] ?? null;
    }

    /** @return array<int,HomepageTemplate> declaration-ordered */
    public static function all(): array
    {
        self::seed();

        $rows = array_values(self::$templates);
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn (array $row) => $row['template'], $rows);
    }

    /** @return array<int,string> */
    public static function ids(): array
    {
        return array_map(fn (HomepageTemplate $t) => $t->key(), self::all());
    }

    /** @return array<int,array> */
    public static function toClientSchema(): array
    {
        return array_map(fn (HomepageTemplate $t) => $t->toClientSchema(), self::all());
    }

    public static function forget(string $source): void
    {
        self::$templates = array_filter(self::$templates, fn (array $row) => $row['source'] !== $source);
        self::$seeded = false;
    }

    public static function flush(): void
    {
        self::$templates = [];
        self::$seq = 0;
        self::$seeded = false;
    }

    /** Idempotent. Reads the class list from config('myra.landing.templates'). */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        foreach ((array) config('myra.landing.templates', []) as $class) {
            if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'define')) {
                continue;
            }

            $template = $class::define();

            if ($template instanceof HomepageTemplate) {
                self::add($template, 'core');
            }
        }
    }

    /**
     * The stored key, unless the actor may preview a draft via ?template=.
     *
     * An unknown, renamed, deleted or traversal-shaped key ALWAYS degrades to
     * 'classic': the public homepage can never 500 because of a setting.
     */
    public static function resolve(?string $stored, ?Request $request = null): HomepageTemplate
    {
        self::seed();

        $requested = $request?->query('template');

        if (is_string($requested) && $requested !== '' && self::mayPreview($request)) {
            $draft = self::get($requested);

            if ($draft !== null) {
                return $draft;
            }
        }

        return (is_string($stored) ? self::get($stored) : null)
            ?? self::get(self::FALLBACK)
            ?? HomepageTemplate::make(self::FALLBACK)->component(HomepageTemplate::FALLBACK_COMPONENT);
    }

    private static function mayPreview(?Request $request): bool
    {
        $user = $request?->user();

        return $user instanceof Authorizable && $user->can('settings.edit');
    }
}
