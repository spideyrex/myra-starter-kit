<?php

namespace App\Admin\Navigation;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Server-contributed sidebar entries.
 *
 * Static on purpose: anything that wants to contribute (a cluster, a plugin's
 * service provider) can push here without either side depending on the other's
 * boot order. With nothing registered forUser() returns [], which is what makes
 * the client merge the identity operation.
 */
final class NavRegistry
{
    /**
     * label => ['labelKey' => string, 'sort' => int, 'order' => int,
     *           'items' => array<int,array{order:int,source:string,node:array}>]
     *
     * @var array<string,array>
     */
    private static array $groups = [];

    private static int $seq = 0;

    private static ?string $seededSignature = null;

    public static function add(NavGroup|NavItem $node, string $source = 'core'): void
    {
        if ($node instanceof NavGroup) {
            self::touchGroup($node->labelKey(), $node->sortOrder());
            foreach ($node->children() as $item) {
                self::push($node->labelKey(), $item->toArray(), $source);
            }

            return;
        }

        self::push((string) config('myra.nav_group', 'Custom'), $node->toArray(), $source);
    }

    /**
     * Cross-bundle contract: a plain array in the shape documented by the plugin
     * manifest. Unknown keys are ignored and a missing labelKey is dropped —
     * a contributor must never be able to crash the sidebar.
     */
    public static function addRaw(string $groupLabelKey, array $item, string $source = 'core'): void
    {
        $node = self::normalise($item);

        if ($node === null) {
            return;
        }

        self::push($groupLabelKey, $node, $source);
    }

    /** Reads config('myra.clusters') and instantiates each cluster's items(). Idempotent. */
    public static function seedClusters(): void
    {
        $declared = (array) config('myra.clusters', []);
        $signature = md5(serialize($declared));

        if (self::$seededSignature === $signature) {
            return;
        }

        self::forget('cluster');
        self::$seededSignature = $signature;

        foreach ($declared as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Cluster::class)) {
                continue;
            }

            try {
                $children = [];
                foreach ($class::items() as $item) {
                    if ($item instanceof NavItem) {
                        $children[] = $item->toArray();
                    }
                }

                $slug = trim($class::$slug, '/');

                self::push($class::$groupLabelKey ?? (string) config('myra.nav_group', 'Custom'), [
                    'labelKey' => $class::labelKey(),
                    'href' => null,
                    'icon' => $class::$icon,
                    'permission' => $class::$permission,
                    'sort' => $class::$sort,
                    'activePrefix' => ($class::$prefixesUrls && $slug !== '') ? '/admin/'.$slug : null,
                    'items' => $children,
                ], 'cluster');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Permission-filtered, cluster-collapsed, sort-ordered, JSON-ready. */
    public static function forUser(?Authenticatable $user): array
    {
        self::seedClusters();

        $groups = array_values(self::$groups);
        usort($groups, fn ($a, $b) => [$a['sort'], $a['order']] <=> [$b['sort'], $b['order']]);

        $out = [];

        foreach ($groups as $group) {
            $entries = $group['items'];
            usort($entries, fn ($a, $b) => [$a['node']['sort'], $a['order']] <=> [$b['node']['sort'], $b['order']]);

            $items = self::filterItems(array_column($entries, 'node'), $user);

            if ($items === []) {
                continue;
            }

            $out[] = [
                'labelKey' => $group['labelKey'],
                'sort' => $group['sort'],
                'items' => $items,
            ];
        }

        return $out;
    }

    public static function flush(): void
    {
        self::$groups = [];
        self::$seq = 0;
        self::$seededSignature = null;
    }

    /** Drop everything a given source contributed. */
    public static function forget(string $source): void
    {
        foreach (self::$groups as $label => $group) {
            self::$groups[$label]['items'] = array_values(array_filter(
                $group['items'],
                fn (array $entry) => $entry['source'] !== $source,
            ));
        }
    }

    private static function filterItems(array $items, ?Authenticatable $user): array
    {
        $kept = [];

        foreach ($items as $item) {
            $ability = $item['permission'] ?? null;

            if ($ability !== null && ! self::allows($user, $ability)) {
                continue;
            }

            $children = self::filterItems($item['items'] ?? [], $user);
            usort($children, fn ($a, $b) => $a['sort'] <=> $b['sort']);

            // An empty expander must never render.
            if ($children === [] && ($item['href'] ?? null) === null) {
                continue;
            }

            $item['items'] = $children;
            $kept[] = $item;
        }

        return $kept;
    }

    private static function allows(?Authenticatable $user, string $ability): bool
    {
        return $user instanceof Authorizable && $user->can($ability);
    }

    private static function push(string $groupLabelKey, array $node, string $source): void
    {
        self::touchGroup($groupLabelKey);

        self::$groups[$groupLabelKey]['items'][] = [
            'order' => self::$seq++,
            'source' => $source,
            'node' => $node,
        ];
    }

    private static function touchGroup(string $labelKey, ?int $sort = null): void
    {
        if (! isset(self::$groups[$labelKey])) {
            self::$groups[$labelKey] = [
                'labelKey' => $labelKey,
                'sort' => 0,
                'order' => self::$seq++,
                'items' => [],
            ];
        }

        if ($sort !== null) {
            self::$groups[$labelKey]['sort'] = $sort;
        }
    }

    private static function normalise(array $item, int $depth = 0): ?array
    {
        $labelKey = $item['labelKey'] ?? null;

        if (! is_string($labelKey) || $labelKey === '') {
            return null;
        }

        $children = [];

        if ($depth === 0 && is_array($item['items'] ?? null)) {
            foreach ($item['items'] as $child) {
                if (is_array($child) && ($normalised = self::normalise($child, 1)) !== null) {
                    $children[] = $normalised;
                }
            }
        }

        return [
            'labelKey' => $labelKey,
            'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
            'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : null,
            'permission' => is_string($item['permission'] ?? null) ? $item['permission'] : null,
            'sort' => (int) ($item['sort'] ?? 0),
            'activePrefix' => is_string($item['activePrefix'] ?? null) ? $item['activePrefix'] : null,
            'items' => $children,
        ];
    }
}
