# Components Reference

All reusable Vue components in the admin framework.

---

## Admin Components

Imported from `@/components/admin`:

```ts
import { FormField, FormFields, ResourceForm, SettingsCard, SimpleTable, RowActions, DateCell } from '@/components/admin';
```

---

### FormField

Renders a single form field with label, input, error, and hint. Automatically selects the correct input component based on `type`.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `string` | required | Field label text |
| `name` | `string` | required | Field name (used for ID generation) |
| `type` | `string` | `'text'` | Input type (see below) |
| `error` | `string` | — | Validation error message |
| `required` | `boolean` | `false` | Shows red asterisk next to label |
| `hint` | `string` | — | Helper text below input |
| `placeholder` | `string` | — | Input placeholder |
| `options` | `SelectOption[]` | — | Options for select type |
| `disabled` | `boolean` | `false` | Disable the input |
| `rows` | `number` | — | Row count for textarea type |

**Supported Types:**

| Type | Renders |
|------|---------|
| `text` | `<Input>` |
| `email` | `<Input type="email">` |
| `number` | `<Input type="number">` |
| `tel` | `<Input type="tel">` |
| `url` | `<Input type="url">` |
| `password` | `<PasswordInput>` (with eye toggle) |
| `textarea` | `<Textarea>` |
| `select` | `<Select>` dropdown |
| `switch` | `<Switch>` toggle |
| `checkbox` | `<Checkbox>` |
| `date` | `<Input type="date">` |
| `datetime-local` | `<Input type="datetime-local">` |
| `radio` | Radio button group |
| `color` | Color picker with hex input |
| `hidden` | `<input type="hidden">` (no visible element) |
| `file` | Styled file input |
| `richtext` | `<Textarea>` with 6 rows |

**Model:** `v-model` for two-way binding.

**Slots:**
- Default — custom input component (replaces the auto-rendered input)

**Usage:**
```vue
<!-- Standard usage -->
<FormField label="Name" name="name" v-model="form.name" required :error="form.errors.name" />

<!-- With custom input -->
<FormField label="Color" name="color">
    <input type="color" v-model="form.color" />
</FormField>
```

---

### FormFields

Renders an array of field schemas into `<FormField>` instances. No wrapper element (fragment rendering).

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `schema` | `FieldSchema[]` | Array of field schemas from the form builder |
| `form` | `object` | Inertia form instance (with `.errors` property) |

**Usage:**
```vue
<FormFields :schema="schema" :form="form" />
```

See [Form Builder](form-builder.md) for full schema API.

---

### ResourceForm

Card-wrapped form layout for create/edit pages. Provides a responsive grid, submit button, and cancel link.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `processing` | `boolean` | `false` | Shows loading spinner on submit button |
| `submitText` | `string` | `'Save'` | Submit button label |
| `cancelHref` | `string` | — | URL for cancel button (hidden if not set) |
| `maxWidth` | `string` | `'max-w-2xl'` | Tailwind max-width class |
| `columns` | `1 \| 2 \| 3` | `2` | Grid column count |

**Events:**
- `submit` — Emitted when form is submitted

**Slots:**

| Slot | Description |
|------|-------------|
| `header` | Content before the field grid |
| default | Form fields (rendered inside grid) |
| `after-fields` | Content after the field grid (outside grid) |
| `actions` | Custom action buttons (replaces default submit/cancel) |

**Usage:**
```vue
<ResourceForm
    :processing="form.processing"
    submit-text="Create Product"
    :cancel-href="route('admin.products.index')"
    @submit="submit"
>
    <FormFields :schema="schema" :form="form" />
</ResourceForm>
```

---

### SettingsCard

Form card for settings pages. Similar to ResourceForm but with a title/description header. Used inside tabs or stacked layouts.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | required | Card title |
| `description` | `string` | — | Card description |
| `processing` | `boolean` | `false` | Shows loading on submit |
| `submitText` | `string` | `'Save'` | Submit button label |
| `columns` | `1 \| 2` | `2` | Grid column count |

**Events:**
- `submit` — Emitted when form is submitted

**Slots:**

| Slot | Description |
|------|-------------|
| default | Form fields (rendered inside grid) |
| `after-fields` | Content after the field grid |
| `actions` | Custom action buttons |

