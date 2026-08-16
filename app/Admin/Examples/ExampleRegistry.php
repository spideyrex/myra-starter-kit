<?php

namespace App\Admin\Examples;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The single source of truth for the shadcn example catalogue.
 *
 * Seeded from the COMMITTED manifest (scripts/shadcn/examples-manifest.json),
 * never from a directory scan, so the entry set, the source that is served and
 * the hashes that guard it all come from one file.
 */
final class ExampleRegistry
{
    public const MANIFEST = 'scripts/shadcn/examples-manifest.json';

    public const ROOT = 'resources/js/examples';

    public const QUARANTINE = 'resources/js/examples/_unavailable';

    /** @var array<string,array{order:int,source:string,entry:ExampleEntry}> */
    private static array $entries = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    /** @var array<string,array>|null */
    private static ?array $manifest = null;

    public static function add(ExampleEntry $e, string $source = 'core'): void
    {
        self::$entries[$e->key()] = [
            'order' => self::$entries[$e->key()]['order'] ?? self::$seq++,
            'source' => $source,
            'entry' => $e,
        ];
    }

    /** Seed-free, like DemoRegistry: a lookup must observe a forget(), not undo it. */
    public static function has(string $key): bool
    {
        return isset(self::$entries[$key]);
    }

    public static function get(string $key): ?ExampleEntry
    {
        return self::$entries[$key]['entry'] ?? null;
    }

    /**
     * Permission-filtered, declaration-ordered, JSON-ready. An entry the actor
     * cannot reach is not disabled — its existence is never disclosed.
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

    /** Clears the seeded flag too, so a later seed() can rebuild what was dropped. */
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
        self::$manifest = null;
    }

    /** Idempotent. Reads the committed manifest; a missing manifest seeds nothing. */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        foreach (self::manifest() as $key => $row) {
            $entry = ExampleEntry::make($key)
                ->shell($row['shell'] ?? "{$key}/Page.vue")
                ->origin($row['origin'] ?? 'fetched')
                ->sourceRef($row['sourceSha'] ?? null)
                ->files($row['files'] ?? [])
                ->registryDependencies($row['registryDependencies'] ?? [])
                ->npmDependencies($row['npmDependencies'] ?? [])
                ->available((bool) ($row['available'] ?? false), $row['unavailableReason'] ?? null)
                ->dataFiles(array_values(array_filter(
                    array_column($row['files'] ?? [], 'path'),
                    fn (string $path) => str_starts_with($path, 'data/'),
                )))
                ->surfaces(self::SURFACES[$key] ?? [])
                ->tags(self::TAGS[$key] ?? [])
                ->since('2.6.0')
                ->permission('examples.view');

            self::add($entry, 'core');
        }
    }

    /** @return array<string,array> */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = base_path(self::MANIFEST);

        if (! is_file($path)) {
            return self::$manifest = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return self::$manifest = is_array($decoded['examples'] ?? null) ? $decoded['examples'] : [];
    }

    /**
     * Source for one entry, read from the MANIFEST's file list — the caller's
     * id never reaches a filesystem path, so a traversal-shaped id resolves to
     * no entry and therefore to no read.
     *
     * @return array<int,array{path:string,language:string,content:string}>
     */
    public static function sourceFor(ExampleEntry $e): array
    {
        $root = base_path($e->isAvailable()
            ? self::ROOT.'/'.$e->key()
            : self::QUARANTINE.'/'.$e->key());

        $out = [];

        foreach ($e->fileList() as $file) {
            $absolute = $root.'/'.$file['path'];

            if (! is_file($absolute)) {
                continue;
            }

            $out[] = [
                'path' => $file['path'],
                'language' => self::languageOf($file['path']),
                'content' => (string) file_get_contents($absolute),
            ];
        }

        return $out;
    }

    private static function languageOf(string $path): string
    {
        $name = preg_replace('/\.txt$/', '', $path) ?? $path;

        return match (pathinfo($name, PATHINFO_EXTENSION)) {
            'vue' => 'vue',
            'ts' => 'typescript',
            'js', 'mjs' => 'javascript',
            'json' => 'json',
            'css' => 'css',
            'md' => 'markdown',
            default => 'plaintext',
        };
    }

    /** Which UI surfaces an example demonstrates; drives the catalogue chips. */
    private const SURFACES = [
        'authentication' => ['form', 'split'],
        'cards' => ['grid', 'detail'],
        'dashboard' => ['list', 'detail', 'chart'],
        'playground' => ['compose', 'detail'],
        'tasks' => ['list', 'detail'],
        'mail' => ['list', 'detail', 'compose'],
        'music' => ['grid', 'detail'],
        'forms' => ['form', 'detail'],
    ];

    private const TAGS = [
        'authentication' => ['forms', 'layout'],
        'cards' => ['layout', 'dashboard'],
        'dashboard' => ['dashboard', 'tables'],
        'playground' => ['forms', 'ai'],
        'tasks' => ['tables', 'data'],
        'mail' => ['layout', 'content'],
        'music' => ['layout', 'content'],
        'forms' => ['forms'],
    ];
}
