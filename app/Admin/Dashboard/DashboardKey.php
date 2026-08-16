<?php

namespace App\Admin\Dashboard;

/** Closed set. A dashboard key is never taken from a request value. */
final class DashboardKey
{
    /** @return array<int,string> */
    public static function all(): array
    {
        return array_values(array_filter(
            array_keys((array) config('myra.dashboard.keys', [])),
            static fn ($key) => is_string($key) && $key !== '',
        ));
    }

    public static function allows(string $key): bool
    {
        return in_array($key, self::all(), true);
    }
}
