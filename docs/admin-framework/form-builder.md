# Form Builder API

The form builder provides a fluent API for defining form fields as TypeScript objects instead of verbose template markup.

## Quick Comparison

**Before (template-heavy):**
```vue
<FormField label="Name" name="name" v-model="form.name" required :error="form.errors.name" />
<FormField label="Email" name="email" v-model="form.email" type="email" required :error="form.errors.email" />
<FormField label="Password" name="password" v-model="form.password" type="password" :error="form.errors.password" />
<FormField label="Role" name="role" v-model="form.role" type="select" :options="[...]" :error="form.errors.role" />
```

**After (schema-driven):**
```ts
const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email().required(),
    TextInput.make('password').password(),
    Select.make('role').options([...]),
];
```

```vue
<FormFields :schema="schema" :form="form" />
```

## Imports

```ts
// Field types
import { TextInput, Select, Textarea, Toggle, Checkbox, DatePicker, DateTimePicker, Radio, ColorPicker, Hidden, FileUpload, RichEditor } from '@/composables/useFormSchema';

// Layout types
import { Section, Grid, Tabs, Tab, Fieldset } from '@/composables/useFormSchema';

import { FormFields } from '@/components/admin';
```

## Field Types

### TextInput

Standard text input with type modifiers.

```ts
TextInput.make('name')                          // text input
TextInput.make('email').email()                 // email input
TextInput.make('password').password()           // password input with eye toggle
TextInput.make('price').numeric()               // number input
TextInput.make('quantity').integer()             // number input (alias)
TextInput.make('phone').tel()                   // tel input
TextInput.make('website').url()                 // url input
```

### Select

Dropdown select field. Supports two option formats.

```ts
// Array format
Select.make('role').options([
    { label: 'Admin', value: 'admin' },
    { label: 'Editor', value: 'editor' },
    { label: 'Viewer', value: 'viewer' },
])

// Record shorthand
Select.make('status').options({
    active: 'Active',
    suspended: 'Suspended',
    pending: 'Pending',
})

// With placeholder
Select.make('category').placeholder('Select a category').options({...})
```

### Textarea

Multi-line text input.

```ts
Textarea.make('description')
Textarea.make('notes').rows(6)                  // custom row count
Textarea.make('bio').colSpan(2)                 // full-width in 2-col grid
```

### Toggle

Switch/toggle for boolean values.

```ts
Toggle.make('is_active').label('Active')
Toggle.make('notifications').label('Enable notifications')
```

### Checkbox

Checkbox for boolean values.

```ts
Checkbox.make('agree_terms').label('I agree to the terms')
Checkbox.make('subscribe').label('Subscribe to newsletter')
```

### DatePicker

Native date input.

```ts
DatePicker.make('birth_date')
DatePicker.make('start_date').minDate('2024-01-01')
DatePicker.make('end_date').maxDate('2026-12-31')
```

### DateTimePicker

Native datetime-local input.

```ts
DateTimePicker.make('scheduled_at')
DateTimePicker.make('event_start').minDate('2024-01-01T00:00')
```

### Radio

Radio button group. Supports same option formats as Select.

```ts
Radio.make('theme').options({ light: 'Light', dark: 'Dark', system: 'System' })
Radio.make('priority').options({
    low: 'Low', medium: 'Medium', high: 'High',
}).inline()   // horizontal layout
```

### ColorPicker

Native color picker with hex text input.

```ts
ColorPicker.make('accent_color')
ColorPicker.make('brand_color').label('Brand Color')
```

### Hidden

Hidden input — no visible element rendered.

```ts
Hidden.make('type')   // useful for passing extra data
```

### FileUpload

File input with accept/size constraints.

```ts
FileUpload.make('document')
FileUpload.make('avatar').image()                    // accept="image/*"
FileUpload.make('photos').accept('image/*').multiple()
FileUpload.make('resume').accept('.pdf,.doc').maxSize(10)  // 10MB limit
```

### RichEditor

Textarea with more rows, intended for rich content.

```ts
RichEditor.make('content')
RichEditor.make('body').rows(10)
```

## Shared Methods

All field types inherit these methods from `BaseField`:

| Method | Description | Example |
|--------|-------------|---------|
| `.label(text)` | Set explicit label | `.label('Full Name')` |
| `.required()` | Mark as required (shows asterisk) | `.required()` |
| `.hint(text)` | Helper text below field | `.hint('Min 8 characters')` |
| `.placeholder(text)` | Input placeholder text | `.placeholder('Enter name')` |
| `.disabled()` | Disable the field | `.disabled()` |
| `.colSpan(n)` | Grid column span | `.colSpan(2)` |

### Auto-derived Labels

If `.label()` is not called, the label is derived from the field name:

| Name | Auto Label |
|------|------------|
| `name` | Name |
| `email` | Email |
| `mail_host` | Mail Host |
| `first_name` | First Name |
| `created_at` | Created At |

### Column Spanning

In a 2-column grid (default for ResourceForm), use `.colSpan(2)` for full-width fields:

```ts
const schema = [
    TextInput.make('first_name'),               // left column
    TextInput.make('last_name'),                // right column
    Textarea.make('bio').colSpan(2),            // full width
    TextInput.make('city'),                     // left column
    TextInput.make('country'),                  // right column
];
```

