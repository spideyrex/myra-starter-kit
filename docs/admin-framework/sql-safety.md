# SQL Safety — building a LIKE predicate

`App\Support\Sql::whereLike()` is the **only** sanctioned way to build a LIKE predicate in this
codebase. A guard test fails CI if anything under `app/` bypasses it.

---

## Use this

```php
use App\Support\Sql;

Sql::whereLike($query, 'name', $request->string('q'));                    // contains
Sql::whereLike($query, 'mime_type', $type, 'starts');                     // starts | ends | exact
Sql::orWhereLike($query, 'email', $term);                                 // OR-joined
```

## Never this

```php
$query->where('name', 'like', "%{$term}%");          // unescaped: a '%' matches every row
$query->where('name', 'like', Sql::like($term));     // escaped, but no ESCAPE clause — inert on SQLite
```

## Why both halves matter

`Sql::like()` escapes `%`, `_` and `\` so a user's wildcard cannot widen the match or force a full
table scan. But the escaped pattern is only **half** of the contract — it has to travel with an
explicit `ESCAPE` clause:

- **SQLite** has *no default escape character*. Without `ESCAPE`, `\%` matches a literal backslash
  followed by a percent, so an escaped search silently returns nothing.
- **MySQL/MariaDB** treat backslash as the default escape, so the same code appears to work.

That asymmetry is why this shipped broken once: the escaping was correct on production MySQL and
inert on the SQLite test database. `whereLike()` emits the pattern *and* the clause together, so the
two halves cannot drift apart.

### Driver-aware escape literal

MySQL and MariaDB reprocess backslashes inside string literals, so the escape character is emitted
doubled there — **unless** `sql_mode` contains `NO_BACKSLASH_ESCAPES`, where the doubled form is a
two-character string that MySQL rejects as an `ESCAPE` argument. `Sql` probes `@@sql_mode` once per
connection, caches the result, and falls back to standard behaviour if the probe errors.

SQLite, PostgreSQL (with `standard_conforming_strings`) and SQL Server take the literal as written.

## Column safety

The predicate is raw SQL, so the identifier is validated against
`/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/` and then passed through the grammar's `wrap()`. Anything else
throws `InvalidArgumentException`. The search term always stays a bound parameter.

## History

Six call sites interpolated raw user input into a LIKE pattern with no escaping on any driver —
`UserService::exportQuery()`, `AdminNotificationController`, `ActivityLogController`,
`EmailLogController`, `MediaController` and `HasRelationManagers`. All were migrated in **v2.2.1**
and are covered by `tests/Feature/Security/LikeEscapingTest.php`.

---

## Related

- [PHP Backend](php-backend.md) — `SearchableQuery` and the controller layer
- [Table Builder](table-builder.md) — the query builder's own operator whitelist