**Usage:**
```vue
<SettingsCard
    title="General Settings"
    description="Basic configuration."
    :processing="form.processing"
    @submit="saveSettings"
>
    <FormFields :schema="schema" :form="form" />
</SettingsCard>
```

---

### SimpleTable

Static (non-paginated) table for small datasets. Shows EmptyState when no items.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `columns` | `SimpleTableColumn[]` | required | Column definitions |
| `items` | `any[]` | required | Row data |
| `rowKey` | `string` | `'id'` | Unique key property |
| `emptyTitle` | `string` | `'No results found'` | Empty state title |
| `emptyDescription` | `string` | — | Empty state description |
| `emptyIcon` | `Component` | — | Empty state icon |

**Slots:**
- `cell-{key}` — Custom cell with `{ value, row }` context
- `actions` — Actions column with `{ row }` context
- `empty` — Custom empty state action

**Usage:**
```vue
<SimpleTable :columns="columns" :items="tokens">
    <template #cell-last_used_at="{ value }">
        <DateCell :value="value" format="relative" />
    </template>
    <template #actions="{ row }">
        <Button variant="destructive" size="sm" @click="revoke(row.id)">Revoke</Button>
    </template>
</SimpleTable>
```

---

### RowActions

Dropdown menu for table row actions. Automatically hides actions the user lacks permission for.

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `actions` | `RowAction[]` | Array of action definitions |

**RowAction Interface:**

| Property | Type | Description |
|----------|------|-------------|
| `label` | `string` | Action label text |
| `icon` | `Component` | Lucide icon component |
| `permission` | `string` | Required permission (hidden if user lacks it) |
| `href` | `string` | Navigation URL (renders as Link) |
| `onClick` | `() => void` | Click handler (renders as button) |
| `destructive` | `boolean` | Red text styling |
| `separator` | `boolean` | Show separator line above this action |
| `show` | `boolean` | Explicit visibility control (default: true) |

**Usage:**
```vue
<RowActions :actions="[
    { label: 'Edit', icon: Pencil, href: route('admin.products.edit', row.id), permission: 'products.edit' },
    { label: 'Delete', icon: Trash2, permission: 'products.delete', destructive: true, separator: true,
      onClick: () => confirmDelete('admin.products.destroy', row.id) },
]" />
```

---

### DateCell

Formats and displays dates.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `value` | `string \| null` | required | ISO date string |
| `format` | `'date' \| 'datetime' \| 'relative'` | `'date'` | Display format |

**Format Examples:**

| Format | Output |
|--------|--------|
| `date` | `3/1/2026` |
| `datetime` | `3/1/2026, 2:30:00 PM` |
| `relative` | `Just now`, `5m ago`, `2h ago`, `3d ago` |

**Usage:**
```vue
<DateCell :value="row.created_at" />
<DateCell :value="row.updated_at" format="relative" />
```

---

## Shared Components

Imported directly from `@/components/`:

---

### DataTable

Full-featured paginated table with search, sorting, selection, auto-rendering columns, filters, and actions.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `columns` | `Column[] \| BaseColumn[]` | required | Column definitions (legacy or schema-based) |
| `data` | `PaginatedData<any>` | required | Paginated data from backend |
| `searchable` | `boolean` | `true` | Show search input |
| `searchPlaceholder` | `string` | `'Search...'` | Search input placeholder |
| `selectable` | `boolean` | `false` | Show row checkboxes |
| `loading` | `boolean` | `false` | Show loading skeleton |
| `filters` | `Record<string, string>` | — | Current URL filter values |
| `routeName` | `string` | required | Route name for pagination/search |
| `routeParams` | `Record<string, any>` | — | Additional route params |
| `tableFilters` | `FilterInput[]` | — | Filter controls (from useTableFilters) |
| `actions` | `ActionInput[]` | — | Row actions (from useTableActions) |
| `bulkActions` | `BulkAction[]` | — | Bulk actions for selected rows |

**Legacy Column Interface (still supported):**

| Property | Type | Description |
|----------|------|-------------|
| `key` | `string` | Data property key |
| `label` | `string` | Column header text |
| `sortable` | `boolean` | Enable sort on this column |
| `class` | `string` | Additional CSS classes |

**Slots:**

| Slot | Context | Description |
|------|---------|-------------|
| `toolbar` | `{ selectedIds }` | Toolbar area (extra buttons) |
| `cell-{key}` | `{ row, value }` | Custom cell rendering (overrides auto-render) |
| `expanded-row` | `{ row }` | Expandable row content |
| `actions` | `{ row }` | Row actions column (overrides auto-render) |
| `empty` | — | Custom empty state |

