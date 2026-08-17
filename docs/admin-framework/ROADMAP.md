# Myra Roadmap — persistent backlog

State file for `/improve-myra`. Agents read this to pick work and tick items on completion.
Baseline: Myra v2.0.0 vs **Filament v5**. Ordered by value; work top-down unless told otherwise.

Legend: `[ ]` open · `[~]` in progress · `[x]` shipped (note the version)

---

## C1 — Filament v5 parity gaps

Concrete, verified gaps in the schema engines.

**Form fields** (`useFormSchema.ts` + `FormField.vue`)
- [x] `code` — code editor field (syntax highlight, line numbers, language per-field) — v2.1.0
- [x] `markdown` — full markdown editor (toolbar + preview split; partial support exists today) — v2.1.0
- [x] `toggle-buttons` — Filament's toggle-buttons semantics distinct from the current `toggle-group` — v2.1.0
- [x] Field-level `helperText` / `hint` / `hintAction` parity pass across all types — v2.1.0

**Table columns** (`useTableSchema.ts`)
- [x] `color` column — swatch rendering + copyable value — v2.1.0
- [x] `checkbox` column — inline boolean editing, optimistic update — v2.1.0
- [x] Column-level summaries beyond sum/avg/count (min, max, range) — v2.1.0

**Infolist entries** (`useInfolistSchema.ts`)
- [x] `color` entry — v2.1.0
- [x] `code` entry — v2.1.0