## Rendering

### FormFields Component

The `<FormFields>` component renders a schema array, binding `v-model` and errors automatically:

```vue
<script setup lang="ts">
import { FormFields, ResourceForm } from '@/components/admin';
import { useResourceForm } from '@/composables/useResourceForm';
import { TextInput, Select } from '@/composables/useFormSchema';

const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email().required(),
    Select.make('status').options({ active: 'Active', inactive: 'Inactive' }),
];

const { form, submit } = useResourceForm({
    data: { name: '', email: '', status: 'active' },
    storeRoute: 'admin.items.store',
});
</script>

<template>
    <ResourceForm :processing="form.processing" @submit="submit">
        <FormFields :schema="schema" :form="form" />
    </ResourceForm>
</template>
```

### Mixed Usage

You can combine `<FormFields>` with manual `<FormField>` tags for custom inputs:

```vue
<ResourceForm :processing="form.processing" @submit="submit">
    <FormFields :schema="schema" :form="form" />

    <template #after-fields>
        <FormField label="Avatar" name="avatar">
            <input type="file" @change="handleUpload" />
        </FormField>
    </template>
</ResourceForm>
```

### Dynamic Schemas

Since schemas are plain arrays, you can build them dynamically:

```ts
const props = defineProps<{ roles: string[] }>();

const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email().required(),
    Select.make('role')
        .placeholder('Select role')
        .options(props.roles.map(r => ({ label: r, value: r }))),
];
```

### Conditional Fields

```ts
const isAdmin = computed(() => form.role === 'admin');

const schema = computed(() => [
    TextInput.make('name').required(),
    Select.make('role').options({ admin: 'Admin', user: 'User' }),
    ...(isAdmin.value ? [
        TextInput.make('admin_code').required().hint('Required for admin accounts'),
    ] : []),
]);
```

## Settings Page Pattern

For settings pages with multiple sections, define a schema per section:

```ts
const generalSchema = [
    TextInput.make('site_name'),
    TextInput.make('admin_email').email(),
];

const seoSchema = [
    TextInput.make('meta_title'),
    Textarea.make('meta_description'),
    TextInput.make('meta_keywords'),
];
```

```vue
<SettingsCard title="General" :processing="generalForm.processing" @submit="save('general', generalForm)">
    <FormFields :schema="generalSchema" :form="generalForm" />
</SettingsCard>

<SettingsCard title="SEO" :processing="seoForm.processing" :columns="1" @submit="save('seo', seoForm)">
    <FormFields :schema="seoSchema" :form="seoForm" />
</SettingsCard>
```

## Form Layouts

Layout classes wrap fields in visual groupings. They are not field types — they contain nested schema arrays.

### Section

Wraps fields in a `<Card>` with title and description.

```ts
const schema = [
    Section.make('Personal Info').schema([
        TextInput.make('name').required(),
        TextInput.make('email').email().required(),
    ]),
    Section.make('Preferences').columns(1).schema([
        Toggle.make('notifications'),
        Toggle.make('newsletter'),
    ]),
];
```

**Methods:**

| Method | Description |
|--------|-------------|
| `.description(text)` | Card description text |
| `.schema(fields)` | Nested field/layout array |
| `.columns(n)` | Grid columns (default: 2) |
| `.collapsible()` | Make section collapsible |
| `.collapsed()` | Start collapsed (implies collapsible) |
| `.icon(component)` | Icon next to title |

### Grid

Nested grid for fine-grained layout control.

```ts
Grid.make(3).schema([
    TextInput.make('city'),
    TextInput.make('state'),
    TextInput.make('zip'),
])
```

### Tabs

Tab panels for organizing large forms.

```ts
Tabs.make([
    Tab.make('General').schema([
        TextInput.make('name').required(),
        TextInput.make('email').email(),
    ]),
    Tab.make('Settings').icon(Settings).schema([
        Toggle.make('is_active'),
        Select.make('role').options({ admin: 'Admin', user: 'User' }),
    ]),
    Tab.make('Notes').badge('3').schema([
        Textarea.make('notes').colSpan(2),
    ]),
])
```

### Fieldset

Bordered group with legend.

```ts
Fieldset.make('Address').columns(2).schema([
    TextInput.make('street').colSpan(2),
    TextInput.make('city'),
    TextInput.make('state'),
])
```

### Nesting

Layouts can be nested:

```ts
const schema = [
    Section.make('Profile').schema([
        TextInput.make('name').required(),
        Grid.make(3).schema([
            TextInput.make('city'),
            TextInput.make('state'),
            TextInput.make('zip'),
        ]),
    ]),
];
```

## Internal API

Each field instance exposes:

| Property/Method | Description |
|-----------------|-------------|
| `.name` | The field name string |
| `.colStyle` | CSS `grid-column: span N` string or undefined |
| `.toProps()` | Returns a `FieldSchema` object for `<FormField>` binding |

The `FieldSchema` interface:

```ts
interface FieldSchema {
    name: string;
    label: string;
    type: 'text' | 'email' | 'password' | 'number' | 'textarea' | 'select'
        | 'switch' | 'checkbox' | 'tel' | 'url' | 'date' | 'datetime-local'
        | 'radio' | 'color' | 'hidden' | 'file' | 'richtext';
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
