<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/** Resolves a URL `{report}` segment to a declared report. Never a class name from the request. */
final class ReportRegistry
{
    public static function map(): array
    {
        return (array) config('myra.reports.definitions', []);
    }

    public static function has(string $report): bool
    {
        return isset(self::map()[$report]);
    }

    public static function resolve(string $report): ReportDefinition
    {
        $class = self::map()[$report] ?? null;

        abort_if($class === null || ! is_string($class) || ! class_exists($class), 404);
        abort_unless(method_exists($class, 'definition'), 404);

        $definition = $class::definition();

        abort_unless($definition instanceof ReportDefinition, 404);
        $definition->assertConfigured();

        return $definition;
    }

    /**
     * @param  string[]  $keys
     * @return array<string, array>
     */
    public static function schemaFor(array $keys, ?Authenticatable $user): array
    {
        $out = [];

        foreach ($keys as $key) {
            if (! self::has($key)) {
                continue;
            }

            $definition = self::resolve($key);

            if (! self::allows($definition, $user)) {
                continue;
            }

            $out[$key] = $definition->toClientSchema($user);
        }

        return $out;
    }

    /** @return array<string, array> everything the actor may see */
    public static function visibleTo(?Authenticatable $user): array
    {
        return self::schemaFor(array_keys(self::map()), $user);
    }

    private static function allows(ReportDefinition $definition, ?Authenticatable $user): bool
    {
        return $user instanceof Authorizable && $user->can($definition->permissionAbility());
    }
}
