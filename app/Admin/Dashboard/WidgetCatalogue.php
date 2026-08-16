<?php

namespace App\Admin\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Static registry of addable widgets (the NavRegistry pattern).
 *
 * Ships EMPTY: with config('myra.dashboard.catalogue') empty, forUser()
 * returns [] and the catalogue sheet shows an empty state.
 */
final class WidgetCatalogue
{
    /** @var array<string,array{widget:CatalogueWidget,source:string,order:int}> */
    private static array $widgets = [];

    private static int $seq = 0;

    private static ?string $seededSignature = null;

    public static function add(CatalogueWidget $w, string $source = 'core'): void
    {
        self::$widgets[$w->key()] = [
            'widget' => $w,
            'source' => $source,
            'order' => self::$seq++,
        ];
    }

    public static function has(string $key): bool
    {
        return isset(self::$widgets[$key]);
    }

    public static function get(string $key): ?CatalogueWidget
    {
        return self::$widgets[$key]['widget'] ?? null;
    }

    /**
     * Permission-filtered. An unpermitted entry's EXISTENCE is not disclosed.
     *
     * @return array<int,array>
     */
    public static function forUser(?Authenticatable $user): array
    {
        self::seed();

        $entries = array_values(self::$widgets);
        usort($entries, static fn ($a, $b) => $a['order'] <=> $b['order']);

        $out = [];

        foreach ($entries as $entry) {
            if (! $entry['widget']->visibleTo($user)) {
                continue;
            }

            $out[] = $entry['widget']->toClientSchema();
        }

        return $out;
    }

    public static function forget(string $source): void
    {
        self::$widgets = array_filter(
            self::$widgets,
            static fn (array $entry) => $entry['source'] !== $source,
        );
    }

    public static function flush(): void
    {
        self::$widgets = [];
        self::$seq = 0;
        self::$seededSignature = null;
    }

    /**
     * Idempotent. Each configured provider is either a CatalogueWidget or a
     * class exposing `static widgets(): iterable<CatalogueWidget>`. A bad
     * provider is reported and skipped — never fatal.
     */
    public static function seed(): void
    {
        $declared = (array) config('myra.dashboard.catalogue', []);
        $signature = md5(serialize(array_map(
            static fn ($p) => is_object($p) ? spl_object_hash($p) : $p,
            $declared,
        )));

        if (self::$seededSignature === $signature) {
            return;
        }

        self::forget('config');
        self::$seededSignature = $signature;

        foreach ($declared as $provider) {
            try {
                foreach (self::expand($provider) as $widget) {
                    self::add($widget, 'config');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** @return iterable<CatalogueWidget> */
    private static function expand(mixed $provider): iterable
    {
        if ($provider instanceof CatalogueWidget) {
            return [$provider];
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'widgets')) {
            $widgets = $provider::widgets();

            return is_iterable($widgets)
                ? array_filter(
                    is_array($widgets) ? $widgets : iterator_to_array($widgets),
                    static fn ($w) => $w instanceof CatalogueWidget,
                )
                : [];
        }

        return [];
    }
}
