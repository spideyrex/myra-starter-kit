# Type Definitions

TypeScript interfaces and types used throughout the admin framework.

---

## Core Types

**File:** `resources/js/types/index.d.ts`

### User

```ts
interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    phone?: string;
    avatar?: string;
    status: 'active' | 'suspended' | 'pending';
    roles: string[];
    permissions: string[];
    created_at: string;
    updated_at: string;
}
```

### PaginatedData

Matches Laravel's `LengthAwarePaginator` JSON structure. Used by `DataTable`.

```ts
interface PaginatedData<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
}
```

### PageProps

Inertia shared page props. Extended by page-specific props.

```ts
type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: { user: User };
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    unreadNotificationsCount?: number;
    siteSettings?: SiteSettings;
};
```

### Other Core Types

```ts
interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

interface Permission {
    id: number;
    name: string;
    guard_name: string;
    group?: string;
    created_at: string;
    updated_at: string;
}

interface Notification {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

interface SiteSettings {
    site_name: string;
    site_description: string;
    site_logo?: string;
    maintenance_mode: boolean;
}

interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface NavItem {
    title: string;
    href?: string;
    icon?: string;
    permission?: string;
    children?: NavItem[];
}
```

---

## Admin Types

**File:** `resources/js/types/admin.d.ts`

### FieldSchema

Re-exported from `useFormSchema`. Represents a field definition produced by the form builder.

```ts
interface FieldSchema {
    name: string;
    label: string;
    type: 'text' | 'email' | 'password' | 'number' | 'textarea'
        | 'select' | 'switch' | 'checkbox' | 'tel' | 'url'
        | 'date' | 'datetime-local' | 'radio' | 'color'
        | 'hidden' | 'file' | 'richtext';
    required: boolean;
    hint?: string;
    placeholder?: string;
    disabled: boolean;
    colSpan?: number;
    options?: SelectOption[];
    rows?: number;
    minDate?: string;
    maxDate?: string;
    inline?: boolean;
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
}
```

### SelectOption

Used by `Select` fields and the `FormField` select type.

```ts
interface SelectOption {
    label: string;
    value: string;
}
```

### RowAction

Used by the `RowActions` component.

```ts
interface RowAction {
    label: string;
    icon?: Component;       // Lucide icon component
    permission?: string;    // Required permission
    href?: string;          // Navigation URL
    onClick?: () => void;   // Click handler
    destructive?: boolean;  // Red text styling
    separator?: boolean;    // Separator line above
    show?: boolean;         // Explicit visibility (default: true)
}
```

### SimpleTableColumn

Used by the `SimpleTable` component.

```ts
interface SimpleTableColumn {
    key: string;
    label: string;
    class?: string;
}
```

### FormFieldProps

Base props for the `FormField` component (subset).

```ts
interface FormFieldProps {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
}
```

---

## DataTable Column (Legacy)

Exported from `DataTable.vue`. Used to define table columns (legacy API).

```ts
interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}
```

**Import:**
```ts
import type { Column } from '@/components/DataTable.vue';
```

---

## Table Schema Types

**File:** `resources/js/types/admin.d.ts`

### ColumnSchema

Discriminated union of column types produced by the table builder classes.

```ts
type ColumnSchema =
    | TextColumnSchema
    | BadgeColumnSchema
    | DateColumnSchema
    | BooleanColumnSchema
    | ImageColumnSchema
    | IconColumnSchema
    | ToggleColumnSchema;
```

All column schemas share a `ColumnSchemaBase` interface:

```ts
interface ColumnSchemaBase {
    key: string;
    label: string;
    type: string;
    sortable: boolean;
    searchable: boolean;
    hidden: boolean;
    alignRight: boolean;
    class?: string;
    tooltip?: string;
    toggleable: boolean;
    grow: boolean;
}
```

### FilterSchema

```ts
type FilterSchema = SelectFilterSchema | TernaryFilterSchema | CheckboxFilterSchema;
```

### ActionSchema

```ts
interface ActionSchema {
    label: string;
    icon?: Component;
    urlFn?: (row: any) => string;
    actionFn?: (row: any) => void;
    requiresConfirmation: boolean;
    confirmTitle?: string;
    confirmDescription?: string;
    permission?: string;
    destructive: boolean;
    separator: boolean;
    deleteRouteName?: string;
}
```

### BulkActionSchema

```ts
interface BulkActionSchema {
    label: string;
    actionFn?: (ids: number[]) => void;
    requiresConfirmation: boolean;
    deselectAfter: boolean;
    icon?: Component;
    permission?: string;
    destructive: boolean;
}
```

### LayoutSchema

Used by form layout classes (Section, Grid, Tabs, Fieldset).

```ts
interface LayoutSchema {
    layoutType: 'section' | 'grid' | 'tabs' | 'tab' | 'fieldset';
    label?: string;
    description?: string;
    columns?: number;
    collapsible?: boolean;
    collapsed?: boolean;
    icon?: Component;
    badge?: string;
    schema: SchemaItem[];
}
```
