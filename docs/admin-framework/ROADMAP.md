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

- [ ] **Framework stubs are stale.** Every C1 agent skipped `packages/myra/framework/stubs/`
      because `packages/` is gitignored and absent from a git worktree. Generated projects do
      not get inline-edit columns, the new actions, or the hint API. Needs: `use HandlesInlineUpdates`
      + `inlineEditableFields()` in the controller stub, `use HandlesSoftDeletes` + `authorizeBulkVerb()`
      in the generated `bulkAction()`, and CheckboxColumn/ColorColumn in the index-page stub.
      **Any future sweep touching `resources/js` must run the stub port as a non-worktree step.**
- [ ] Markdown field image upload has no server endpoint — `mdUploadRoute` posts to a route that
      does not exist yet (private disk + signed temporary URL per spec).
- [ ] `ReplicateAction.schema()` discards user edits: `ActionModal` submits only schema fields, so
      the modal's values never reach the replicate endpoint.
- [ ] `ActionGroup.permission()` is dead for a root-level group (copied everywhere except `permission`).
- [ ] Toggle `min`/`max` are enforced in the renderer only — no matching validation rule is emitted.
- [ ] i18n pass over the new strings ('Confirm', 'Update failed.', 'Copied to clipboard', 'Page').
- [ ] `only: []` in the inline-update request is a no-op — Inertia treats an empty array as a full
      visit, so the "returns no props" optimisation never happens.

---

## C2 — Table power features

The "view, filter, search, export, import" stability mandate.

- [ ] Saved views — named filter+column+sort presets, per user, shareable
- [ ] Column visibility manager with presets and persistence
- [ ] 100k+ row performance: virtualised rows, cursor pagination, indexed sort paths
- [ ] Streaming export for large datasets (never buffer the whole set in memory)
- [ ] Import: column auto-mapping, validation preview, partial-failure report, resumable
- [ ] Advanced query builder parity (nested AND/OR groups — `QueryBuilderGroup.vue` is the base)
- [ ] Global search ranking + scoped search per resource

---

## C3 — Reporting, graphs & charts

Beyond Filament — this is a differentiator, not a parity item.

- [ ] Report builder: composable report definitions (source, dimensions, measures, filters)
- [ ] Chart library expansion: line, bar, stacked, area, pie/donut, radar, scatter, heatmap,
      funnel, gauge, sparkline (current: chart.js + vue-chartjs)
- [ ] Drill-down — click a chart segment to open the filtered table
- [ ] Cross-filtering between dashboard widgets
- [ ] Scheduled reports — cron → render → email delivery (email templates already exist)
- [ ] PDF export for reports; Excel export exists (`useExcelExport.ts`)
- [ ] Comparison periods (vs previous period / year) on stat + chart widgets

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
