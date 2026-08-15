# Changelog

All notable changes to the Myra Starter Kit are documented here.

## [2.2.0]

### A — C1b follow-up defects

- **Inline (markdown) image upload.** New `InlineUploadController` + `admin.uploads.inline` /
  `admin.uploads.inline.show` routes. Files land on the **private** `local` disk under
  `inline/{userId}/{ulid}.{ext}` — nothing is written to `public/`. `mimes:` is not trusted:
  `getimagesize()` re-checks the magic bytes and a `.png`-named text file is a 422. Reads are
  authorised as *owner OR `media.view`*, and a miss is a **404, never a 403**, so the endpoint is
  not an existence oracle. Responses carry `nosniff`, `Content-Disposition: inline` and a
  `default-src 'none'; sandbox` CSP. `MarkdownEditor.uploadRoute()` now defaults to the shipped
  route and gains `.maxUploadKb()` / `.acceptedUploadTypes()`; the editor pre-checks both, sends
  `X-CSRF-TOKEN` + `Accept: application/json` with `credentials: 'same-origin'`, exposes a
  `role="status"` uploading line and disables the image button while a request is in flight.
  Limits live in `config('myra.uploads')`.
- **`ReplicateAction.schema()` keeps the user's edits.** `ActionModal` gained `payloadKey` and
  `extraPayload`; the modal now posts `{except, only, relations, suffix, redirect_to, overrides:
  {...row overrides, ...form values}}` instead of bare form values, so the schema fields actually
  reach `Replicates::replicate()`. Form values win on collision. New `ReplicateAction.fillFrom()`
  seeds the modal separately from `overrides()`. The server whitelist is unchanged — an override
  outside `fillable` is still dropped.
- **`ActionGroup.permission()` gates a root-level group.** New exported `resolveActionItems()`
  returns `rootGroup: null` and an empty item list when the single top-level group's permission is
  denied, instead of painting an empty trigger. `ActionGroup` also gained `visibleWhen()` /
  `hiddenWhen()`, evaluated per row.
- **Toggle `min()` / `max()` produce a rule.** `BaseField.rules()` added on every field;
  `ToggleGroupField` / `ToggleButtons` / `CheckboxList` auto-derive `['array', 'min:n', 'max:n']`
  under any explicit `.rules()`. `FormField` renders an advisory `validation.selectBetween` message
  wired via `aria-describedby` with `aria-invalid` on the group container.
- **i18n for the v2.1.0 strings.** New top-level `table` namespace (`table.filters`, `table.active`,
  `table.updateFailed`, `table.scope.page`, `table.upload.*`) plus `common.copiedToClipboard` and
  `validation.selectBetween`, in `en` / `ms` / `zh`. Replaces the hardcoded English in
  `DataTable.vue`, `ActionModal.vue` and `ColorColumn`'s copy message.
- **Inline updates are a real partial visit.** `only: []` is a *full* visit in Inertia; inline edits
  now send `only: props.inlineReloadProps` (default `['flash']`) with `replace: true`, so an inline
  edit no longer refetches the page or stacks a history entry. Pages needing server-computed
  footers pass `:inline-reload-props="['flash', 'summaries']"`.
### B — Saved views + column manager

#### Added
- **Saved table views** — `TableView.make(name)` declares a view on the page (`search`, `filters`,
  `dateRange`, `query`, `sort`, `perPage`, `columns`, `columnOrder`, `default`, `permission`), and a
  user-saved view arrives from the server in the same `SavedView` shape, so one menu drives both.
  `useTableViews()` exposes `all`, `active`, `isModified`, `applyView`, `saveAs`, `updateActive`,
  `rename`, `remove`, `makeDefault` and `shareUrl`.
- `table_views` table (additive migration), `App\Models\TableView` with a `visibleTo` scope,
  `App\Policies\TableViewPolicy` (team views are readable by teammates, writable only by their
  author), `TableViewRequest`, and `Admin\TableViewController` with index/store/update/destroy/
  makeDefault under `admin.table-views.*`.
- `App\Admin\Views\ViewShape` — shape and size validation for the opaque `payload.query` blob
  (25 rules, depth 3, 16 KB). It deliberately performs no field or operator whitelisting: a saved
  view is replayed as URL query params through the same controller path as a live filter.
