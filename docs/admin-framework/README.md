# Admin Framework Documentation

An admin framework built on Laravel + Inertia.js + Vue 3 (TypeScript) with shadcn-vue components.

## Table of Contents

| Document | Description |
|----------|-------------|
| [Getting Started](getting-started.md) | Quick start guide, creating your first resource |
| [Form Builder](form-builder.md) | Schema-driven form API with field types, layouts, and chaining |
| [Table Builder](table-builder.md) | Schema-driven table API with columns, filters, and actions |
| [Components](components.md) | All reusable Vue components (FormField, DataTable, etc.) |
| [Composables](composables.md) | Vue composables (useResourceForm, useTableSchema, etc.) |
| [Scaffold Commands](scaffold-commands.md) | Artisan commands for generating resources and pages |
| [PHP Backend](php-backend.md) | Controllers, services, and the SearchableQuery trait |
| [Type Definitions](type-definitions.md) | TypeScript interfaces and types |

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   Vue 3 Frontend                    │
│                                                     │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────┐  │
│  │ Form Builder │  │  Components  │  │Composables│  │
│  │ (Schema API) │  │  (Admin UI)  │  │ (Logic)   │  │
│  └──────┬──────┘  └──────┬───────┘  └─────┬─────┘  │
│         └────────────────┼────────────────┘         │
│                          │                          │
│              ┌───────────▼──────────┐               │
│              │   Inertia.js Bridge  │               │
│              └───────────┬──────────┘               │
└──────────────────────────┼──────────────────────────┘
                           │
┌──────────────────────────┼──────────────────────────┐
│                  Laravel Backend                     │
│              ┌───────────▼──────────┐               │
│              │     Controllers      │               │
│              └───────────┬──────────┘               │
│              ┌───────────▼──────────┐               │
│              │      Services        │               │
│              │  (SearchableQuery)   │               │
│              └───────────┬──────────┘               │
│              ┌───────────▼──────────┐               │
│              │   Eloquent Models    │               │
│              └──────────────────────┘               │
└─────────────────────────────────────────────────────┘
```

## Tech Stack

- **Backend:** Laravel, Inertia.js
- **Frontend:** Vue 3 (Composition API, TypeScript)
- **UI Library:** shadcn-vue
- **Icons:** lucide-vue-next
- **Styling:** Tailwind CSS v4 with oklch colors
- **Notifications:** vue-sonner
- **Charts:** vue-chartjs

## File Structure

```
app/
├── Admin/Traits/
│   └── SearchableQuery.php          # Reusable search/sort/paginate trait
├── Http/Controllers/Admin/          # Admin controllers
└── Services/                        # Service layer

resources/js/
├── components/
│   ├── admin/                       # Admin-specific components
│   │   ├── FormField.vue            # Single form field renderer
│   │   ├── FormFields.vue           # Schema-to-fields renderer
│   │   ├── ResourceForm.vue         # Card form wrapper
│   │   ├── SettingsCard.vue         # Settings page form card
│   │   ├── SimpleTable.vue          # Static table
│   │   ├── RowActions.vue           # Dropdown row actions
│   │   ├── DateCell.vue             # Date formatter
│   │   └── index.ts                 # Barrel exports
│   ├── DataTable.vue                # Paginated, sortable, searchable table
│   ├── PageHeader.vue               # Page title + actions header
│   ├── StatusBadge.vue              # Status indicator badge
│   ├── EmptyState.vue               # Empty content placeholder
│   ├── LoadingButton.vue            # Button with spinner
│   ├── PasswordInput.vue            # Password with visibility toggle
│   ├── ConfirmDialog.vue            # Global confirm dialog
│   ├── StatCard.vue                 # Dashboard stat card
│   └── NotificationBell.vue         # Notification dropdown
├── composables/
│   ├── useFormSchema.ts             # Form builder API (fields + layouts)
│   ├── useTableSchema.ts            # Table column builder API
│   ├── useTableFilters.ts           # Table filter builder API
│   ├── useTableActions.ts           # Table action builder API
│   ├── useResourceForm.ts           # Form submission handler
│   ├── useConfirmAction.ts          # Delete/post confirmation
│   ├── useConfirm.ts                # Confirm dialog state
│   ├── usePermissions.ts            # Permission checking
│   └── useFlashToasts.ts            # Flash message toasts
├── types/
│   ├── index.d.ts                   # Core types (User, PaginatedData, etc.)
│   └── admin.d.ts                   # Admin types (RowAction, SelectOption, etc.)
└── Pages/Admin/                     # Admin pages

stubs/admin/                         # Artisan command templates
├── controller.resource.stub
├── controller.page.stub
├── service.stub
├── page.index.stub
├── page.create.stub
├── page.edit.stub
└── page.single.stub
```
