<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import FormFields from '@/components/admin/FormFields.vue';
import {
    TimePicker, CheckboxList, KeyValue, MarkdownEditor, Section, TextInput,
    Toggle, Checkbox, ToggleButtons,
} from '@/composables/useFormSchema';
import { Link2, Sparkles, Pencil, Eye, Check, Mail, MessageSquare, Bell, Sun, Moon, Monitor } from 'lucide-vue-next';

const form = reactive({
    meeting_time: '09:00',
    permissions: ['read', 'write'],
    metadata: [
        { key: 'version', value: '1.0' },
        { key: 'author', value: 'Admin' },
    ],
    readme: '# Hello World\n\nThis is a **markdown** editor.\n\n- Item 1\n- Item 2\n',
    changelog: 'Short summary of what changed.',
    notes: 'Edit-only mode, no switcher.',
    title: 'Quarterly report',
    slug: 'quarterly-report',
    plan: 'pro',
    api_key: '',
    newsletter: true,
    accept_terms: false,
    status: 'draft',
    channels: ['email'],
    theme: 'system',
    is_featured: '0',
    errors: {} as Record<string, string>,
});

function slugify(value: string): string {
    return String(value).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

const schema = [
    Section.make('Helper text & hints')
        .description('hint(), hintIcon(), hintColor() and hintAction() are on BaseField, so every field type has them.')
        .columns(1)
        .schema([
            TextInput.make('title')
                .label('Title')
                .required()
                .hint('Shown in listings and search results.'),
            TextInput.make('slug')
                .label('Slug')
                .required()
                .hint('Lowercase, hyphen-separated. Changing this breaks existing links.')
                .hintIcon(Link2, 'Used in the public URL')
                .hintColor('warning')
                .hintAction({
                    label: 'Regenerate',
                    icon: Sparkles,
                    onClick: (f) => { f.slug = slugify(f.title); },
                }),
            TextInput.make('api_key')
                .label('API key')
                .password()
                .hint('Stored encrypted at rest.')
                .hintColor('info'),
            Toggle.make('newsletter')
                .label('Send the weekly newsletter')
                .hint('Toggles and checkboxes render hints too.'),
            Checkbox.make('accept_terms')
                .label('I accept the terms')
                .hint('Required before the form can be submitted.')
                .hintColor('danger'),
        ]),
    Section.make('Toggle buttons')
        .description('One descriptor array carries value, label, icon, colour, description, tooltip, disabled and hidden.')
        .columns(1)
        .schema([
            ToggleButtons.make('status')
                .label('Status')
                .options([
                    { value: 'draft', label: 'Draft', icon: Pencil, color: 'muted', description: 'Only you can see it' },
                    { value: 'reviewing', label: 'In review', icon: Eye, color: 'warning' },
                    { value: 'published', label: 'Published', icon: Check, color: 'success', tooltip: 'Requires the articles.publish permission' },
                ])
                .inline()
                .hint('Drafts are excluded from the sitemap.'),
            ToggleButtons.make('channels')
                .label('Channels')
                .options([
                    { value: 'email', label: 'Email', icon: Mail },
                    { value: 'sms', label: 'SMS', icon: MessageSquare },
                    { value: 'push', label: 'Push', icon: Bell },
                ])
                .multiple()
                .min(1)
                .max(2)
                .columns(3)
                .hint('Pick one or two — the third disables itself at the cap.'),
            ToggleButtons.make('theme')
                .label('Theme')
                .options([
                    { value: 'light', label: 'Light', icon: Sun },
                    { value: 'dark', label: 'Dark', icon: Moon },
                    { value: 'system', label: 'Follow system', icon: Monitor },
                ])
                .hideLabels()
                .inline()
                .hint('Icon-only buttons keep the option label as their accessible name.'),
            ToggleButtons.make('is_featured')
                .label('Featured')
                .boolean('Featured', 'Standard')
                .inline(),
        ]),
    Section.make('TimePicker')
        .description('Native time input with min/max constraints')
        .columns(2)
        .schema([
            TimePicker.make('meeting_time')
                .label('Meeting Time')
                .minTime('08:00')
                .maxTime('18:00')
                .hint('Business hours only: 08:00 – 18:00'),
        ]),
    Section.make('CheckboxList')
        .description('Multi-select checkboxes with search and grid layout')
        .columns(1)
        .schema([
            CheckboxList.make('permissions')
                .label('Permissions')
                .options([
                    { label: 'Read', value: 'read' },
                    { label: 'Write', value: 'write' },
                    { label: 'Delete', value: 'delete' },
                    { label: 'Admin', value: 'admin' },
                    { label: 'Export', value: 'export' },
                    { label: 'Import', value: 'import' },
                    { label: 'Audit', value: 'audit' },
                    { label: 'Settings', value: 'settings' },
                ])
                .searchable()
                .columns(2)
                .hint('Select one or more permissions'),
        ]),
    Section.make('Key-Value Editor')
        .description('Dynamic key-value pairs for metadata or configuration')
        .columns(1)
        .schema([
            KeyValue.make('metadata')
                .label('Metadata')
                .keyLabel('Property')
                .valueLabel('Value')
                .keyPlaceholder('Enter property name...')
                .valuePlaceholder('Enter property value...')
                .maxItems(5)
                .hint('Maximum 5 pairs'),
        ]),
    Section.make('Markdown Editor')
        .description('Toolbar, split preview, counter and fullscreen — all sanitised before display.')
        .columns(1)
        .schema([
            MarkdownEditor.make('readme')
                .label('README — default toolbar')
                .rows(12)
                .fullscreen()
                .hint('GitHub-flavoured markdown. Output is sanitised before display.'),
            MarkdownEditor.make('changelog')
                .label('Changelog — trimmed toolbar with a counter')
                .rows(8)
                .withoutToolbar(['image', 'table'])
                .counter()
                .maxLength(500)
                .hint('withoutToolbar() removes one button without respelling the whole set.'),
            MarkdownEditor.make('notes')
                .label('Notes — edit only')
                .rows(6)
                .mode('edit')
                .modeSwitcher(false)
                .hint('mode(\'edit\') with modeSwitcher(false) hides the preview entirely.'),
        ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Field Types' }]">
        <Head title="New Field Types" />

        <PageHeader
            title="New Field Types"
            description="Hints, ToggleButtons, Markdown, TimePicker, CheckboxList and KeyValue field types."
        />

        <div class="mx-auto mt-6 max-w-4xl space-y-6">
            <Card>
                <CardContent class="pt-6">
                    <FormFields :form="form" :schema="schema" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Form Data</CardTitle>
                    <CardDescription>Live preview of the form state</CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="rounded-md bg-muted p-4 text-sm overflow-auto">{{ JSON.stringify(form, null, 2) }}</pre>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