**Usage (legacy — still works):**
```vue
<DataTable :columns="columns" :data="products" :filters="filters" route-name="admin.products.index">
    <template #cell-status="{ value }">
        <StatusBadge :status="value" />
    </template>
    <template #actions="{ row }">
        <RowActions :actions="[...]" />
    </template>
</DataTable>
```

**Usage (schema-based — recommended):**
```vue
<DataTable
    :columns="columns"
    :data="products"
    :filters="filters"
    :table-filters="tableFilters"
    :actions="actions"
    :bulk-actions="bulkActions"
    route-name="admin.products.index"
/>
```

See [Table Builder](table-builder.md) for full schema API.

---

### PageHeader

Page title bar with optional description and action buttons.

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `title` | `string` | Page title |
| `description` | `string` | Page description |

**Slots:**
- `badge` — Badge/tag next to the title
- `actions` — Action buttons aligned to the right

**Usage:**
```vue
<PageHeader title="Products" description="Manage your product catalog.">
    <template #actions>
        <Button as-child>
            <Link :href="route('admin.products.create')">
                <Plus class="mr-2 size-4" /> Add Product
            </Link>
        </Button>
    </template>
</PageHeader>
```

---

### StatusBadge

Colored badge for status values.

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `status` | `string` | Status value |

**Status Colors:**

| Statuses | Variant |
|----------|---------|
| `active`, `success`, `sent` | default (green) |
| `suspended`, `failed`, `error` | destructive (red) |
| `pending`, `queued` | secondary (gray) |
| other | outline |

**Usage:**
```vue
<StatusBadge status="active" />
```

---

### EmptyState

Centered empty content placeholder with icon, title, and optional action.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | `'No results found'` | Title text |
| `description` | `string` | — | Description text |
| `icon` | `Component` | `InboxIcon` | Lucide icon |

**Slots:**
- `action` — Action button/content

**Usage:**
```vue
<EmptyState title="No products yet" description="Create your first product to get started.">
    <template #action>
        <Button as-child>
            <Link :href="route('admin.products.create')">Create Product</Link>
        </Button>
    </template>
</EmptyState>
```

---

### LoadingButton

Button with built-in loading spinner. Replaces the `<Button :disabled="form.processing">` pattern.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `loading` | `boolean` | `false` | Show spinner and disable |
| `variant` | `string` | `'default'` | Button variant |
| `size` | `string` | `'default'` | Button size |
| `type` | `string` | `'submit'` | Button type |
| `disabled` | `boolean` | `false` | Disable button |

**Usage:**
```vue
<LoadingButton :loading="form.processing">Save</LoadingButton>
<LoadingButton :loading="form.processing" variant="destructive">Delete</LoadingButton>
```

---

### PasswordInput

Password input with visibility toggle (eye/eye-off icon).

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `modelValue` | `string` | Input value (v-model) |
| `id` | `string` | Input ID |
| `placeholder` | `string` | Placeholder text |
| `autocomplete` | `string` | Autocomplete attribute |
| `required` | `boolean` | Required attribute |
| `autofocus` | `boolean` | Autofocus attribute |

**Usage:**
```vue
<PasswordInput v-model="form.password" placeholder="Enter password" required />
```

---

### ConfirmDialog

Global confirmation dialog. Renders automatically — just include it once in your layout. Controlled via the `useConfirm` composable.

No props needed. Add to your layout:

```vue
<ConfirmDialog />
```

---

### StatCard

Dashboard statistic card with optional trend indicator.

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `title` | `string` | Stat title |
| `value` | `string \| number` | Stat value |
| `description` | `string` | Additional context |
| `icon` | `Component` | Lucide icon |
| `trend` | `{ value: number, isPositive: boolean }` | Trend indicator |

**Usage:**
```vue
<StatCard
    title="Total Revenue"
    value="$12,345"
    description="Last 30 days"
    :icon="DollarSign"
    :trend="{ value: 12.5, isPositive: true }"
/>
```

---

### NotificationBell

Notification dropdown with unread badge. Reads `unreadNotificationsCount` and `recentNotifications` from Inertia page props.

No props needed:

```vue
<NotificationBell />
```
