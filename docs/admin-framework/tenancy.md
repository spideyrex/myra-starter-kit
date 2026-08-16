# Multi-tenancy (v2.4)

Opt-in, default off. With `MYRA_TENANCY` unset the framework behaves exactly as it did
in v2.3: no global scope is registered, no creating hook is attached, no query changes.

## Two locks

Scoping happens only when **both** are true:

1. `config('myra.tenancy.enabled') === true`
2. the model class is listed in `config('myra.tenancy.models')`

Adding `BelongsToTenant` to a model is not enough. `bootBelongsToTenant()` returns
*before* `addGlobalScope` and *before* `static::creating`, so the disabled path registers
nothing at all — a registered-but-inert scope would still mutate the builder and still
appear in `getGlobalScopes()`.

`enabled` is compared with `===`, so `MYRA_TENANCY` must be literally `true`. Anything
else, including `1`, leaves tenancy off.

## Fail closed

When scoping is active and no tenant resolves, the predicate is `1 = 0`. No tenant means
no rows, never all rows. The deleted `BelongsToTeam` trait did the opposite: it scoped
nothing when `current_team_id` was NULL, which is every user on a single-tenant install.

## Where the tenant comes from

`users.current_team_id`, with membership re-validated on every request
(`$user->teams()->whereKey($id)->exists()`). Never from a route, header or query
parameter. There is no setter — `Tenancy::for($tenant, $callback)` is the only way to
change it, and it restores the previous binding in a `finally`.

## Row shapes

Most tables carry the tenant column (`team_id`) and are scoped by
`Tenancy::apply($query, $table)`.

`users` belongs to a tenant through the `team_user` pivot, so it is scoped by
`Tenancy::applyMembership($query, 'users')` instead. The table list lives in
`myra.tenancy.membership_tables`.

`Tenancy::applyForModel()` picks between them, and **fails closed** for a table that has
neither — unless the operator has deliberately listed it in `myra.tenancy.shared_tables`.

## Null rows

`null_rows = 'strict'` (default) hides rows whose tenant column is NULL from everyone but
a super-admin. `'shared'` shows them to every tenant, with the OR set wrapped in a nested
closure — an unwrapped `orWhereNull` escapes every predicate before it and leaks the table.

## Before you enable it

```
php artisan myra:tenancy-audit
```

Read-only. Exits non-zero while any listed model has a null tenant row, is missing the
column or the trait, or has no index leading on the tenant column. Those rows would
silently disappear for everyone but a super-admin, so a blocked deploy beats a silent
visibility change.

## Queues

A worker has no session. A job that must stay inside its tenant uses
`App\Admin\Tenancy\Concerns\TenantAware` and calls `$this->captureTenant()` in its
constructor; `BindsTenant` job middleware re-binds it on the worker. A job that captured
nothing is left alone rather than pinned to "no tenant".

## Validation

`Tenancy::unique($table, $column)` and `Tenancy::exists(...)` scope the lookup to the
current tenant. With tenancy off they are byte-identical to `Rule::unique` / `Rule::exists`.

## Rollback without a deploy

```
MYRA_TENANCY=false
php artisan config:clear
```

Every migration in this release is an additive nullable column or an index; none needs a
`down()` to restore visibility.

## The proof

`tests/fixtures/tenancy-baseline.json` holds the compiled SQL of the canonical query per
model — as guest, as a non-super member and as a super-admin — captured by
`myra:tenancy-baseline` on the tree *before any tenancy code existed*.
`tests/Feature/Tenancy/DisabledPathIsNoOpTest` asserts the disabled path still produces
exactly those strings. **Do not regenerate that fixture to make a failure go away**: a
diff there is a production visibility change.