- **Column manager** — `useColumnManager()` plus `admin/ColumnManager.vue`: search-within-list,
  drag reorder (native HTML5, no dnd library), `Alt`+arrow keyboard reorder announced through an
  `aria-live` region, group headers, and Reset. On by default; opt out with `:column-manager="false"`.
- `admin/TableViewsMenu.vue`, `resources/js/types/table-views.d.ts`, `config/myra.php` `views.max`,
  the `views.*` / `columns.*` i18n namespaces in `{en,ms,zh}.json`, and a `Demo/SavedViews.vue` page.

#### Changed
- `DataTable.vue` — `applyFilters()` split into `buildParams()` / `captureState()` / `applyView()`;
  `per_page` is now sent (the server already read and capped it); the column-visibility store moved
  from a bare `Record<string, boolean>` to `{ v: 2, visible, order }`, migrated in place on read;
  the storage key gained the query prefix so two tables on one page no longer collide. New props:
  `tableKey`, `savedViews`, `views`, `columnManager`, `canShareViews`.
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
### D — query builder + global search

**Added**
- `App\Admin\QueryBuilder`: a closed `Operator` enum (28 cases), `FieldSpec`/`FieldSet` whitelist,
  `RuleTree` parser and `RuleCompiler`. `HandlesQueryBuilder::applyQueryBuilder()` is the single
  entry point; the field set is always a controller-side literal.
- `App\Support\Sql::like()` — escapes `%`, `_` and `\` in all four match modes.
- `App\Admin\Search`: `SearchSource`, `GlobalSearch`, `Scorer` and `Sources`, registered from
  `GlobalSearchServiceProvider`. Weighted ranking (`max(weight × matchKind) + recency boost`),
  per-source ownership scopes, per-source and global result caps, and command-palette page entries.
- Client: `Constraint` classes (`Text/Number/Boolean/Date/Select/Relation`) with
  `QueryBuilderFilter.constraints()` / `.fromColumns()` / `.maxRules()` / `.maxDepth()`;
  `QueryBuilderRule.vue`, `QueryValueInput.vue`, `CommandPalette.vue`, `SearchHighlight.vue`;
  `types/query-builder.d.ts`.
- `filters.*` and `search.*` i18n namespaces in `en`, `ms` and `zh`.
- Additive, driver-guarded migration `2026_08_16_000004_add_search_indexes`.

**Security**
- The query builder fails closed. An unknown field, an operator the field does not allow, a value
  outside a `select`'s options, a bad cast, an arity mismatch, more than `maxRules` rules, nesting
  past `maxDepth` or a tree over 16 KB is a **422** — never a silently unfiltered result set. A
  field the actor lacks permission for reuses the unknown-field message so it cannot be used as an
  enumeration oracle.
- `RuleCompiler` wraps the whole tree in one outer `where()`, so a top-level `or` can never escape
  an ownership scope already applied to the builder. Column names come only from
  `FieldSpec::column()`, validated against an identifier regex at construction.
- `SearchableQuery::applySearchAndPaginate()` now escapes LIKE wildcards and caps the requested page
  number. The signature is unchanged.
- `SearchController`'s unwrapped `orWhere` chain is replaced by a registry whose OR set is always
  wrapped; term length is clamped to 2..100 and the route gains `throttle:60,1`.
- Registering a search source on a model with a `created_by` column but no `scope()` throws
  `MissingSearchScopeException` outside production.
- Search-result highlighting is rendered from server-supplied offset ranges into `<mark>` runs —
  never `v-html`.

**Fixed**
- `useGlobalSearch` had no request cancellation: a slow early response could overwrite a newer one.
  Each keystroke now aborts the in-flight request and a sequence guard discards stale responses.
- `QueryBuilderGroup` was capped at one nesting level, showed hardcoded English operator labels and
  used a `<Badge @click>` for the conjunction. It is now depth-configurable, fully i18n'd, and the
  conjunction is a `<button type="button" aria-pressed>`; every rule row is a labelled `role="group"`
  and the Add Rule / Add Group buttons disable at their caps.
- `DemoController::advancedFilters` filtered an in-memory Collection; it now runs against a real
  Eloquent model through the compiler, and the Collection rule evaluators are deleted.

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
