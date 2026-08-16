# Changelog

All notable changes to the Myra Starter Kit are documented here.

## v2.4.0 — 2026-08 — "Extension, structure, tenancy, scale"

Four bundles, each shipped as an isolated, additive change. Every feature that could alter what a
user can see is opt-in and default off, and each such switch has a test asserting the disabled path
is unchanged.

### A — plugin system, `make:myra-plugin`, stub design pass

- **A plugin is one class.** `App\Admin\Plugin\MyraPlugin` declares `id()` and `manifest()`; the
  `Manifest` is the single place a plugin names its permissions, reports, imports, routes, policies,
  commands, migrations, translations and nav items. No package service provider, no per-panel
  registration call, no setter/getter pairs.
- **Registration is explicit.** `config('myra.extensions.plugins')` is a literal list. There is no
  composer `extra.*` auto-discovery, and that is deliberate: a plugin registers routes *inside* the
  admin middleware stack and merges entries into `config('shield.modules')`, so `composer update`
  must never be enough on its own to grant either.
- **A failing plugin is quarantined, not fatal.** `PluginRegistry::load()` catches per plugin,
  records the exception in `failed()`, reports it, and carries on — in *every* environment.
  `load()` runs from `register()`, so a rethrow there aborts the entire bootstrap (every route,
  every artisan command), not just the admin. `myra.extensions.strict` is therefore opt-in
  (`MYRA_PLUGINS_STRICT=true`, off by default) and rethrows only when you ask for it, e.g. in CI.
- `App\Support\Myra` — `Myra::adminRoutes()` registers into the admin group without re-declaring it,
  `Myra::publicRoutes()` is `web` only, plus `version()`, `plugin()`, `hasPlugin()`,
  `failedPlugins()`. `Myra::ADMIN_MIDDLEWARE` is a literal copy of the stack in `routes/web.php`, and
  `tests/Feature/Plugin/AdminMiddlewareParityTest` fails CI the moment the two drift — for both a
  core route and a plugin route.
- New `App\Providers\MyraServiceProvider` (the only new provider) applies manifests: a
  `array_replace_recursive` deep merge for `shield.modules` (a flat `mergeConfigFrom` would drop
  nested abilities), plain merges for report and import registries, then routes, policies, commands,
  migrations and translations at boot.
- New `php artisan make:myra-plugin {Name}` — writes the plugin class, `composer.json`, three locale
  files, a plain-PHPUnit manifest test and the empty migration/page directories; adds a PSR-4 entry
  to the root `composer.json` for an in-repo path, runs `composer dump-autoload`, and only then
  registers the class at the `// myra:plugins` marker in `config/myra.php`. If composer is missing
  or the dump fails it skips the config edit and tells you what to do, so the generator can never
  leave a declared-but-unautoloadable plugin class behind.
- Ships `App\Plugins\Example\ExamplePlugin` (id `myra-example`) — a real, listed plugin with one
  permission module, one admin route (`GET /admin/myra-example/ping`, an invokable controller so the
  route survives `route:cache`) and one nav item. The plugins demo page therefore always has a row.
- **Stub design pass.** `make:myra-resource` now emits, by default, everything that is universally
  safe: an `OwnedByUser` model whose `$fillable` deliberately excludes `created_by`; a migration with
  a nullable `created_by` foreign key and a `['created_by','created_at']` index; `Store…Request` /
  `Update…Request` form requests instead of an inline `validate()` placeholder;
  `Gate::authorize('{prefix}.{ability}')` as the first line of every controller method;
  `table-key="{prefix}"` on the generated `DataTable` (without it saved views were silently off); an
  explicit `sortable:` whitelist in the service; `t('generated.…')` throughout the Vue pages with the
  keys written into all three locales; and a feature test asserting the permission gates, ownership
  scoping and the sort whitelist. `--unscoped` and `--soft-deletes` cover the cases that need a
  decision about the data. `--group=` is finally read: it places the nav item in its own sidebar
  group instead of being accepted and ignored.
- `--print` is now a true dry run for `make:myra-resource` and `make:myra-page` — it renders every
  file and writes nothing, which is what `tests/Feature/Generators/StubDesignPassTest` asserts
  against. The assertions run on real generator output, never on a copy of a stub.
