# Changelog

All notable changes to the Myra Starter Kit are documented here.

## [2.2.0]

### C — Streaming export + import hardening

#### Security
- **Export data leak closed.** `UserController::exportCsv` built its own
  `User::with('roles')->get()`, bypassing `UserService`'s `created_by` ownership scope and the
  super-admin exclusion — any holder of `users.view` could export every user in the system. It now
  streams `UserService::exportQuery()`, the same scoped query the listing paginates. Covered by
  `tests/Feature/ExportScopeTest.php`.
- Import mapping keys are whitelisted against the declared column names, and mapping values against
  the staged headers. Previously any client key reached the model and only a hand-written field list
  stood between an import and mass assignment.
- TOCTOU closed: `preview()` stages the upload once under `imports/{userId}/{ulid}.csv` on the
  private disk and hands back a ULID token; `validate` and `commit` re-read from the path derived
  from that token, never from the request. The committed bytes are always the previewed bytes.
- Per-record authorization is mandatory — `ImportDefinition::assertConfigured()` throws when
  `authorizeRow()` is missing, so an import cannot silently skip the guards the single-record path
  applies. `UsersImport` reproduces the role-privilege guard.
- All three import write endpoints are rate limited (`throttle:10,1`); they had none. The per-resource
  ability is resolved from the registry rather than hardcoded to `users.create`.
- `Csv::filename()` strips everything outside `[A-Za-z0-9._-]`, collapses `..` and truncates to 80
  chars, so a filename cannot traverse a path or inject a `Content-Disposition` header.
- Formula-injection escaping (`Csv::cell`) is unconditional in **both** CSV and XLSX output.

#### Added
- `App\Admin\Export\ExportDefinition` + `ExportColumn` — declarative exports with `state()`,
  `formatStateUsing()`, `date()`, `counts()`, `sum()`, `limit()`, `sensitive()`,
  `enabledByDefault()`. Client column picks are intersected with the declared set, never trusted.
- `ExportableQuery::streamExport()` — keyset (`lazyById`) iteration, so an export is constant-memory
  and stable under concurrent writes; `chunk()`'s OFFSET paging skips and duplicates rows mid-export.
  `preserveSort()` opts into `cursor()` and lowers `maxRows` to 20 000.
- Over `maxRows` the request **422s**; it never returns a truncated file the user mistakes for
  complete.
- Streaming XLSX writer (`XlsxRowWriter`) — inline strings written to a temp sheet stream, zipped and
  piped out. Dependency-free (ZipArchive); swap in OpenSpout later without touching callers.
- `App\Admin\Import\{ImportDefinition, ImportColumn, ImportRegistry, ImportSession, HeaderMapper}`
  and a four-endpoint pipeline: `preview` → `validate` → `commit` (resumable) → `failures`.
  **No migrations, no queue, no notifications table.**
- Resumable commit: one chunk per request inside a transaction, returning the byte offset to resume
  from. Memory is O(chunk); a killed request resumes where it stopped and never leaves a half-written
  row. `chunkSize` is the future job boundary.
- Server-side auto-mapping in four passes: exact → normalised → declared `guess()` aliases →
  Levenshtein ≤ 2.
- `validate` dry-runs rows and returns per-cell errors; failures accumulate to a downloadable CSV
  (capped at 1000 rows, after which the run reports `aborted`). `sensitive()` columns are `***`.
- `ImportErrorGrid.vue` — a real `<table>` with `<caption>`, `scope="col"`, `aria-invalid` +
  `aria-describedby` on offending cells, and an icon + text so colour is never the only signal.
- `useImportRunner` — the commit loop, with Resume that continues from the stored cursor rather than
  restarting.
- `make:myra-import` now generates an `ImportDefinition` and registers it in `config/myra.php`;
  `make:myra-export` emits an `ExportDefinition`.
- `transfer.*` i18n namespace in `{en,ms,zh}.json`, plus `lang/{en,ms,zh}/transfer.php` for the
  server-side messages.

#### Changed
- `ExportDropdown` takes a route **name** and reads `window.location.search` itself, so "Export CSV"
  finally carries the active filters — it silently exported everything before. Adds a column picker.
- The browser XLSX item is relabelled "this page only" and `useExcelExport` refuses above
  `MAX_CLIENT_ROWS = 5000`, pointing at the server route.
