# Dashboard, Realtime, AI & PWA

Shipped in **v2.5.0**. Every subsystem on this page except the dashboard editor is **opt-in and off
by default** — deploying the code changes nothing until you set its flag.

| Subsystem | Flag | Default |
|---|---|---|
| Dashboard editor | — | on |
| Realtime widgets | `MYRA_REALTIME` | **off** |
| AI filter / schema / summarise | `MYRA_AI_FILTER`, `MYRA_AI_SCHEMA`, `MYRA_AI_SUMMARISE` | **off** |
| PWA shell | `MYRA_PWA` | **off** |

---

## Dashboard editor

![Dashboard editor](../../public/docs/screenshots/demo-dashboard-editor.png)

Drag tiles into place and save a per-user layout; add widgets from the catalogue without writing
code. Layouts persist to `dashboard_layouts`, keyed by user and dashboard.

Reordering is keyboard accessible. `DashboardGrid` emits an **ordered key sequence**, not a
positional index — a grid-local index is ambiguous once a dashboard has more than one grid.

```ts
const { layout, reorderWithin, save } = useDashboardLayout('main');
```

## Realtime widgets

![Live widgets](../../public/docs/screenshots/demo-live-widgets.png)

Widgets refresh over the already-wired Laravel Echo connection. A dashboard of N live widgets uses
**one** subscription through a shared bus, not N.

Server side, a model touch emits a signal that is **queued**, never fanned out inside the request:

```php
use App\Admin\Realtime\Concerns\TouchesWidgets;

class Order extends Model
{
    use TouchesWidgets;   // queues EmitWidgetSignal on save
}
```

## Loading states

`DataSurface` plus the skeleton components are the default for any data surface, so a slow query
shows structure rather than a spinner.

```vue
<DataSurface :loading="loading" :error="error">
    <template #skeleton><SkeletonTable :rows="10" /></template>
    <DataTable ... />
</DataSurface>
```

## AI ask bar

![AI ask bar](../../public/docs/screenshots/demo-ai-filter.png)

Describe a filter in plain language and it is compiled into the **existing validated
`App\Admin\QueryBuilder` rule tree**, then re-validated server-side exactly like a hand-built one.

**The model never produces SQL.** It emits a JSON envelope of column/operator/value triples; every
column is checked against `FieldSpec`'s whitelist and every operator against the closed `Operator`
enum before anything reaches the database. Model output is treated as hostile input.

Other guarantees:

- Provider API keys are read server-side only and never reach the client or an Inertia prop.
- AI endpoints are permission-gated and rate-limited.
- Anything summarised is already ownership-scoped for the acting user — no cross-user leakage.

## PWA shell

![Offline shell](../../public/docs/screenshots/demo-offline-shell.png)

An installable app shell that keeps working without a connection.

**The service worker caches only the static shell and build assets.** No admin JSON, no Inertia
response, no user data ever enters the cache — otherwise the next person on a shared browser could
read the previous user's data.

## Component gallery & command palette

![Component playground](../../public/docs/screenshots/demo-playground.png)

The gallery is a searchable catalogue of every demo surface with a live props playground and
copy-paste snippets. A server-side `DemoRegistry` keeps the gallery and the pages in
`resources/js/Pages/Admin/Demo/` from drifting apart — a page without an entry, or an entry without
a page, fails the test suite.

The command palette is mounted globally across every admin surface.

---

## Related

- [Reporting](reporting.md) — the report widgets a dashboard displays
- [Tenancy](tenancy.md) — tenant scoping, also opt-in