- `myra:about` gained a Plugins section (per-plugin surface counts, failures in red), a dynamic
  generator list, and sections that light up when the other bundles are present.
- New demo page `/admin/demo/plugins`, driven by `PluginRegistry`. Its props are captured by
  `PluginDemoPropsTest` into `tests/js/fixtures/plugin-demo.json`, and `tests/js/pluginDemo.spec.ts`
  mounts the real page against that same file.

### B — clusters, server-contributed navigation, nested and singular resources

- `App\Admin\Navigation\*` — `NavItem`, `NavGroup`, `Cluster` and a static `NavRegistry`. Clusters
  group resources under one collapsible sidebar entry and, optionally, one URL segment. Cluster
  membership merges from both directions: a cluster may enumerate its children and a resource may
  declare its cluster.
- **The sidebar addition is provably additive.** The nine hardcoded groups in
  `AuthenticatedLayout.vue` stay byte-identical; a new `myraNav` Inertia prop carries additions and
  serialises as `[]` when nothing is registered, so `[...core, ...[]]` is the identity operation.
  `tests/js/navIdentity.spec.ts` asserts that `myraNav: []` renders exactly the same list as the prop
  being absent entirely.
- Permission filtering happens on the server (`NavRegistry::forUser`) *and* again on the client, so a
  server bug can never widen visibility. Icons cross the wire as strings resolved through an explicit
  allowlist; an unknown name degrades to `LayoutGrid` rather than throwing.
- `ParentResource` + `ResolvesParentResource` for nested resources. The parent is re-queried through
  its own global scopes after route binding, so an out-of-scope parent is a 404 and never a readable
  id; generated nested routes carry `->scopeBindings()` with an indexed two-column lookup.
- `SingularResource` + `HandlesSingularRecord` for one-row settings pages: exactly two routes
  (`GET` and `PUT`), no create, no destroy, no `{record}` parameter.
- New generators `make:myra-cluster`, `make:myra-nested`, `make:myra-singleton`.
- New demo: a Learning cluster (courses → lessons, plus a site-identity singleton) on three new
  tables.

### C — multi-tenancy, opt-in, default off

- **Two independent locks.** Scoping requires `config('myra.tenancy.enabled') === true` *and* the
  model listed in `config('myra.tenancy.models')`. Adding the trait to a model is not sufficient, so
  a merge that adds a trait can never change production visibility on its own.
- **The disabled path registers nothing.** `bootBelongsToTenant()` returns before `addGlobalScope`
  and before `static::creating`. A registered scope that returns early still mutates the builder and
  still shows up in `getGlobalScopes()`; that is not good enough for a visibility feature.
  `tests/Feature/Tenancy/DisabledPathIsNoOpTest` asserts `toSql()` and `getBindings()` against a
  baseline fixture generated *before* the trait existed, as guest, member and super-admin.
- **Fail closed.** With scoping active and no tenant resolved, the scope applies `1 = 0`. Rows with a
  NULL tenant column are invisible to non-super-admins under `null_rows: 'strict'`; the `'shared'`
  mode wraps its `orWhereNull` in a nested closure, because an unwrapped one leaks every row.
- `App\Admin\Tenancy\Tenancy` is the single predicate: `Tenancy::apply()` for the hand-rolled query
  sites, `Tenancy::for()` as the only way to change tenant (restoring in a `finally`),
  `Tenancy::without()` as an audited escape hatch, and `Tenancy::unique()`/`exists()` for validation
  rules that do not leak across tenants and degrade to the plain rules when disabled.
- The tenant is resolved lazily from `users.current_team_id` with membership re-validated per
  request — never from a route or query parameter. No middleware, no route changes.
- `App\Models\Traits\BelongsToTeam` is **deleted**. Zero models used it; it registered an
  unconditional global scope with no super-admin bypass and failed *open* on a NULL
  `current_team_id`, which is every user on a single-team install.
- New `myra:tenancy-audit` (gates readiness on the column, the trait, a leading composite index and
  zero NULL rows) and `myra:tenancy-baseline` (writes the SQL baseline the no-op test asserts
  against). The migration adds nullable `team_id` columns and indexes only — no backfill.

### D — testing helpers, cursor pagination, 100k-row virtualisation

