<?php

namespace App\Support;

final class Sql
{
    /**
     * Escape LIKE wildcards so a user's `%` or `_` cannot widen the match or
     * force a full table scan. The default MySQL/SQLite escape char is `\`.
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
}
