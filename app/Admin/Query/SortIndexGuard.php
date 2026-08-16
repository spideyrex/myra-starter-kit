<?php

namespace App\Admin\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Development-time guard: a column a table lets the client sort by should be the
 * LEADING column of some index, or a 100k-row table degrades to a filesort.
 *
 * Production never throws — a missing index must degrade to slow, never to a 500.
 */
final class SortIndexGuard
{
    /** table => [index name => columns]; one introspection per table per process. */
    private static array $cache = [];

    /**
     * @param  array<string,?string>|string[]  $sortable  column list, or column => backing index name
     */
    public static function assert(Model $model, ?string $sort, array $sortable): void
    {
        if (! config('myra.performance.assert_indexed_sorts', false)) {
            return;
        }

        if (app()->isProduction() || $sort === null || $sort === '') {
            return;
        }

        $declared = self::normalise($sortable);

        if (! array_key_exists($sort, $declared)) {
            return;
        }

        if (in_array($sort, self::missing($model->getTable(), [$sort => $declared[$sort]]), true)) {
            throw UnindexedSortException::for($model->getTable(), $sort);
        }
    }

    /**
     * @param  array<string,?string>|string[]  $sortable
     * @return string[] sortable columns with no index whose LEADING column they are
     */
    public static function missing(string $table, array $sortable): array
    {
        $indexes = self::indexes($table);

        if ($indexes === null) {
            return [];
        }

        $leading = [];
        $names = [];

        foreach ($indexes as $name => $columns) {
            $names[strtolower($name)] = true;
            if (isset($columns[0])) {
                $leading[$columns[0]] = true;
            }
        }

        $missing = [];

        foreach (self::normalise($sortable) as $column => $indexName) {
            if ($indexName !== null && isset($names[strtolower($indexName)])) {
                continue;
            }
            if (isset($leading[$column])) {
                continue;
            }
            $missing[] = $column;
        }

        return $missing;
    }

    /** Tests only. */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return array<string,string[]>|null null when the table cannot be introspected */
    private static function indexes(string $table): ?array
    {
        if (array_key_exists($table, self::$cache)) {
            return self::$cache[$table];
        }

        try {
            $out = [];
            foreach (Schema::getIndexes($table) as $index) {
                $out[(string) $index['name']] = array_values(array_filter(
                    (array) ($index['columns'] ?? []),
                    fn ($c) => is_string($c),
                ));
            }
            // The primary key is not always reported as an index by every driver.
            $out['__primary'] = ['id'];

            return self::$cache[$table] = $out;
        } catch (Throwable) {
            return self::$cache[$table] = null;
        }
    }

    /**
     * Accepts both the historical string[] shape and the column => ?index map.
     *
     * @param  array<string,?string>|string[]  $sortable
     * @return array<string,?string>
     */
    private static function normalise(array $sortable): array
    {
        if ($sortable === []) {
            return [];
        }

        if (array_is_list($sortable)) {
            $out = [];
            foreach ($sortable as $column) {
                if (is_string($column)) {
                    $out[$column] = null;
                }
            }

            return $out;
        }

        $out = [];
        foreach ($sortable as $column => $index) {
            $out[(string) $column] = is_string($index) ? $index : null;
        }

        return $out;
    }
}
