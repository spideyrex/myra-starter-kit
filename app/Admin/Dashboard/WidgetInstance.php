<?php

namespace App\Admin\Dashboard;

use App\Admin\Report\ReportRegistry;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * THE security boundary for catalogue-added widgets.
 *
 * A stored instance is a REQUEST, never a schema. Every page load re-derives the
 * schema through WidgetCatalogue -> Gate -> ReportRegistry -> binding
 * intersection. A stale or tampered instance degrades to "not rendered": never a
 * 500, never a field-enumeration oracle.
 */
final class WidgetInstance
{
    public static function resolve(array $raw, ?Authenticatable $user): ?array
    {
        $widget = WidgetCatalogue::get((string) ($raw['catalogue'] ?? ''));

        if ($widget === null) {
            return null;
        }

        if (! $widget->visibleTo($user)) {
            return null;
        }

        $reportKey = $widget->reportKey();

        if ($reportKey !== null) {
            if (! ReportRegistry::has($reportKey)) {
                return null;
            }

            $ability = ReportRegistry::resolve($reportKey)->permissionAbility();

            if (! ($user instanceof Authorizable && $user->can($ability))) {
                return null;
            }
        }

        $key = (string) ($raw['key'] ?? '');

        if ($key === '') {
            return null;
        }

        $binding = self::binding($widget, is_array($raw['binding'] ?? null) ? $raw['binding'] : []);

        if ($binding === null) {
            return null;
        }

        $hasChartType = false;
        $chartType = self::chartType($widget, $raw['chartType'] ?? null, $hasChartType);

        if ($hasChartType && $chartType === null) {
            return null;
        }

        if ($widget->type() === 'chart' && $chartType === null) {
            return null;
        }

        return [
            'key' => $key,
            'type' => $widget->type(),
            'title' => self::title($raw['title'] ?? null),
            'colSpan' => self::span($raw['colSpan'] ?? null) ?? $widget->defaultSpan(),
            'rowSpan' => self::within($raw['rowSpan'] ?? null, 1, 4),
            'binding' => $binding,
            'chartType' => $chartType,
            'permission' => $widget->permissionAbility(),
            'catalogue' => $widget->key(),
        ];
    }

    /**
     * @param  array<int,array>  $instances
     * @return array<int,array> capped at config('myra.dashboard.max_instances')
     */
    public static function resolveAll(array $instances, ?Authenticatable $user): array
    {
        $cap = (int) config('myra.dashboard.max_instances', LayoutShape::MAX_INSTANCES);
        $perCatalogue = [];
        $out = [];

        foreach ($instances as $raw) {
            if (count($out) >= $cap) {
                break;
            }
            if (! is_array($raw)) {
                continue;
            }

            $resolved = self::resolve($raw, $user);

            if ($resolved === null) {
                continue;
            }

            $catalogueKey = $resolved['catalogue'];
            $used = $perCatalogue[$catalogueKey] ?? 0;
            $widget = WidgetCatalogue::get($catalogueKey);

            if ($widget !== null && $used >= $widget->instanceCap()) {
                continue;
            }

            $perCatalogue[$catalogueKey] = $used + 1;
            $out[] = $resolved;
        }

        return $out;
    }

    /**
     * Keep ONLY keys the catalogue entry declared a default for, and only values
     * inside the declared vocabulary. An explicitly supplied value outside that
     * vocabulary fails closed (null); an absent one falls back to the default.
     */
    private static function binding(CatalogueWidget $widget, array $raw): ?array
    {
        $defaults = $widget->defaultBinding();
        $out = [];

        foreach ($defaults as $name => $fallback) {
            $provided = array_key_exists($name, $raw);
            $value = $provided ? $raw[$name] : $fallback;

            $kept = match ($name) {
                'dimension' => is_string($value) && in_array($value, $widget->dimensionNames(), true) ? $value : null,
                'measures' => self::measures($widget, $value),
                'chartType' => is_string($value) && in_array($value, $widget->chartTypeNames(), true) ? $value : null,
                'limit' => self::limit($value),
                default => is_scalar($value) || $value === null ? $value : null,
            };

            if ($kept === null) {
                if ($provided && in_array($name, ['dimension', 'measures', 'chartType'], true)) {
                    return null;
                }

                continue;
            }

            $out[$name] = $kept;
        }

        if ($widget->reportKey() !== null) {
            $out['report'] = $widget->reportKey();
        }

        return $out;
    }

    private static function measures(CatalogueWidget $widget, mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $kept = array_values(array_filter(
            $value,
            static fn ($m) => is_string($m) && in_array($m, $widget->measureNames(), true),
        ));

        return $kept === [] ? null : $kept;
    }

    private static function limit(mixed $value): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return max(1, min($value, (int) config('myra.reports.max_groups', 200)));
    }

    /** A chart catalogue entry that declares no chartTypes() cannot be instantiated. */
    private static function chartType(CatalogueWidget $widget, mixed $value, ?bool &$provided): ?string
    {
        $provided = $value !== null;
        $allowed = $widget->chartTypeNames();

        if ($allowed === []) {
            return null;
        }

        if (is_string($value)) {
            return in_array($value, $allowed, true) ? $value : null;
        }

        if ($provided) {
            return null;
        }

        $default = $widget->defaultBinding()['chartType'] ?? null;

        if (is_string($default) && in_array($default, $allowed, true)) {
            return $default;
        }

        return $allowed[0];
    }

    /** The row was validated on write; a hand-edited row still cannot widen the grid. */
    private static function span(mixed $value): int|array|null
    {
        if (is_int($value)) {
            return self::within($value, 1, 4);
        }

        if (! is_array($value) || array_is_list($value)) {
            return null;
        }

        $out = [];
        foreach (['sm', 'lg', 'xl'] as $breakpoint) {
            $span = self::within($value[$breakpoint] ?? null, 1, 4);
            if ($span !== null) {
                $out[$breakpoint] = $span;
            }
        }

        return $out === [] ? null : $out;
    }

    private static function within(mixed $value, int $min, int $max): ?int
    {
        if (! is_int($value)) {
            return null;
        }

        return max($min, min($value, $max));
    }

    private static function title(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, 60);
    }
}