**Actions**
- [x] `replicate` action (record duplication with field overrides) — v2.1.0
- [x] `restore` + `force-delete` as first-class actions (soft-delete demo exists; actions don't) — v2.1.0
- [x] Action grouping / dropdown clustering parity — v2.1.0

---

## C1b — C1 follow-ups (carried from the v2.1.0 sweep)

Known-and-logged, not silently dropped.

- [~] **Scaffolding stubs — (a) DONE v2.4.0, verified 2026-08-16; (b) open, needs a decision — TWO separate systems, do not conflate them** (corrected
      2026-08-16 after inspection; the earlier note here was wrong):

      **(a) `stubs/admin/*.stub` — DONE in v2.4.0, empirically verified 2026-08-16.** The generator now emits model + migration + form requests + a feature test, and the index page passes `table-key` so saved views and the column manager work in generated resources. Verified by running `make:myra-resource Widget --model` end to end: all emitted PHP lints clean and the generated Vue pages pass `vue-tsc` with zero errors. (It stops at permission registration without a database — that is environmental, not a defect.)
      Consumed by the `make:myra-*` generators via `base_path()` (see
      `app/Console/Commands/Myra/Concerns/ScaffoldsAdmin.php::writeStub`).
      Deliberately NOT emitted, and this remains the right call: `HandlesInlineUpdates` needs a
      matching route + `inlineEditableFields()`, and `ColorColumn`/`CheckboxColumn` would
      reference columns the generated model may not have. Those belong behind a generator flag,
      not in the default scaffold.

      **(b) `packages/myra/framework/stubs/` — SEPARATE PRIVATE REPO, still open. NEEDS A HUMAN
      DECISION FIRST — do not bulk-copy.** Consumed by `myra:install` (`InstallCommand` copies the
      whole tree) to bootstrap a brand-new project. Measured 2026-08-16 against v2.5.1:

      | | app | stubs |
      |---|---|---|
      | `resources/js` (.vue/.ts) | 610 | 293 |
      | `app` (.php) | 272 | 58 |

      This is a rebuild of the installer payload, not a sync. Two reasons it was deliberately
      NOT executed:
      1. **Parts of the stub tree are intentionally leaner** — e.g. its `AuthenticatedLayout.vue`
         omits the command palette, team switcher and i18n. A blind copy destroys those choices
         and drags the whole demo catalogue, tenancy, AI, PWA and plugin surface into every new
         install.
      2. **It cannot be verified here.** Testing `myra:install` requires a fresh Laravel project
         to install into. Copying ~600 files and pushing them unverified is exactly the kind of
         change the release gate exists to prevent.

      **What it needs before anyone starts:** (a) a product decision on what a new install should
      contain — everything, or a core subset with the rest opt-in via plugins; (b) a scratch
      Laravel app to run `myra:install` against end to end. With those two, it is a
      straightforward, mostly mechanical job in the `myra-framework` repo.
- [x] Markdown field image upload endpoint (private disk, magic-byte validation) — v2.2.0
- [x] `ReplicateAction.schema()` now carries the modal's edits to the replicate endpoint — v2.2.0
- [x] `ActionGroup.permission()` is dead for a root-level group (copied everywhere except `permission`). — v2.2.0
- [x] Toggle `min`/`max` are enforced in the renderer only — no matching validation rule is emitted. — v2.2.0
- [x] i18n pass over the new strings ('Confirm', 'Update failed.', 'Copied to clipboard', 'Page'). — v2.2.0
- [x] Inline-update partial-reload props (the `only: []` no-op) — v2.2.0

---

## C2 — Table power features

The "view, filter, search, export, import" stability mandate.

- [x] Saved views — named filter+column+sort presets, per user, shareable — v2.2.0
- [x] Column visibility manager with presets and persistence — v2.2.0
- [x] 100k+ row performance: virtualised rows, cursor pagination, indexed sort paths — v2.4.0
- [x] Streaming export for large datasets (never buffer the whole set in memory) — v2.2.0
- [x] Import: column auto-mapping, validation preview, partial-failure report, resumable — v2.2.0
- [x] Advanced query builder parity (nested AND/OR groups — `QueryBuilderGroup.vue` is the base) — v2.2.0
- [x] Global search ranking + scoped search per resource — v2.2.0

---

## C2b — C2 follow-ups (carried from the v2.2.0 sweep)

- [x] **Unescaped LIKE patterns outside the query builder.** All six call sites migrated to
      `Sql::whereLike()`/`orWhereLike()`: `UserService::exportQuery()`,
      `AdminNotificationController`, `ActivityLogController`, `EmailLogController`,
      `MediaController` (including the prefix match on `mime_type`) and `HasRelationManagers`.
      Covered by `tests/Feature/Security/LikeEscapingTest.php`.
- [x] `ImportController` row-cap refusals go through `App\Admin\Http\Refusal` — the same clean
      payload as the export path, extracted out of `ExportableQuery::exportRefusal()`.
- [x] `Sql::LIKE_ESCAPE` removed; `escapeLiteral()` remains the single source of the literal.
- [x] `HasRelationManagers::paginateRelation()` whitelists `sort`; an unlisted column falls back
      to `$defaultSort` instead of reaching `orderBy()` as raw client input.
- [x] MySQL `sql_mode=NO_BACKSLASH_ESCAPES` would break the emitted `ESCAPE '\\'` literal. — v2.5.1
      `escapeLiteral()` needs a third case if any deployment runs that mode.
- [x] `Sql::like()` is still public and can be misused without its `ESCAPE` clause — only a — v2.5.1
      docblock guards it. Consider making it internal.
- [x] `InlineUploadController::show()` null-guards `$request->user()` and answers 404, not a fatal.
- [x] The inline-upload public URL contains a doubled segment (`/uploads/inline/inline/{id}/`), — v2.5.1
      now enshrined by an assertion. Cosmetic, but worth a clean route.
- [x] 100k+ row performance (virtualised rows, cursor pagination) — deliberately deferred from — v2.4.0
      the v2.2.0 sweep, not attempted.

---

## C3 — Reporting, graphs & charts

Beyond Filament — this is a differentiator, not a parity item.

- [x] Report builder: composable report definitions (source, dimensions, measures, filters) — v2.3.0
- [x] Chart library expansion: line, bar, stacked, area, pie/donut, radar, scatter, heatmap, — v2.3.0
      funnel, gauge, sparkline (current: chart.js + vue-chartjs)
- [x] Drill-down — click a chart segment to open the filtered table — v2.3.0
- [x] Cross-filtering between dashboard widgets — v2.3.0
- [x] Scheduled reports — cron → render → email delivery (email templates already exist) — v2.3.0
- [x] PDF export for reports; Excel export exists (`useExcelExport.ts`) — v2.3.0
- [x] Comparison periods (vs previous period / year) on stat + chart widgets — v2.3.0

---

## C4 — Plugin & scale architecture

The "easy to scale up the platform" foundation.

- [x] Plugin system: discoverable packages that register pages, resources, widgets, permissions — v2.4.0
- [x] Clusters — grouped resources with shared navigation/sub-navigation — v2.4.0
- [x] Multi-tenancy — full tenant scoping — v2.4.0 **(shipped OPT-IN, MYRA_TENANCY=false; enabling it is a deliberate, separate step)**
- [x] Nested resources (parent/child routing) and singular resources — v2.4.0
- [x] Testing helpers — assert on schemas, tables, actions the way Filament's test suite does — v2.4.0
- [x] Generator coverage for all of the above (`make:myra-plugin`, `make:myra-cluster`, …) — v2.4.0

---

## C5 — Dashboard system, latest tech

- [x] Drag-and-drop dashboard editor with per-user layouts (`DashboardGrid.vue` is the base) — v2.5.0
- [x] Widget marketplace/catalogue — browse and drop in widgets without code — v2.5.0
- [x] Real-time widgets over Echo/websockets (already wired) with live-updating charts — v2.5.0
- [x] Component catalogue upgrade: searchable gallery with live props playground and — v2.5.0
      copy-paste snippets (today: 20 static demo pages)
- [x] AI-assisted: natural-language filters, schema generation, dashboard summarisation — v2.5.0
      (provider layer exists in `app/Services/Ai/`)
- [x] Command-palette-first navigation across every admin surface — v2.5.0
- [x] PWA/offline shell for the admin — v2.5.0
- [x] Skeleton/streaming loading states as the default for every data surface — v2.5.0

---

## Shipped

- [x] v2.8 Appearance ("Surface") — four swappable guest layouts (split, centred,
      cover, card), six background types over ten authored recipes, a scrim with a
      floor for image-backed surfaces, and a configurable background behind the
      public homepage. Admin editor at `/dashboard/appearance`, gallery at
      `/dashboard/demo/auth-layouts`. Both defaults (`auth = brand`, `page = none`)
      emit zero CSS, so an upgrade that changes nothing renders the same DOM.
      See [appearance.md](appearance.md).
- [x] Live editing in the page-builder preview — click any heading, subtitle,
      button label, stat, FAQ answer or testimonial in the preview and type.
      Section renderers emit `data-myra-field`/`data-myra-kind`; an in-frame agent
      turns those into contenteditable regions and posts changes over a
      channel-tagged, origin-checked postMessage. Loaded only for a framed
      `?preview=` page. Images and rich text select the section and scroll their
      real control into view instead. See [live-edit.md](live-edit.md).
- [x] Admin URL prefix — moved `/admin` → `/dashboard` and made it configuration
      (`myra.admin.prefix`, env `MYRA_ADMIN_PREFIX`). Route names stay `admin.*`, so the
      ~370 `route('admin.…')` call sites were untouched. `Myra::adminPrefix()/adminPath()`
      and `@/lib/adminPath` are the single source of truth; a GET-only legacy redirect keeps
      old links alive. 204 routes moved; 78/78 live pages verified clean. See
      [admin-prefix.md](admin-prefix.md).
- [x] v2.0.0 — Block Builder, searchable/async Select, 8 generators, Shield RBAC, 2FA,
      per-user data isolation, PHPUnit suite + CI, MapLibre toolkit, i18n (en/ms/zh)
