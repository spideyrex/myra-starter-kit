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

- [ ] **Scaffolding stubs are stale — TWO separate systems, do not conflate them** (corrected
      2026-08-16 after inspection; the earlier note here was wrong):

      **(a) `stubs/admin/*.stub` — 8 files, TRACKED IN THE MAIN REPO, agent-reachable.**
      Consumed by the `make:myra-*` generators via `base_path()` (see
      `app/Console/Commands/Myra/Concerns/ScaffoldsAdmin.php::writeStub`). This is the
      higher-value target: it is what `make:myra-resource` emits every time you scaffold.
      Today `page.index.stub` imports only `TextColumn`/`DateColumn` + `EditAction`/`DeleteAction`,
      and `controller.resource.stub` has no `bulkAction()` at all.
      **This is real design work, not a copy.** Adding `HandlesInlineUpdates` needs a matching
      route + `inlineEditableFields()`, and adding `ColorColumn`/`CheckboxColumn` blind would
      reference columns the generated model may not have — a careless port makes the generator
      emit scaffolds that 404 or fatal. Needs a proper design pass deciding what is universally
      safe to emit vs. what belongs behind a generator flag.

      **(b) `packages/myra/framework/stubs/` — 384 files, SEPARATE PRIVATE REPO, NOT
      agent-reachable from a worktree.** Consumed by `myra:install` (`InstallCommand` copies the
      whole tree) to bootstrap a brand-new project. It is a **v1.0-era snapshot, two releases
      stale**: verified absent are `ColorSwatch.vue`, `CodeBlock.vue`, `useCodeMirror.ts`,
      `useTableViews.ts`, `useSummaries.ts` — i.e. a freshly installed project gets none of
      v2.1.0 or v2.2.0. Lower urgency (only affects new installs, not this platform) but it is
      a full-tree sync and must be done outside a worktree, in the `myra-framework` repo.
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
- [ ] 100k+ row performance: virtualised rows, cursor pagination, indexed sort paths
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
- [ ] MySQL `sql_mode=NO_BACKSLASH_ESCAPES` would break the emitted `ESCAPE '\\'` literal.
      `escapeLiteral()` needs a third case if any deployment runs that mode.
- [ ] `Sql::like()` is still public and can be misused without its `ESCAPE` clause — only a
      docblock guards it. Consider making it internal.
- [x] `InlineUploadController::show()` null-guards `$request->user()` and answers 404, not a fatal.
- [ ] The inline-upload public URL contains a doubled segment (`/uploads/inline/inline/{id}/`),
      now enshrined by an assertion. Cosmetic, but worth a clean route.
- [ ] 100k+ row performance (virtualised rows, cursor pagination) — deliberately deferred from
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

- [ ] Plugin system: discoverable packages that register pages, resources, widgets, permissions
- [ ] Clusters — grouped resources with shared navigation/sub-navigation
- [ ] Multi-tenancy — full tenant scoping (teams tables + `TeamSwitcher.vue` are the starting point)
- [ ] Nested resources (parent/child routing) and singular resources
- [ ] Testing helpers — assert on schemas, tables, actions the way Filament's test suite does
- [ ] Generator coverage for all of the above (`make:myra-plugin`, `make:myra-cluster`, …)

---

## C5 — Dashboard system, latest tech

- [ ] Drag-and-drop dashboard editor with per-user layouts (`DashboardGrid.vue` is the base)
- [ ] Widget marketplace/catalogue — browse and drop in widgets without code
- [ ] Real-time widgets over Echo/websockets (already wired) with live-updating charts
- [ ] Component catalogue upgrade: searchable gallery with live props playground and
      copy-paste snippets (today: 20 static demo pages)
- [ ] AI-assisted: natural-language filters, schema generation, dashboard summarisation
      (provider layer exists in `app/Services/Ai/`)
- [ ] Command-palette-first navigation across every admin surface
- [ ] PWA/offline shell for the admin
- [ ] Skeleton/streaming loading states as the default for every data surface

---

## Shipped

- [x] v2.0.0 — Block Builder, searchable/async Select, 8 generators, Shield RBAC, 2FA,
      per-user data isolation, PHPUnit suite + CI, MapLibre toolkit, i18n (en/ms/zh)