- `ImportModal` steps become upload → map → validate → import → result, with a `role="progressbar"`
  and a polite live region.
- The `if (count($errors) >= 10) break;` bail-out is gone — it stopped importing while reporting
  `imported` as if it were the truth.
- `EmailLogController` / `ActivityLogController` exports drop `->latest()->get()` for the streamed
  path; `UserService`, `ArticleService` and `PageService` expose `exportQuery()`.

#### Deferred
- Queued/batched export and import above a threshold, PDF format, scheduled exports, "revert this
  import".

## v2.1.0 — 2026-08

Filament v5 parity cluster: the remaining form fields, table columns, infolist entries and record
actions, plus a JS test suite (vitest) alongside the PHP one.

### Security
- `Replicates` — `overrides` was whitelisted against the model's full `$fillable`, which includes
  `created_by`. Any user with `{module}.create` could replicate their own record and hand ownership
  to another user, bypassing the `OwnedByUser` isolation. `created_by` is now guarded and the
  override whitelist is `fillable` minus the guarded set; the replica is re-stamped with the acting
  user. Covered by `ReplicateTest::test_replicate_cannot_repoint_ownership_via_overrides`.
- `HandlesInlineUpdates` used `$this->authorize()`, which fatals because the base `Controller` does
  not compose `AuthorizesRequests`; now `Gate::authorize()`.

### Fixed
- Inline-edit optimistic writes were never rolled back on a 403/500 — Inertia's `onError` only fires
  for validation responses, so the rollback moved to `onFinish` behind a success flag.

### Added — Table columns
- **`ColorColumn`** — validated colour swatch + copyable value. Values are matched against a strict colour regex before reaching an inline `style`; anything else renders as plain text. Alpha colours sit on a themed checkerboard. `.swatchOnly()`, `.circular()`, `.swatchSize(px)`, `.copyable()`, `.copyMessage()`.
- **`CheckboxColumn`** — inline-editable boolean with `.indeterminateWhen(fn)`.
- **`InlineEditableColumn`** — shared base for toggle / checkbox / select / text-input columns. `.updateRoute()` hands the write to the table (optimistic paint, rollback + toast on failure, in-flight de-duplication, debounce cleanup on unmount); `.field()`, `.optimistic()`, `.permission()`, `.disabledWhen()`, `.confirmWhen()`, `.rowLabel()`, `.debounce()`, and `.onUpdate()` as the escape hatch.
- **`HandlesInlineUpdates`** trait — server half of inline editing: whitelisted field names, cast per field, `authorize('update', $model)` on top of the route middleware.
- **Summaries** — `min`, `max`, `median` added to `sum` / `average` / `count` / `range` / `custom`, plus `.summary({ type, label, prefix, suffix, decimals, currency, locale, excludeNull, separator, scope })`. Client-computed footers carry a "Page" badge so page-scoped numbers are never mistaken for dataset totals. `SearchableQuery::summarise()` computes them server-side.

### Changed
- `DataTable` renders every cell through a single `admin/TableCell.vue`; grouped rows previously fell back to raw text for image, icon, toggle, select and text-input columns.
- `SimpleTable` accepts schema columns (`TextColumn`, `ColorColumn`, …) alongside the legacy `{ key, label, class }` shape.
- Summary computation is a single pass per column (no `Math.min(...array)` spread, which threw past ~65k rows).
### Added — Infolist entries
- **`ColorEntry`** — colour swatch + literal value with a copy button, sharing `ColorSwatch.vue` with `ColorColumn`. `.copyable()`, `.copyMessage()`, `.showValue()`, `.swatchOnly()`, `.swatchSize()`, `.circular()`. Values are validated against a strict colour regex before reaching an inline `style`; anything else renders as plain text.
- **`CodeEntry`** — read-only, syntax-highlighted code block. `.language()`, `.lineNumbers()`, `.wrap()`, `.maxLines()`, `.startLine()`, `.highlightLines()`, `.filename()`, `.copyable()`. Renders through the lazy `CodeBlock.vue`, which uses the read-only `highlightToHtml()` half of `useCodeMirror` (no `EditorView`), gates highlighting on `IntersectionObserver`, and sanitises all markup before `v-html`. Object/array values are pretty-printed as JSON automatically.
- `useInfolistSchema` re-exports `CodeLanguage` from `useFormSchema`, so infolist entries and the `code` form field share exactly one language list.
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
