# Table Builder API

The table builder provides a fluent API for defining DataTable columns, filters, and actions as TypeScript objects instead of verbose template slots.

## Quick Comparison

**Before (manual slots):**
```vue
<script setup>
const columns: Column[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Created', sortable: true },
];
</script>
<template>
    <DataTable :columns="columns" :data="products" ...>
        <template #cell-status="{ value }">
            <StatusBadge :status="value" />
        </template>
        <template #cell-created_at="{ value }">
            <DateCell :value="value" />
        </template>
        <template #actions="{ row }">
            <RowActions :actions="[
                { label: 'Edit', icon: Pencil, href: route('...', row.id) },
                { label: 'Delete', icon: Trash2, destructive: true, ... },
            ]" />
        </template>
    </DataTable>
</template>
```

**After (declarative):**
```vue
<script setup>
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { EditAction, DeleteAction } from '@/composables/useTableActions';

const columns = [
    TextColumn.make('name').sortable().searchable(),
    BadgeColumn.make('status').colors({ active: 'success', suspended: 'destructive' }),
    DateColumn.make('created_at').sortable(),
];

const actions = [
    EditAction.make('admin.products.edit'),
    DeleteAction.make('admin.products.destroy'),
];
</script>
<template>
    <DataTable :columns="columns" :data="products" :actions="actions" route-name="admin.products.index" />
</template>
```

## Imports

```ts
// Columns
import { TextColumn, BadgeColumn, DateColumn, BooleanColumn, ImageColumn, IconColumn, ToggleColumn } from '@/composables/useTableSchema';

// Filters
import { SelectFilter, TernaryFilter, Filter } from '@/composables/useTableFilters';

// Actions
import { Action, EditAction, ViewAction, DeleteAction, ActionGroup, BulkAction } from '@/composables/useTableActions';
```

## Column Types

### TextColumn

Plain text column with formatting options.

```ts
TextColumn.make('name')                              // plain text
TextColumn.make('name').sortable().searchable()      // sortable + searchable
TextColumn.make('email').copyable()                  // copy-to-clipboard button
TextColumn.make('bio').limit(50)                     // truncate to 50 chars
TextColumn.make('price').money('USD')                // format as currency
TextColumn.make('quantity').numeric(2)               // format with decimals
TextColumn.make('name').prefix('Mr. ')               // prepend text
TextColumn.make('score').suffix(' pts')              // append text
TextColumn.make('status').default('N/A')             // fallback for null values
TextColumn.make('bio').wrap()                        // allow text wrapping
TextColumn.make('name')
    .url((row) => route('admin.users.edit', row.id)) // render as link
TextColumn.make('name')
    .formatStateUsing((value, row) => `${value} (${row.role})`) // custom format
TextColumn.make('name')
    .description((row) => row.email)                 // secondary text below
```

### BadgeColumn

Renders a colored badge. Uses `<StatusBadge>` when no colors are configured, or `<Badge>` with mapped variants.

```ts
BadgeColumn.make('status')                           // auto StatusBadge
BadgeColumn.make('status').colors({
    active: 'default',
    suspended: 'destructive',
    pending: 'secondary',
})
```

### DateColumn

Renders dates using the `<DateCell>` component.

```ts
DateColumn.make('created_at')                        // default date format
DateColumn.make('created_at').format('datetime')     // date + time
DateColumn.make('updated_at').format('relative')     // "5m ago"
DateColumn.make('created_at').sortable()             // sortable date
```

### BooleanColumn

Renders check/X icons for boolean values.

```ts
BooleanColumn.make('is_active')                      // default Check/X icons
BooleanColumn.make('is_active')
    .trueIcon(CheckCircle)                           // custom true icon
    .falseIcon(XCircle)                              // custom false icon
    .trueColor('text-green-500')                     // custom true color
    .falseColor('text-red-500')                      // custom false color
```

### ImageColumn

Renders an `<img>` tag.

```ts
ImageColumn.make('avatar')                           // default 40px square
ImageColumn.make('avatar').circular()                // rounded-full
ImageColumn.make('avatar').size(32)                  // custom size
ImageColumn.make('avatar').defaultUrl('/default.png') // fallback image
```

### IconColumn

Renders a dynamic Lucide icon.

```ts
IconColumn.make('type')
    .icon((value) => value === 'pdf' ? FileText : File)
    .color((value) => value === 'pdf' ? 'text-red-500' : 'text-muted-foreground')
```

### ToggleColumn

Renders an inline `<Switch>` for boolean values.

```ts
ToggleColumn.make('is_active')
    .onUpdate((row, value) => {
        router.patch(route('admin.products.toggle', row.id), { is_active: value });
    })
```

## Shared Column Methods

All column types inherit these methods:

| Method | Description |
|--------|-------------|
| `.label(text)` | Override auto-derived label |
| `.sortable()` | Enable server-side sorting |
| `.searchable()` | Mark as searchable |
| `.hidden()` | Hide the column |
| `.visible(bool)` | Conditional visibility |
| `.alignEnd()` | Right-align the column |
| `.extraClass(cls)` | Add CSS class |
| `.tooltip(text)` | Add tooltip text |
| `.toggleable()` | User can show/hide this column |
| `.grow()` | Column takes available space |

## Filters

Filters render in a collapsible toolbar above the table. Values are passed to the backend as query params via Inertia.

### SelectFilter

