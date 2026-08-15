# Changelog

All notable changes to the Myra Starter Kit are documented here.

## Unreleased

### Added — Table actions
- **`ReplicateAction`** — duplicate a record from the row menu with `.except()` / `.only()` / `.withRelations()` / `.overrides()` / `.suffix()`, or `.schema()` to edit before saving. Server side: `App\Admin\Traits\Replicates` (relation names whitelisted against the model's `$replicable`, overrides against `$fillable`, record fetched through the ownership scope, one level of relations only).
- **`RestoreAction` / `ForceDeleteAction`** — first-class actions with `deleted_at`-aware visibility defaults. `DeleteAction` gained the inverse default.
- **`softDeleteActions()` / `softDeleteBulkActions()` / `trashedFilter()`** — the whole trash workflow in three calls; replaces the hand-rolled `getRowActions()` on the Articles, Pages and Users index pages.
- **Action grouping** — `ActionGroup` with `label/icon/color/size/button/buttonGroup/tooltip/badge/placement/width/maxHeight/collapseAfter/permission`, plus `ActionDivider` and `ActionSectionLabel`; nested groups render as submenus. Orphan dividers collapse after permission filtering.
- **Generic action request path** — `Action.route(name, method)`, `.routeParams()`, `.payload()`, `.successMessage()`, `.badge()`, `.tooltip()`, `.external()`.

### Fixed
- `requiresConfirmation()` is now honoured for every row action, not only deletes, and `DeleteAction.confirmTitle()` / `.confirmDescription()` are no longer discarded by `DataTable`.
- `a.color` and `external` are now consumed by `RowActions` (both were stored and dropped).

### Security
- Bulk endpoints are gated on `{module}.edit` but accept destructive verbs. Every `bulkAction()` now authorizes per verb (`force_delete`/`delete` → `{module}.delete`) via `App\Admin\Traits\HandlesSoftDeletes`.
- `UserController::bulkAction()` now runs the same per-user `authorizeManage()` guard as the single-record path, and `restore()` / `forceDelete()` guard the target user as well.
- Single restore / force-delete endpoints re-check the ability in the controller in addition to the route middleware.

## v2.0.0 — 2026-06

A major release focused on Filament-style developer experience, security hardening, an automated test suite + CI, and an interactive map toolkit.

### Added — Scaffolding CLI (build dashboards with little code)
- `make:myra-page {Name}` — single admin page (controller + Vue) with auto-wired route, nav, and `view` permission.
- `make:myra-resource {Name} [--model]` — full CRUD (controller, service, 3 Vue pages) + 4 permissions + 6 routes + nav.
- `make:myra-component {Name} {keyword}` — clone any Feature Demo (form-builder, data-table, wizard, infolist, widgets, map, …) into a runnable page.
- `make:myra-setting {Name}` — Spatie settings group: Settings class + seeding migration + controller + settings page + routes/nav/permissions.
- `make:myra-export {Model}` — streaming CSV export controller (ExportableQuery) + route.
- `make:myra-import {Model}` — CSV import controller (preview + execute with column mapping) + routes.
- `make:myra-policy {Model}` — model policy mapping to Shield `{prefix}.view/create/edit/delete` permissions (auto-discovered).
- `make:myra-user` — create an active, verified user interactively (`--admin` for super-admin).
- `shield:generate` — sync Spatie permissions from `config/shield.php`.
- `content:sanitize` — backfill server-side HTML sanitization over stored content.
- `myra:about` — read-only status (version, RBAC modules, settings groups, generators, counts).

### Added — UI / builder features
- **Block Builder** field (`Builder` + `Block`) — multi-type repeatable content blocks (Filament-style); value `[{ type, data }]`.
- **Searchable / async Select** (`Select.searchable()` / `.optionsUrl()`) — combobox with client-side filter or debounced endpoint loading for relationship pickers.
- **Maps** — theme-aware MapLibre GL components (`Map`, `MapControls`, `MapMarker`, `MapPopup`, `MapRoute`, `MapArc`, `MapCluster`) + a Malaysia-themed demo (flight arcs, markers, route, clustering).
- **AI assistant** — pluggable provider (OpenAI / Anthropic / OpenRouter / Ollama) streamed into the rich text editor; AI settings tab.
- **i18n** (en / ms / zh) with a language switcher; image editor; XLSX export.

### Added — Security / RBAC
- **Shield RBAC** — `{module}.{ability}` permissions from `config/shield.php`; convention middleware; super-admin `Gate::before` bypass.
- **Per-user data isolation** — `OwnedByUser` global scope (Articles/Pages/Categories), media scoped to uploader, query-scoped Users/Email Templates; public views exempt.
- **Role/user protections** — role `active`/`visible` toggles, super-admin/admin management guards, privileged-role assignment restricted to super-admin.
- **2FA enforced after login**; suspended/pending users blocked at login and mid-session.
- Server-side HTML sanitization, SVG-upload block, CSV formula-injection escaping, whitelisted sort columns + capped pagination, Content-Security-Policy + HSTS, production-safe seeder.
- Editable login tagline + server-enforced sign-up toggle.

### Added — Quality
- **PHPUnit test suite** across modules + security paths (unit + feature), sqlite `:memory:`.
- **GitHub Actions CI** (PHP tests, frontend type-check/build, dependency audit) with a status badge.

### Changed / Fixed
- Dashboard user-growth query made portable (MySQL / PostgreSQL / SQLite).
- CSP allows MapLibre's `blob:` worker so maps render in production.
- Removed the private `myra/framework` dependency — the repo now installs from a clean `git clone` + `composer install` with no token.
- Documentation (`/docs`) and README updated throughout, including screenshots.

## v1.x
- Initial starter kit: Laravel 12 + Inertia + Vue 3 admin, schema-driven form/table/infolist/widget builders, CMS (articles/pages/categories), email system, backups, media, activity log, notifications, homepage builder, theme system.
