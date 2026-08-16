<?php

namespace App\Admin\Demo;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The single source of truth for the feature gallery.
 *
 * Mirrors App\Admin\Navigation\NavRegistry so permission filtering is the same
 * mechanism the sidebar already uses: an entry the actor cannot reach is not
 * merely disabled, its existence is never disclosed.
 */
final class DemoRegistry
{
    /** @var array<string,array{order:int,source:string,entry:DemoEntry}> */
    private static array $entries = [];

    private static int $seq = 0;

    private static bool $seeded = false;

    public static function add(DemoEntry $e, string $source = 'core'): void
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

    public static function get(string $key): ?DemoEntry
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
    }

    /**
     * Idempotent. One declaration per page in resources/js/Pages/Admin/Demo/ —
     * the drift between the pages that exist and the cards that advertise them
     * is what this registry exists to close, so DemoRegistryTest asserts the
     * counts match.
     */
    public static function seed(): void
    {
        if (self::$seeded) {
            return;
        }

        self::$seeded = true;

        foreach (self::declarations() as $key => $spec) {
            $entry = DemoEntry::make($key)
                ->routeName($spec['route'])
                ->icon($spec['icon'])
                ->tags($spec['tags'])
                ->since($spec['since'])
                ->permission($spec['permission'] ?? null)
                ->playground($spec['playground'] ?? null);

            self::add($entry, 'core');
        }
    }

    /**
     * @return array<string,array{route:string,icon:string,tags:array<int,string>,
     *                            since:string,permission?:string,playground?:string}>
     */
    private static function declarations(): array
    {
        return [
            'richTextEditor' => ['route' => 'admin.demo.rich-text-editor', 'icon' => 'Type', 'tags' => ['forms', 'content'], 'since' => '1.0.0'],
            'repeaterField' => ['route' => 'admin.demo.repeater-field', 'icon' => 'Repeat', 'tags' => ['forms'], 'since' => '1.0.0'],
            'formBuilder' => ['route' => 'admin.demo.form-builder', 'icon' => 'FormInput', 'tags' => ['forms'], 'since' => '1.0.0'],
            'bulkActions' => ['route' => 'admin.demo.bulk-actions', 'icon' => 'CheckSquare', 'tags' => ['tables'], 'since' => '1.0.0'],
            'softDeletes' => ['route' => 'admin.demo.soft-deletes', 'icon' => 'Trash2', 'tags' => ['tables', 'data'], 'since' => '1.0.0'],
            'actionModals' => ['route' => 'admin.demo.action-modals', 'icon' => 'PanelTop', 'tags' => ['tables'], 'since' => '1.0.0'],
            'importExport' => ['route' => 'admin.demo.import-export', 'icon' => 'Upload', 'tags' => ['data'], 'since' => '1.0.0'],
            'globalSearch' => ['route' => 'admin.demo.global-search', 'icon' => 'Search', 'tags' => ['navigation'], 'since' => '1.0.0'],
            'inlineEditing' => ['route' => 'admin.demo.inline-editing', 'icon' => 'Pencil', 'tags' => ['tables'], 'since' => '1.1.0'],
            'conditionalFields' => ['route' => 'admin.demo.conditional-fields', 'icon' => 'Eye', 'tags' => ['forms'], 'since' => '1.1.0'],
            'infolist' => ['route' => 'admin.demo.infolist', 'icon' => 'Info', 'tags' => ['layout'], 'since' => '1.1.0'],
            'relationManager' => ['route' => 'admin.demo.relation-manager', 'icon' => 'Link2', 'tags' => ['tables', 'data'], 'since' => '1.1.0'],
            'grouping' => ['route' => 'admin.demo.grouping', 'icon' => 'Layers', 'tags' => ['tables'], 'since' => '1.1.0'],
            'reordering' => ['route' => 'admin.demo.reordering', 'icon' => 'GripVertical', 'tags' => ['tables'], 'since' => '1.1.0'],
            'widgets' => ['route' => 'admin.demo.widgets', 'icon' => 'LayoutDashboard', 'tags' => ['dashboard'], 'since' => '1.1.0'],
            'fieldTypes' => ['route' => 'admin.demo.field-types', 'icon' => 'ListChecks', 'tags' => ['forms'], 'since' => '1.2.0'],
            'codeEditor' => ['route' => 'admin.demo.code-editor', 'icon' => 'Code', 'tags' => ['forms', 'content'], 'since' => '1.2.0'],
            'advancedFilters' => ['route' => 'admin.demo.advanced-filters', 'icon' => 'SlidersHorizontal', 'tags' => ['tables'], 'since' => '1.2.0'],
            'wizard' => ['route' => 'admin.demo.wizard', 'icon' => 'Wand2', 'tags' => ['forms'], 'since' => '1.2.0'],
            'map' => ['route' => 'admin.demo.map', 'icon' => 'MapPin', 'tags' => ['data'], 'since' => '1.2.0'],
            'savedViews' => ['route' => 'admin.demo.saved-views', 'icon' => 'Bookmark', 'tags' => ['tables'], 'since' => '2.2.0'],
            'reports' => ['route' => 'admin.demo.reports', 'icon' => 'BarChart3', 'tags' => ['dashboard', 'data'], 'since' => '2.3.0', 'permission' => 'reports.view'],
            'reportDelivery' => ['route' => 'admin.demo.report-delivery', 'icon' => 'Send', 'tags' => ['dashboard'], 'since' => '2.3.0', 'permission' => 'reports.schedule'],
            'tenancy' => ['route' => 'admin.demo.tenancy', 'icon' => 'Building2', 'tags' => ['platform'], 'since' => '2.4.0'],
            'plugins' => ['route' => 'admin.demo.plugins', 'icon' => 'Puzzle', 'tags' => ['platform'], 'since' => '2.4.0'],
            'scale' => ['route' => 'admin.demo.scale', 'icon' => 'Gauge', 'tags' => ['tables', 'platform'], 'since' => '2.4.0'],
            'playground' => ['route' => 'admin.demo.playground', 'icon' => 'SlidersHorizontal', 'tags' => ['gallery'], 'since' => '2.5.0', 'playground' => 'statCard'],
        ];
    }
}