```ts
SelectFilter.make('status').options({
    active: 'Active',
    suspended: 'Suspended',
    pending: 'Pending',
})

// With custom placeholder
SelectFilter.make('role')
    .label('User Role')
    .placeholder('All roles')
    .options([
        { label: 'Admin', value: 'admin' },
        { label: 'Editor', value: 'editor' },
    ])
```

### TernaryFilter

Three-state filter: All / Yes / No.

```ts
TernaryFilter.make('email_verified').label('Email Verified')
TernaryFilter.make('is_active')
    .trueLabel('Active')
    .falseLabel('Inactive')
```

### Filter (Checkbox)

Simple boolean checkbox filter.

```ts
Filter.make('with_trashed').label('Include deleted')
Filter.make('verified').query('email_verified') // custom query param name
```

## Actions

Row actions are rendered automatically in a dropdown menu. No need for manual `RowActions` or slot templates.

### Pre-built Actions

```ts
EditAction.make('admin.products.edit')               // Pencil icon, navigates
ViewAction.make('admin.products.show')               // Eye icon, navigates
DeleteAction.make('admin.products.destroy')          // Trash icon, confirms, deletes

// With permission
EditAction.make('admin.products.edit').permission('products.edit')
DeleteAction.make('admin.products.destroy')
    .permission('products.delete')
    .confirmTitle('Delete Product')
    .confirmDescription('This will permanently delete the product.')
```

### Custom Actions

```ts
Action.make('Archive')
    .icon(Archive)
    .action((row) => router.post(route('admin.products.archive', row.id)))
    .requiresConfirmation('Archive Product', 'Are you sure?')
    .permission('products.edit')

Action.make('View Report')
    .icon(BarChart)
    .url((row) => route('admin.products.report', row.id))
    .visible((row) => row.has_report)
```

### Action Visibility

```ts
Action.make('Restore')
    .icon(Undo)
    .visible((row) => row.deleted_at !== null)  // only show for soft-deleted

Action.make('Impersonate')
    .hidden((row) => row.id === currentUserId)  // hide for self
```

### Bulk Actions

Bulk actions appear when rows are selected.

```ts
const bulkActions = [
    BulkAction.make('Activate')
        .icon(CheckCircle)
        .action((ids) => router.post(route('admin.users.bulk-action'), { ids, action: 'activate' })),
    BulkAction.make('Delete')
        .icon(Trash2)
        .destructive()
        .requiresConfirmation('Delete Selected', 'This will delete all selected items.')
        .action((ids) => router.post(route('admin.users.bulk-action'), { ids, action: 'delete' })),
];
```

## DataTable Props

The enhanced DataTable accepts both the new schema-based API and the existing legacy API:

| Prop | Type | Description |
|------|------|-------------|
| `columns` | `Column[] \| BaseColumn[]` | Column definitions (legacy or schema) |
| `data` | `PaginatedData<any>` | Paginated data |
| `filters` | `Record<string, string>` | Current URL filter values |
| `routeName` | `string` | Route name for pagination/search |
| `tableFilters` | `FilterInput[]` | Filter controls |
| `actions` | `ActionInput[]` | Row actions |
| `bulkActions` | `BulkAction[]` | Bulk actions |
| `searchable` | `boolean` | Show search input (default: true) |
| `selectable` | `boolean` | Show row checkboxes |
| `loading` | `boolean` | Show skeleton loading |

## Full Example

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn, ImageColumn } from '@/composables/useTableSchema';
import { SelectFilter, TernaryFilter } from '@/composables/useTableFilters';
import { EditAction, DeleteAction, BulkAction } from '@/composables/useTableActions';
import { usePermissions } from '@/composables/usePermissions';
import type { PaginatedData } from '@/types';
import { Plus, CheckCircle, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    users: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { can } = usePermissions();

const columns = [
    ImageColumn.make('avatar').circular().size(32),
    TextColumn.make('name').sortable().searchable()
        .description((row) => row.email),
    BadgeColumn.make('status').colors({
        active: 'default',
        suspended: 'destructive',
        pending: 'secondary',
    }),
    DateColumn.make('created_at').sortable().format('relative'),
];

const filters = [
    SelectFilter.make('status').options({
        active: 'Active',
        suspended: 'Suspended',
        pending: 'Pending',
    }),
    TernaryFilter.make('email_verified').label('Email Verified'),
];

const actions = [
    EditAction.make('admin.users.edit').permission('users.edit'),
    DeleteAction.make('admin.users.destroy').permission('users.delete'),
];

const bulkActions = [
    BulkAction.make('Activate')
        .icon(CheckCircle)
        .action((ids) => router.post(route('admin.users.bulk-action'), { ids, action: 'activate' })),
    BulkAction.make('Delete')
        .icon(Trash2)
        .destructive()
        .action((ids) => router.post(route('admin.users.bulk-action'), { ids, action: 'delete' })),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Users' }]">
        <Head title="Users" />

        <PageHeader title="Users" description="Manage user accounts.">
            <template #actions>
                <Button v-if="can('users.create')" as-child>
                    <Link :href="route('admin.users.create')">
                        <Plus class="mr-2 size-4" /> Add User
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="users"
                :filters="props.filters"
                :table-filters="filters"
                :actions="actions"
                :bulk-actions="bulkActions"
                route-name="admin.users.index"
                search-placeholder="Search users..."
            />
        </div>
    </AuthenticatedLayout>
</template>
```

## Backwards Compatibility

- DataTable still accepts plain `Column[]` objects — no breaking change
- Existing slot-based rendering (`#cell-{key}`, `#actions`, `#toolbar`) continues to work
- Slots override auto-rendering when present
- You can mix schema columns with slot overrides
