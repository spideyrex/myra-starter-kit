<?php

namespace App\Admin\Dashboard;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The page's own (non-catalogue) tiles, declared in
 * config('myra.dashboard.static_widgets') as key => ability|null.
 *
 * Entries used to travel verbatim, which was fine while author == viewer by
 * construction. Once an admin authors what a viewer renders, a privileged tile
 * KEY reaching an unprivileged browser is a key-enumeration oracle.
 */
final class StaticWidgetRegistry
{
    /** @return array<int,string> */
    public static function visibleTo(?Authenticatable $user): array
    {
        $out = [];

        foreach (self::declared() as $key => $ability) {
            if ($ability === null || $ability === '') {
                $out[] = $key;

                continue;
            }

            if ($user instanceof Authorizable && $user->can($ability)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::declared());
    }

    /** @return array<string,string|null> */
    private static function declared(): array
    {
        $out = [];

        foreach ((array) config('myra.dashboard.static_widgets', []) as $key => $ability) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $out[$key] = is_string($ability) && $ability !== '' ? $ability : null;
        }

        return $out;
    }
}
