<?php

namespace App\Homepage\Sections;

/**
 * The single source of truth for the authorable section types.
 *
 * Mirrors TemplateRegistry method for method: declaration-ordered, idempotent
 * seed, and a get() that returns null rather than guessing.
 */
final class SectionRegistry
{
    /** @var array<string,array{order:int,source:string,type:SectionType}> */
    private static array $types = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    public static function add(SectionType $type, string $source = 'core'): void
    {
        self::$types[$type->key()] = [
            'order' => self::$types[$type->key()]['order'] ?? self::$seq++,
            'source' => $source,
            'type' => $type,
        ];
    }

    public static function has(string $key): bool
    {
        self::seed();

        return isset(self::$types[$key]);
    }

    public static function get(string $key): ?SectionType
    {
        self::seed();

        return self::$types[$key]['type'] ?? null;
    }

    /** @return array<int,SectionType> declaration-ordered */
    public static function all(): array
    {
        self::seed();

        $rows = array_values(self::$types);
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(fn (array $row) => $row['type'], $rows);
    }

    /** @return array<int,string> */
    public static function ids(): array
    {
        return array_map(fn (SectionType $t) => $t->key(), self::all());
    }

    /** @return array<int,array<string,mixed>> */
    public static function toClientSchema(): array
    {
        return array_map(fn (SectionType $t) => $t->toClientSchema(), self::all());
    }

    /** Seeds first so the removal is deterministic; flush() is the only way back. */
    public static function forget(string $source): void
    {
        self::seed();

        self::$types = array_filter(self::$types, fn (array $row) => $row['source'] !== $source);
    }

    public static function flush(): void
    {
        self::$types = [];
        self::$seq = 0;
        self::$seeded = false;
    }

    /**
     * Idempotent. Two config keys so bundles never edit each other's array.
     */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        $classes = [
            ...(array) config('myra.pagebuilder.sections', []),
            ...(array) config('myra.pagebuilder_extra.extra_sections', []),
        ];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'define')) {
                continue;
            }

            $type = $class::define();

            if ($type instanceof SectionType) {
                self::add($type, 'core');
            }
        }
    }
}
