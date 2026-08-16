<?php

namespace App\Admin\Tenancy;

use App\Admin\Tenancy\Exceptions\TenancyConfigurationException;
use App\Models\Traits\BelongsToTenant;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Multi-tenancy, opt-in and default off.
 *
 * Two independent locks gate every code path here: `myra.tenancy.enabled` must
 * be true AND the model must be listed in `myra.tenancy.models`. Adding
 * BelongsToTenant to a model changes nothing on its own.
 *
 * When disabled, every method on this class is a literal no-op: apply() adds no
 * predicate, unique()/exists() return the plain rules, and BelongsToTenant
 * registers neither a global scope nor a creating hook.
 */
final class Tenancy
{
    private static ?bool $enabled = null;

    private static bool $overridden = false;

    private static Model|int|string|null $override = null;

    private static ?Model $overrideModel = null;

    /** actor key (or '@guest') => resolved tenant */
    private static array $resolved = [];

    /** actor key (or '@guest') => bool */
    private static array $superAdmin = [];

    private static bool $asserted = false;

    /** table => whether it carries the tenant column */
    private static array $hasColumn = [];

    public static function enabled(): bool
    {
        return self::$enabled ??= config('myra.tenancy.enabled', false) === true;
    }

    public static function column(): string
    {
        return (string) config('myra.tenancy.column', 'team_id');
    }

    /** @return class-string<Model> */
    public static function model(): string
    {
        return (string) config('myra.tenancy.model', \App\Models\Team::class);
    }

    /**
     * Both locks. The ONLY thing BelongsToTenant consults at boot: a model that
     * is not listed here is never scoped, whatever traits it carries.
     */
    public static function scopes(string $modelClass): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $needle = ltrim($modelClass, '\\');

        foreach ((array) config('myra.tenancy.models', []) as $listed) {
            if (is_string($listed) && ltrim($listed, '\\') === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolved from users.current_team_id with membership RE-VALIDATED on every
     * request. Never from a route, header or query parameter — a tenant a
     * client can name is a tenant a client can pick.
     */
    public static function current(): ?Model
    {
        if (self::$overridden) {
            if (self::$override instanceof Model) {
                return self::$override;
            }

            if (self::$override === null) {
                return null;
            }

            return self::$overrideModel ??= (self::model())::query()->whereKey(self::$override)->first();
        }

        return self::fromActor();
    }

    public static function id(): int|string|null
    {
        if (self::$overridden) {
            return self::$override instanceof Model
                ? self::$override->getKey()
                : self::$override;
        }

        return self::fromActor()?->getKey();
    }

    public static function has(): bool
    {
        return self::id() !== null;
    }

    public static function actorIsSuperAdmin(): bool
    {
        $actor = Auth::user();
        $key = self::actorKey($actor);

        return self::$superAdmin[$key] ??= $actor !== null
            && method_exists($actor, 'hasRole')
            && $actor->hasRole((string) config('shield.super_admin_role', 'super-admin'));
    }

    /**
     * The ONLY way to change tenant. There is no bare setter, and the previous
     * binding is restored in a finally so an exception cannot leak a tenant into
     * the rest of the request.
     */
    public static function for(Model|int|string|null $tenant, Closure $callback): mixed
    {
        $wasOverridden = self::$overridden;
        $previous = self::$override;
        $previousModel = self::$overrideModel;

        self::$overridden = true;
        self::$override = $tenant;
        self::$overrideModel = $tenant instanceof Model ? $tenant : null;

        try {
            return $callback();
        } finally {
            self::$overridden = $wasOverridden;
            self::$override = $previous;
            self::$overrideModel = $previousModel;
        }
    }

    /** Audited escape hatch: runs the callback with no tenant predicate at all. */
    public static function without(Closure $callback): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        if (function_exists('activity')) {
            try {
                activity('tenancy')
                    ->withProperties(['actor' => Auth::id(), 'tenant' => self::id()])
                    ->log('tenancy.bypass');
            } catch (\Throwable) {
                // Auditing must never be the reason a request fails.
            }
        }

        $wasEnabled = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $wasEnabled;
        }
    }

