<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

final class Sql
{
    /** Escape character embedded in every pattern produced by like(). */
    public const LIKE_ESCAPE = '\\';

    /**
     * Escape LIKE wildcards so a user's `%` or `_` cannot widen the match or
     * force a full table scan.
     *
     * The pattern is only half of the contract: it MUST be paired with an
     * explicit `ESCAPE` clause, because SQLite has no default escape character.
     * Use whereLike()/orWhereLike() instead of hand-writing the predicate.
     *
     * @param  string  $mode  contains | starts | ends | exact
     */
    public static function like(string $value, string $mode = 'contains'): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);

        return match ($mode) {
            'starts' => "{$escaped}%",
            'ends' => "%{$escaped}",
            'exact' => $escaped,
            default => "%{$escaped}%",
        };
    }

    /**
     * `column LIKE ? ESCAPE '\'` with the pattern bound as a parameter.
     *
     * The explicit ESCAPE clause is what makes an escaped `%`/`_` behave the
     * same on SQLite (no default escape char) as on MySQL (backslash).
     *
     * @param  string  $mode  contains | starts | ends | exact
     */
    public static function whereLike(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
        string $mode = 'contains',
        string $boolean = 'and',
    ): EloquentBuilder|QueryBuilder {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        $sql = sprintf(
            '%s like ? escape %s',
            $base->getGrammar()->wrap(self::assertColumn($column)),
            self::escapeLiteral($base->getConnection()->getDriverName()),
        );

        return $query->whereRaw($sql, [self::like($value, $mode)], $boolean);
    }

    /** OR-joined counterpart of whereLike(). */
    public static function orWhereLike(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
        string $mode = 'contains',
    ): EloquentBuilder|QueryBuilder {
        return self::whereLike($query, $column, $value, $mode, 'or');
    }

    /** The predicate is raw SQL, so the identifier must be a plain column path. */
    private static function assertColumn(string $column): string
    {
        if (preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $column) !== 1) {
            throw new InvalidArgumentException("Unsafe LIKE column [{$column}].");
        }

        return $column;
    }

    /**
     * MySQL/MariaDB re-process backslashes inside string literals, so the
     * escape char must be doubled there. SQLite, Postgres (with standard
     * conforming strings) and SQL Server take the literal as written.
     */
    private static function escapeLiteral(string $driver): string
    {
        return in_array($driver, ['mysql', 'mariadb'], true) ? "'\\\\'" : "'\\'";
    }
}
