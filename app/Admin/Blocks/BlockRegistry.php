<?php

namespace App\Admin\Blocks;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The single source of truth for the shadcn block catalogue.
 *
 * Seeded from scripts/shadcn/blocks-manifest.json — the file the fetch script
 * wrote — so the catalogue can never advertise a block that is not on disk.
 * Permission filtering mirrors DemoRegistry/NavRegistry: an entry the actor
 * cannot reach is not disabled, its existence is never disclosed.
 */
final class BlockRegistry
{
    public const MANIFEST = 'scripts/shadcn/blocks-manifest.json';

    /** @var array<string,array{order:int,source:string,entry:BlockEntry}> */
    private static array $entries = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    public static function add(BlockEntry $e, string $source = 'core'): void
    {
        self::$entries[$e->key()] = [
            'order' => self::$entries[$e->key()]['order'] ?? self::$seq++,
            'source' => $source,
            'entry' => $e,
        ];
    }

    public static function has(string $key): bool
    {
        return isset(self::$entries[$key]);
    }

    public static function get(string $key): ?BlockEntry
    {
        return self::$entries[$key]['entry'] ?? null;
    }

    /**
     * Permission-filtered, declaration-ordered, JSON-ready.
     *
     * @return array<int,array>
     */
    public static function forUser(?Authenticatable $user): array
    {
        self::seed();

        $rows = array_values(self::$entries);
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        $out = [];

        foreach ($rows as $row) {
            $entry = $row['entry'];
            $ability = $entry->permissionAbility();

            if ($ability !== null && ! ($user instanceof Authorizable && $user->can($ability))) {
                continue;
            }

            $out[] = $entry->toClientSchema();
        }

        return $out;
    }

    /** @return array<string,array<int,array>> */
    public static function byCategory(?Authenticatable $user): array
    {
        $out = [];

        foreach (self::forUser($user) as $schema) {
            $out[$schema['category']][] = $schema;
        }

        return $out;
    }

    /** @return array<int,string> */
    public static function categories(): array
    {
        self::seed();

        $rows = array_values(self::$entries);
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        $out = [];
        foreach ($rows as $row) {
            $slug = $row['entry']->categorySlug();
            if (! in_array($slug, $out, true)) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    public static function forget(string $source): void
    {
        self::$entries = array_filter(self::$entries, fn (array $row) => $row['source'] !== $source);
        self::$seeded = false;
    }

    public static function flush(): void
    {
        self::$entries = [];
        self::$seq = 0;
        self::$seeded = false;
    }

    /** Idempotent. Reads the committed manifest; a missing manifest is an empty catalogue, never a 500. */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        foreach (self::manifest() as $key => $spec) {
            $entry = BlockEntry::make($key)
                ->category($spec['category'] ?? 'other')
                ->entryFile($spec['entryFile'] ?? "{$key}/Page.vue")
                ->files($spec['files'] ?? [])
                ->registryDependencies($spec['registryDependencies'] ?? [])
                ->npmDependencies($spec['npmDependencies'] ?? [])
                ->available((bool) ($spec['available'] ?? false), $spec['unavailableReason'] ?? null)
                ->tags($spec['tags'] ?? [])
                ->since('2.6.0')
                ->viewport($spec['viewport'] ?? 'full');

            self::add($entry, 'core');
        }
    }

    /** @return array<string,array> */
    public static function manifest(): array
    {
        $path = base_path(self::MANIFEST);

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) && is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [];
    }

    /**
     * Source for the code tab. Paths come from the MANIFEST, never from the URL,
     * so the {block} parameter is never concatenated onto a filesystem path.
     *
     * @return array<int,array{path:string,language:string,content:string}>
     */
    public static function sourceFor(BlockEntry $e): array
    {
        $out = [];

        foreach ($e->fileList() as $file) {
            $relative = $file['path'] ?? '';

            if (! is_string($relative) || $relative === '') {
                continue;
            }

            $absolute = base_path($relative);

            if (! is_file($absolute)) {
                continue;
            }

            $out[] = [
                'path' => $relative,
                'language' => self::languageFor($relative),
                'content' => (string) file_get_contents($absolute),
            ];
        }

        return $out;
    }

    private static function languageFor(string $path): string
    {
        $bare = preg_replace('/\.txt$/', '', $path) ?? $path;

        return match (true) {
            str_ends_with($bare, '.vue') => 'vue',
            str_ends_with($bare, '.ts') => 'typescript',
            str_ends_with($bare, '.js') => 'javascript',
            str_ends_with($bare, '.json') => 'json',
            str_ends_with($bare, '.css') => 'css',
            default => 'plaintext',
        };
    }
}