    /**
     * The single tenant predicate. Every hand-rolled query site that cannot go
     * through an Eloquent global scope calls this and nothing else.
     */
    public static function apply(Builder|\Illuminate\Database\Query\Builder $query, string $table): void
    {
        if (! self::enabled()) {
            return;
        }

        if (config('myra.tenancy.super_admin_bypass', true) && self::actorIsSuperAdmin()) {
            return;
        }

        $id = self::id();
        $col = $table.'.'.self::column();

        // FAIL CLOSED. No tenant resolved means no rows, never all rows.
        if ($id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (config('myra.tenancy.null_rows') === 'shared') {
            // The nested closure is mandatory: an unwrapped orWhereNull escapes
            // every predicate already on the builder and leaks the whole table.
            $query->where(fn ($q) => $q->where($col, $id)->orWhereNull($col));

            return;
        }

        $query->where($col, $id);
    }

    /**
     * The membership variant, for tables whose rows belong to a tenant through
     * the team pivot rather than through a tenant column — `users` above all.
     * Same two locks, same fail-closed behaviour.
     */
    public static function applyMembership(
        Builder|\Illuminate\Database\Query\Builder $query,
        string $table,
        string $userKeyColumn = 'id',
    ): void {
        if (! self::enabled()) {
            return;
        }

        if (config('myra.tenancy.super_admin_bypass', true) && self::actorIsSuperAdmin()) {
            return;
        }

        $id = self::id();

        if ($id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $pivot = (string) config('myra.tenancy.membership_table', 'team_user');
        $pivotUserKey = (string) config('myra.tenancy.membership_user_key', 'user_id');
        $column = self::column();

        $query->whereExists(fn ($q) => $q->from($pivot)
            ->whereColumn($pivot.'.'.$pivotUserKey, $table.'.'.$userKeyColumn)
            ->where($pivot.'.'.$column, $id));
    }

    /**
     * The tenant predicate for a query built from a model class, for the query
     * sites that do not go through the model's own global scope.
     *
     * Order matters and every branch is deliberate: a model whose tenant scope
     * is already active needs nothing; a table that belongs through the pivot
     * is scoped by membership; a table carrying the tenant column is scoped by
     * it; anything else FAILS CLOSED unless the operator has explicitly listed
     * it as tenant-shared. Silently unscoped is the one outcome not on offer.
     */
    public static function applyForModel(
        string $modelClass,
        Builder|\Illuminate\Database\Query\Builder $query,
        string $table,
    ): void {
        if (! self::enabled() || self::scopes($modelClass)) {
            return;
        }

        if (in_array($table, (array) config('myra.tenancy.membership_tables', ['users']), true)) {
            self::applyMembership($query, $table);

            return;
        }

        if (in_array($table, (array) config('myra.tenancy.shared_tables', []), true)) {
            return;
        }

        if (self::tableHasColumn($modelClass, $table)) {
            self::apply($query, $table);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** Unique within the tenant. Degrades to a plain Rule::unique when disabled. */
    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        $rule = Rule::unique($table, $column);

        if (! self::enabled()) {
            return $rule;
        }

        $id = self::id();
        $col = self::column();

        // A row created without a tenant collides only with other tenant-less rows.
        return $rule->where(fn ($q) => $id === null
            ? $q->whereNull($col)
            : $q->where($col, $id));
    }

    /** Exists within the tenant. Degrades to a plain Rule::exists when disabled. */
    public static function exists(string $table, string $column = 'NULL'): Exists
    {
        $rule = Rule::exists($table, $column);

        if (! self::enabled()) {
            return $rule;
        }

        $id = self::id();
        $col = self::column();
        $shared = config('myra.tenancy.null_rows') === 'shared';

        return $rule->where(function ($q) use ($id, $col, $shared) {
            if ($id === null) {
                $shared ? $q->whereNull($col) : $q->whereRaw('1 = 0');

                return;
            }

            $shared
                ? $q->where(fn ($w) => $w->where($col, $id)->orWhereNull($col))
                : $q->where($col, $id);
        });
    }

    /**
     * Non-production boot invariant, mirroring GlobalSearch::assertScoped. A
     * model listed for scoping that lacks the trait or the column is a silent
     * hole, so outside production it is a hard error instead.
     */
    public static function assertConfigured(): void
    {
        if (self::$asserted || ! self::enabled() || app()->environment('production')) {
            return;
        }

        self::$asserted = true;

        foreach ((array) config('myra.tenancy.models', []) as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                throw new TenancyConfigurationException(
                    'myra.tenancy.models lists an unknown class ['.var_export($class, true).'].',
                );
            }

            if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                throw new TenancyConfigurationException(
                    "[{$class}] is listed for tenant scoping but does not use BelongsToTenant.",
                );
            }

            try {
                /** @var Model $instance */
                $instance = new $class;
                $hasColumn = Schema::connection($instance->getConnectionName())
                    ->hasColumn($instance->getTable(), self::column());
            } catch (\Throwable) {
                return; // DB unavailable at boot — nothing to assert against.
            }

            if (! $hasColumn) {
                throw new TenancyConfigurationException(
                    "[{$class}] is listed for tenant scoping but ".$instance->getTable()
                    .' has no '.self::column().' column.',
                );
            }
        }
    }

    /** Tests only. Callers must also call Model::clearBootedModels(). */
    public static function flush(): void
    {
        self::$enabled = null;
        self::$overridden = false;
        self::$override = null;
        self::$overrideModel = null;
        self::$resolved = [];
        self::$superAdmin = [];
        self::$asserted = false;
        self::$hasColumn = [];
    }

    private static function fromActor(): ?Model
    {
        $actor = Auth::user();
        $key = self::actorKey($actor);

        if (array_key_exists($key, self::$resolved)) {
            return self::$resolved[$key];
        }

        return self::$resolved[$key] = self::resolveFor($actor);
    }

    private static function resolveFor(?Authenticatable $actor): ?Model
    {
        if ($actor === null || ! method_exists($actor, 'teams')) {
            return null;
        }

        $id = $actor->getAttribute('current_team_id');

        if (empty($id)) {
            return null;
        }

        // Membership is re-checked here, not trusted from the column: a user
        // removed from a team must stop seeing it without a re-login.
        if (! $actor->teams()->whereKey($id)->exists()) {
            return null;
        }

        return (self::model())::query()->whereKey($id)->first();
    }

    /** Schema introspection, cached per table per process. */
    public static function tableHasColumn(string $modelClass, string $table): bool
    {
        if (array_key_exists($table, self::$hasColumn)) {
            return self::$hasColumn[$table];
        }

        try {
            $connection = class_exists($modelClass) && is_subclass_of($modelClass, Model::class)
                ? (new $modelClass)->getConnectionName()
                : null;

            return self::$hasColumn[$table] = Schema::connection($connection)->hasColumn($table, self::column());
        } catch (\Throwable) {
            return false;
        }
    }

    private static function actorKey(?Authenticatable $actor): string
    {
        return $actor === null ? '@guest' : (string) $actor->getAuthIdentifier();
    }
}