- `App\Admin\Testing\*` — `InteractsWithMyra` plus `TableProbe`, `SchemaProbe` and `ActionProbe`.
  They read Inertia props (no DOM, no browser) and assert the things that actually go wrong:
  `assertSortRejected()`, `assertPerPageCapped()`, `assertScopedToActor()`, `assertQueryCount()`,
  `assertSavedViewsEnabled()` (which catches a missing `table-key`), `assertPaginationMode()`.
- **Cursor pagination is a sibling, not a replacement.** `applySearchAndPaginate()` keeps its exact
  signature and behaviour — saved views, deep links and `meta.links` all depend on length-aware.
  The new `applySearchAndCursorPaginate()` shares the sort whitelist and per-page clamp, and always
  appends the primary-key tiebreak, without which cursor pagination silently skips or repeats rows on
  a non-unique sort column. `meta.mode` is stamped only on cursor responses, so every existing
  controller response is byte-identical.
- `myra.performance.stable_sort` adds an id tiebreak to the length-aware path. It is **off** by
  default because turning it on changes the SQL of every existing admin table.
- `SortIndexGuard` throws a named, actionable error for an unindexed sortable column outside
  production and behind a flag; production degrades to slow, never to a 500.
- `useVirtualRows` (zero dependencies, keeps the markup a real `<table>` so `<colgroup>` widths, the
  sticky header and the column manager keep working) and a `virtualized` prop on `DataTable`. Rows
  are keyed by `row.id`, never by index — inline editing keys its optimistic state and rollback the
  same way, and an index-recycling virtualiser would break all of it. Virtualisation refuses (with a
  dev-only warning, never a broken table) when `groupBy`, `reorderable` or an expanded-row slot is in
  play.
- Standing bug fixed: `DataTable`'s `localRows` was filled once at setup and never tracked prop
  changes.
- `cursor` joins `page` as a volatile parameter — never emitted, never persisted into a saved view,
  never compared. A persisted cursor would be replayed against a changed dataset.

### Rollback without a deploy

`MYRA_TENANCY=false`, `MYRA_PLUGINS_STRICT=false`, an empty `myra.extensions.plugins`, an empty
`myra.clusters`, `MYRA_STABLE_SORT=false`, `MYRA_ASSERT_INDEXES=false`, then
`php artisan config:clear`. Every migration in this release is a new table, a nullable column or an
index; none needs a `down()` to restore visibility.

## v2.3.0 — 2026-08

The reporting cluster: composable report definitions, a server-side aggregation engine, a lazy
chart library, drill-through/cross-filtering, and scheduled delivery. Plus the security follow-ups
left over from v2.2.0.

### A — security follow-ups + export writer seam

- Every remaining unescaped `LIKE` now goes through `Sql::whereLike()` / `Sql::orWhereLike()`:
  `UserService::exportQuery()`, `AdminNotificationController`, `ActivityLogController`,
  `EmailLogController`, `MediaController` (including the `mime_type` **prefix** match, where an
  unescaped `%` turned `LIKE 'x%'` into an arbitrary pattern) and `HasRelationManagers`.
- `HasRelationManagers::paginateRelation()` gained a `$sortable` whitelist. An out-of-whitelist
  `sort` falls back to `$defaultSort` instead of reaching `orderBy()` verbatim; `direction`
  normalises to `asc`/`desc`.
- New `App\Admin\Http\Refusal` — a refusal responds as JSON to an XHR caller and `text/plain`
  otherwise, always with `nosniff` and `no-store`, and never through the debug HTML renderer that
  leaks request/user context. Wired into the export row cap and both import row caps.
- New `App\Admin\Export\WriterRegistry` — the single authority on export formats. `csv` and `xlsx`
  register themselves (xlsx behind a `ZipArchive` capability gate); `ExportDefinition::formats()`
  intersects against it, which is the security boundary for the `format` parameter.
- `Sql::LIKE_ESCAPE` removed: its value is driver-dependent, so a public const was misleading.
- `InlineUploadController::show()` no longer dereferences a null user before the 404 can fire.

### B — report definitions + the server-side aggregation engine

