<?php

namespace App\Admin\Import;

/** Resolves a URL `{resource}` segment to a declared import. Never a class name from the request. */
final class ImportRegistry
{
    public static function map(): array
    {
        return (array) config('myra.imports.resources', []);
    }

    public static function has(string $resource): bool
    {
        return isset(self::map()[$resource]);
    }

    public static function resolve(string $resource): ImportDefinition
    {
        $class = self::map()[$resource] ?? null;

        abort_if($class === null || ! is_string($class) || ! class_exists($class), 404);
        abort_unless(method_exists($class, 'definition'), 404);

        $definition = $class::definition();

        abort_unless($definition instanceof ImportDefinition, 404);
        $definition->assertConfigured();

        return $definition;
    }
}
