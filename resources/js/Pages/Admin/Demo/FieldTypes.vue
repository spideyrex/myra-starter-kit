<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import FormFields from '@/components/admin/FormFields.vue';
import { TimePicker, CheckboxList, KeyValue, MarkdownEditor, Section } from '@/composables/useFormSchema';

const form = reactive({
    meeting_time: '09:00',
    permissions: ['read', 'write'],
    metadata: [
        { key: 'version', value: '1.0' },
        { key: 'author', value: 'Admin' },
    ],
    readme: '# Hello World\n\nThis is a **markdown** editor.\n\n- Item 1\n- Item 2\n',
    errors: {} as Record<string, string>,
});

const schema = [
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
        .description('Split-pane editor with live preview')
        .columns(1)
        .schema([
            MarkdownEditor.make('readme')
                .label('README')
                .rows(12)
                .hint('Write markdown on the left, see rendered output on the right'),
        ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Field Types' }]">
        <Head title="New Field Types" />

        <PageHeader
            title="New Field Types"
            description="TimePicker, CheckboxList, KeyValue, and MarkdownEditor field types."
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