- **Every number a report produces is computed by the database.** `ReportRunner` contains no
  `->get()` on an ungrouped query, no `->cursor()`, no `Collection::groupBy` and no `array_sum`
  over model rows. The rows the grouped statement returns ARE the buckets, and there are never
  more than `maxGroups` of them — a 10M-row table grouped by month over two years is 24 rows of
  scalars, independent of table size. The query budget is asserted mechanically via `DB::listen`:
  2 statements for a grouped run, +2 for a comparison, +1 for a relation dimension's bounded label
  lookup.
- `App\Admin\Report\*`: `ReportDefinition` (model, scope, dimensions, measures, filters, defaults,
  comparisons, drill target, caps), `Dimension`, `Measure`, `ReportRegistry`, `ReportRequest`,
  `ReportRunner`, `ReportResult`/`ReportRow`/`StatResult`, `ReportBatch`, `ReportShape`.
- `Bucket` and `Aggregate` are **closed enums and the only producers of a `selectRaw`/`groupByRaw`
  fragment**, exactly as `Operator` is the only producer of a `WHERE` fragment. `App\Support\DateBucket`
  is the only place a date becomes SQL and asserts the column against the same identifier regex
  `FieldSpec` uses.
- `Period` is a **half-open** `[start, end)` window, so "previous period" is contiguous with no
  off-by-one and the date predicate is an index range scan rather than a `BETWEEN`. On the wire
  `to` is inclusive; internally `end` is exclusive and both bounds are UTC.
- `ReportRequest` is the only client-input surface. Names are intersected against the declared sets
  and **dropped** when undeclared (no field-enumeration oracle); malformed input, an over-wide
  period, a bucket that would overflow the group cap, a cross-filter storm and an unknown filter
  field are all 422s. Cross-filters are re-validated server-side even though the server minted
  them — the client is the transport, and the transport carries no authority.
- Top-N on a categorical dimension folds the tail into ONE SQL-computed `Other` row using the
  scalar totals already fetched, so it costs zero extra queries. Non-additive measures
  (`avg`, `min`, `max`, `count_distinct`) report `null` on that row and render an em dash rather
  than a fabricated number.
- Comparisons are two disjoint window scans reusing the same `(scope, date)` index — deliberately
  not one conditional aggregate spanning both windows, which would widen the outer range and need
  driver-specific date arithmetic to realign. Date series are gap-filled and zipped by ordinal;
  categorical series are zipped by key.
- `ReportBatch` runs a whole dashboard in ONE request (12 reports max), not one request per widget.
- **Saved report views add zero tables**: they reuse `table_views` with a `report:{key}` prefix, so
  `TableView::scopeVisibleTo()`, the controller, the policy, `useTableViews.ts` and
  `TableViewsMenu.vue` all work unchanged.
- New routes under `/admin/reports` (`index`, `show`, `data`, `export`) plus
  `/admin/dashboard/widgets/data`; new `reports.view` / `reports.export` / `reports.schedule` /
  `reports.schedule.external` permissions (the last is super-admin only — it authorises mailing
  arbitrary addresses, a data-exfiltration vector).
- Client: `types/reports.ts`, `useReportState.ts` (debounced, cancelling; a 422 sets an i18n key and
  **leaves the previous result intact** — a failed refine must never blank the chart),
  `components/admin/reports/*` assembled entirely from existing `ui/` primitives.
- `php artisan make:myra-report {Model}` scaffolds a definition and registers it in
  `config('myra.reports.definitions')`.
- Demo page 22 (`/admin/demo/reports`) runs the real `users` report against real data — no mock
  provider, because the point is that the aggregation happens in SQL.

### C — chart library, comparison rendering, sparkline

- Chart.js moved behind `defineAsyncComponent` in a registry, so a stat-only dashboard no longer
  pays for the chart library; the duplicate `ChartJS.register()` in `Dashboard.vue` is gone.
- New `components/admin/charts/*`: `ChartCanvas` (the only importer of `vue-chartjs`), inline-SVG
  `Heatmap`/`Funnel`/`Gauge`/`Sparkline`, `DeltaBadge`, `ChartRegistry`, `chartTheme`.
- Chart options are now a `computed`, so a type or theme change actually re-renders; every colour
  comes from resolved CSS custom properties, never a hex literal in a component.
- SVG charts carry `role="img"` with a summary label, keyboard-operable segments (Enter and Space
  fire the same event as a click), a visible focus ring, and a "view as table" fallback.
