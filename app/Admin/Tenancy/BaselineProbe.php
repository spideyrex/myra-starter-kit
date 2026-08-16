<?php

namespace App\Admin\Tenancy;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Captures the compiled SQL of a canonical query per model, for the tenancy
 * no-op proof. The command that writes the baseline and the test that asserts
 * against it both go through here, so the two can never drift.
 */
final class BaselineProbe
{
    /** The models the baseline covers. `User` is the control: it is never tenant-scoped. */
    public const MODELS = [
        \App\Models\Article::class,
        \App\Models\Page::class,
        \App\Models\Category::class,
        \App\Models\User::class,
    ];

    /** Bindings equal to the actor's key are replaced with this token. */
    public const ACTOR_TOKEN = ':actor';

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{sql:string,bindings:array<int,mixed>}
     */
    public static function query(string $modelClass, ?Authenticatable $actor): array
    {
        $base = $modelClass::query()->orderBy('created_at', 'desc')->toBase();

        $actorKey = $actor?->getAuthIdentifier();

        $bindings = array_map(
            static fn ($binding) => $actorKey !== null && $binding == $actorKey ? self::ACTOR_TOKEN : $binding,
            array_values($base->getBindings()),
        );

        return ['sql' => $base->toSql(), 'bindings' => $bindings];
    }

    /** @param class-string<Model> $modelClass */
    public static function scopeKeys(string $modelClass): array
    {
        return array_map(
            static fn ($key) => (string) $key,
            array_keys((new $modelClass)->getGlobalScopes()),
        );
    }

    /**
     * @param  array<string,?Authenticatable>  $actors  role label => actor (null = guest)
     */
    public static function capture(array $actors): array
    {
        $out = [];

        foreach (self::MODELS as $modelClass) {
            $row = [];

            foreach ($actors as $label => $actor) {
                $row[$label] = self::query($modelClass, $actor);
            }

            $row['scopes'] = self::scopeKeys($modelClass);
            $out[$modelClass] = $row;
        }

        return $out;
    }

    /** Deterministic bytes: recursive key sort, 4-space pretty print, trailing newline. */
    public static function encode(array $data): string
    {
        self::sort($data);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private static function sort(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sort($value);
            }
        }
        unset($value);

        if (! array_is_list($data)) {
            ksort($data);
        }
    }
}
