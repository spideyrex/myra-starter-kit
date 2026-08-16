<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Throwable;

final class Sql
{
    /** Per-connection `sql_mode` probe result; true = backslashes are reprocessed. */
    private static array $backslashEscapes = [];
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
            self::escapeLiteral($base->getConnection()),
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
     * MySQL/MariaDB re-process backslashes inside string literals, so the escape
     * char must be doubled there — UNLESS sql_mode contains NO_BACKSLASH_ESCAPES,
     * where a doubled backslash is a two-character string and MySQL rejects it as
     * an ESCAPE argument. SQLite, Postgres (standard conforming strings) and SQL
     * Server take the literal as written.
     */
    private static function escapeLiteral(ConnectionInterface $connection): string
    {
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return "'\\'";
        }

        return self::reprocessesBackslashes($connection) ? "'\\\\'" : "'\\'";
    }

    /** Probed once per connection; a failed probe assumes standard MySQL behaviour. */
    private static function reprocessesBackslashes(ConnectionInterface $connection): bool
    {
        $key = $connection->getName() ?: 'default';

        if (! array_key_exists($key, self::$backslashEscapes)) {
            try {
                $mode = (string) ($connection->selectOne('select @@sql_mode as mode')->mode ?? '');
            } catch (Throwable) {
                $mode = '';
            }

            self::$backslashEscapes[$key] = ! str_contains(strtoupper($mode), 'NO_BACKSLASH_ESCAPES');
        }

        return self::$backslashEscapes[$key];
    }

    /** Test seam: drop the cached sql_mode probes. */
    public static function flushDriverCache(): void
    {
        self::$backslashEscapes = [];
    }
}
