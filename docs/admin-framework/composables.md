# Composables Reference

All Vue composables used in the admin framework.

---

## useFormSchema

Form builder API for defining field schemas with a fluent, chainable syntax.

**File:** `resources/js/composables/useFormSchema.ts`

**Field Exports:** `TextInput`, `Select`, `Textarea`, `Toggle`, `Checkbox`, `DatePicker`, `DateTimePicker`, `Radio`, `ColorPicker`, `Hidden`, `FileUpload`, `RichEditor`

**Layout Exports:** `Section`, `Grid`, `Tabs`, `Tab`, `Fieldset`

**Type Exports:** `FieldSchema`, `LayoutSchema`, `SchemaItem`

See [Form Builder](form-builder.md) for full documentation.

---

## useTableSchema

Fluent column builder API for defining DataTable columns declaratively.

**File:** `resources/js/composables/useTableSchema.ts`

**Exports:** `TextColumn`, `BadgeColumn`, `DateColumn`, `BooleanColumn`, `ImageColumn`, `IconColumn`, `ToggleColumn`

See [Table Builder](table-builder.md) for full documentation.

---

## useTableFilters

Fluent filter builder API for defining DataTable filters.

**File:** `resources/js/composables/useTableFilters.ts`

**Exports:** `SelectFilter`, `TernaryFilter`, `Filter`

See [Table Builder](table-builder.md) for filter documentation.

---

## useTableActions

Pre-configured and custom action builder classes for DataTable row actions and bulk actions.

**File:** `resources/js/composables/useTableActions.ts`

**Exports:** `Action`, `EditAction`, `ViewAction`, `DeleteAction`, `ActionGroup`, `BulkAction`

See [Table Builder](table-builder.md) for action documentation.

---

## useResourceForm

Handles form submission with optional confirmation dialogs. Wraps Inertia's `useForm`.

**File:** `resources/js/composables/useResourceForm.ts`

```ts
import { useResourceForm } from '@/composables/useResourceForm';
```

### Signature

```ts
function useResourceForm<T extends Record<string, any>>(
    options: ResourceFormOptions<T>
): { form: InertiaForm<T>; submit: () => Promise<void> }
```

### Options

| Property | Type | Description |
|----------|------|-------------|
| `data` | `T` | Initial form data |
| `storeRoute` | `string` | Route name for `POST` (create) |
| `storeRouteParams` | `any` | Route params for store |
| `updateRoute` | `string` | Route name for `PUT` (update) |
| `updateRouteParams` | `any` | Route params for update |
| `confirm` | `ConfirmOptions` | Optional confirmation dialog config |

### ConfirmOptions

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `title` | `string` | required | Dialog title |
| `description` | `string` | — | Dialog body text |
| `confirmText` | `string` | `'Confirm'` | Confirm button text |
| `variant` | `'default' \| 'destructive'` | `'default'` | Dialog style |

### Returns

| Property | Type | Description |
|----------|------|-------------|
| `form` | `InertiaForm<T>` | Reactive form with `.errors`, `.processing`, etc. |
| `submit` | `() => Promise<void>` | Shows confirm dialog (if set), then POSTs or PUTs |

### Usage

```ts
// Create
const { form, submit } = useResourceForm({
    data: { name: '', email: '' },
    storeRoute: 'admin.users.store',
    confirm: { title: 'Create User', confirmText: 'Create' },
});

// Update
const { form, submit } = useResourceForm({
    data: { name: props.user.name, email: props.user.email },
    updateRoute: 'admin.users.update',
    updateRouteParams: props.user.id,
    confirm: { title: 'Update User', confirmText: 'Save' },
});
```

---

## useConfirmAction

Provides pre-built confirmation methods for delete and post actions. Used in list pages with row actions.

**File:** `resources/js/composables/useConfirmAction.ts`

```ts
import { useConfirmAction } from '@/composables/useConfirmAction';
```

### Returns

| Method | Description |
|--------|-------------|
| `confirmDelete(routeName, routeParams, options?)` | Shows destructive dialog, calls `router.delete()` |
| `confirmPost(routeName, routeParams, data?, options?)` | Shows dialog, calls `router.post()` |

### Usage

```ts
const { confirmDelete, confirmPost } = useConfirmAction();

// In RowActions
onClick: () => confirmDelete('admin.products.destroy', row.id, {
    title: 'Delete Product',
    description: 'This action cannot be undone.',
})

// Custom post action
onClick: () => confirmPost('admin.products.archive', row.id, {}, {
    title: 'Archive Product',
    confirmText: 'Archive',
})
```

---

## useConfirm

Low-level composable for the global confirmation dialog. Used internally by `useResourceForm` and `useConfirmAction`. Use this when you need custom confirmation flows.

**File:** `resources/js/composables/useConfirm.ts`

```ts
import { useConfirm } from '@/composables/useConfirm';
```

### Returns

| Property | Type | Description |
|----------|------|-------------|
| `confirm(options)` | `(opts) => Promise<boolean>` | Show dialog, returns true/false |
| `isOpen` | `Ref<boolean>` | Dialog open state |
| `title` | `Ref<string>` | Current dialog title |
| `description` | `Ref<string>` | Current dialog description |
| `confirmText` | `Ref<string>` | Current confirm button text |
| `cancelText` | `Ref<string>` | Current cancel button text |
| `variant` | `Ref<string>` | Current variant |
| `handleConfirm()` | `() => void` | Resolve promise with true |
| `handleCancel()` | `() => void` | Resolve promise with false |

### Usage

```ts
const { confirm } = useConfirm();

async function doSomething() {
    const confirmed = await confirm({
        title: 'Are you sure?',
        description: 'This will do something important.',
        confirmText: 'Yes, do it',
        variant: 'destructive',
    });

    if (confirmed) {
        // proceed
    }
}
```

---

## usePermissions

Check user permissions and roles. Reads from Inertia page props (`auth.user.roles`, `auth.user.permissions`).

**File:** `resources/js/composables/usePermissions.ts`

```ts
import { usePermissions } from '@/composables/usePermissions';
```

### Returns

| Property | Type | Description |
|----------|------|-------------|
| `can(permission)` | `(p: string) => boolean` | True if user has permission or is super-admin |
| `hasRole(role)` | `(r: string) => boolean` | True if user has the role |
| `isSuperAdmin` | `ComputedRef<boolean>` | True if user has 'super-admin' role |
| `roles` | `ComputedRef<string[]>` | User's roles |
| `permissions` | `ComputedRef<string[]>` | User's permissions |

### Usage

```ts
const { can, hasRole, isSuperAdmin } = usePermissions();

// In template
<Button v-if="can('products.create')">Add Product</Button>

// In RowActions
{ label: 'Edit', permission: 'products.edit', ... }  // auto-checked by RowActions

// In script
if (hasRole('admin')) { ... }
```

---

## useFlashToasts

Converts Inertia flash messages (`success`, `error`, `warning`, `info`) into vue-sonner toasts. Called once in each layout component.

**File:** `resources/js/composables/useFlashToasts.ts`

```ts
import { useFlashToasts } from '@/composables/useFlashToasts';
```

### Usage

Called in `AuthenticatedLayout.vue` and `GuestLayout.vue`:

```ts
useFlashToasts();
```

Automatically watches for flash messages in Inertia page props and shows appropriate toasts. No configuration needed.