- `useDashboardWidgets` gains report bindings, `permission`, `sort`, `visible`, `lazy`, `poll`,
  responsive `colSpan` (clamped to the grid) and `rowSpan`. `.data(fn)` and every existing closure
  builder are retained unchanged.
- `TableWidget`'s hardcoded "No data available." is now an i18n key.

### D — drill-down, cross-filter, scheduled delivery, PDF

- `DrillThrough` builds the URL a segment navigates to. The tree it emits **carries no authority**:
  the landing controller re-parses it through its own `FieldSet`, so a forged drill URL is
  validated exactly like a hand-typed filter. Its params are byte-identical to what
  `buildTableParams()` emits, so "Save as view" on a drilled table just works.
- Client-orchestrated cross-filtering: one segment click is ONE batched request for every affected
  widget, never N. A widget never filters itself.
- Dependency-free PDF writer (`App\Admin\Report\Pdf\*`) on the `XlsxRowWriter` precedent — page
  content streams to a temp file so memory is O(1). Charts render as native PDF vector operators
  for bar/line/pie; other types degrade to their data table. WinAnsi only: a non-Latin locale is
  refused with `reports.errors.pdfUnsupportedCharset` rather than emitting mojibake.
- Scheduled delivery on one new additive table (`report_schedules`) with a precomputed
  `next_run_at`, so dispatch is an index range scan rather than a cron evaluation over every row.
  Cadence is a closed preset enum — never a user-supplied cron string a worker executes.
  The job runs **as the schedule owner**, so ownership scoping still applies to a 3 a.m. mail;
  three consecutive failures pause the schedule and notify the owner.
- `RecipientResolver` drops any recipient the owner cannot address; an external address requires
  `reports.schedule.external`.
- `EmailService` gained `mailerFor()` (builds a Mailer without mutating global config, which does
  not survive a queue worker) and `queueTemplate()`, so an `EmailLog` row that says `queued`
  finally means it. `sendTemplate()` keeps its signature and synchronous behaviour.
## v2.2.1 — 2026-08

Security and robustness follow-ups. No feature changes.

### Security
- **Unescaped LIKE patterns.** Six call sites interpolated raw user input into a LIKE pattern with
  no escaping on any driver, so a `%` in a search box matched every row: `UserService::exportQuery()`,
  `AdminNotificationController`, `ActivityLogController`, `EmailLogController`, `MediaController`
  (including the `mime_type` prefix match) and `HasRelationManagers`. All migrated to
  `Sql::whereLike()` / `orWhereLike()`. Covered by `tests/Feature/Security/LikeEscapingTest.php`.
- **`HasRelationManagers::paginateRelation()`** passed the client-supplied `sort` straight to
  `orderBy()`. It now whitelists the column and falls back to the default sort.
- `InlineUploadController::show()` null-guards `$request->user()` and answers 404 instead of fatalling.

### Fixed
- `ImportController` row-cap refusals return the same clean payload as the export path, via the
  extracted `App\Admin\Http\Refusal`.
- Removed the dead `Sql::LIKE_ESCAPE` constant; `escapeLiteral()` is the single source of truth.

## v2.2.0 — 2026-08

Table power features (saved views, column manager, streaming export, resumable import, nested
query builder, ranked global search) plus the C1b follow-up defects from v2.1.0.

### Fixed — defects caught by the release gate
- `Sql::like()` escaped `%` `_` `\` but no call site emitted an `ESCAPE` clause. MySQL treats
  backslash as the default LIKE escape so this silently worked there; SQLite has none, so
  searching for a literal `%` matched nothing. Added `Sql::whereLike()` / `orWhereLike()`, which
  emit the pattern and a driver-aware `ESCAPE` clause together, with the column validated as an
  identifier and the term still bound as a parameter.
- Inline-upload reads resolved the storage path with `strpos($path, 'inline/')`, which matched the
  **route** segment rather than the storage prefix. Replaced with an anchored strip of the exact
  route prefix.
- The export row cap aborted mid-stream, so a refusal could still emit a partial file. The cap is
  now enforced before any bytes are written.
- `FieldSpec::operators()` called `array_intersect()` on `Operator` enum instances, which PHP
  cannot cast to string — a hard `Error`. Now compares by `->value` with `in_array(strict: true)`.

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
