# Admin URL prefix

Every admin page sits under one configurable URL segment. It defaults to
`/dashboard`.

```
/dashboard              → the dashboard
/dashboard/users        → admin.users.index
/dashboard/roles        → admin.roles.index
/dashboard/settings     → admin.settings.index
```

## Changing it

Set one env var and clear the config cache:

```dotenv
MYRA_ADMIN_PREFIX=console
```

```bash
php artisan config:clear && php artisan route:clear
```

Every admin route moves to `/console/...`. Nothing else needs editing.

Or edit `config/myra.php` directly:

```php
'admin' => [
    'prefix' => env('MYRA_ADMIN_PREFIX', 'dashboard'),
    'legacy_redirect' => env('MYRA_ADMIN_LEGACY_REDIRECT', true),
    'legacy_prefix' => 'admin',
],
```

A blank prefix falls back to `dashboard` rather than mounting the admin at `/`,
which would collide with the public homepage.

## Route names never change

Route **names** stay `admin.*` regardless of the prefix. That is the whole point:
the ~370 `route('admin.users.index')` call sites across the app and test suite
keep working when the URL moves.

```php
route('admin.users.index');   // /dashboard/users  … or /console/users
```

**Always build admin URLs from a route name.** Reach for a path helper only where
no name is available:

```php
use App\Support\Myra;

Myra::adminPrefix();              // 'dashboard'
Myra::adminPath();                // '/dashboard'
Myra::adminPath('landing/builder'); // '/dashboard/landing/builder'
```

```ts
import { adminPath } from '@/lib/adminPath';

adminPath('landing/builder');     // '/dashboard/landing/builder'
```

The TypeScript helper reads the `adminPrefix` prop shared by
`HandleInertiaRequests`, and falls back to `dashboard` outside an Inertia app
(unit tests, SSR probes).

## Registering routes in the admin group

Plugins and generated code should never re-declare the prefix or the middleware
stack:

```php
Myra::adminRoutes(function () {
    Route::get('/widgets', [WidgetController::class, 'index'])->name('widgets.index');
});
```

## Legacy `/admin` links

With `legacy_redirect` on (the default), a GET to `/admin/...` 302s to the
current prefix, query string preserved:

```
GET /admin/users?page=2  →  302  /dashboard/users?page=2
```

It is GET-only on purpose: a 302 turns a POST into a GET, so redirecting writes
would silently drop the request body. A POST to a legacy URL gets a 405 rather
than a no-op.

While the redirect is on, an unknown `/admin/...` path redirects instead of
404-ing. Turn it off once nothing links to `/admin` any more:

```dotenv
MYRA_ADMIN_LEGACY_REDIRECT=false
```

## Guardrails

`tests/Feature/AdminPrefixTest.php` asserts that every route named `admin.*`
actually resolves under the configured prefix — it is what caught
`admin.stop-impersonate`, which was declared with a literal URI outside the
group. Add routes via `Myra::adminRoutes()` or the existing groups and it stays
green.
