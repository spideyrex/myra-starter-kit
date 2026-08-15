<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import {
    Action,
    ActionDivider,
    ActionGroup,
    ActionSectionLabel,
    DeleteAction,
    ReplicateAction,
} from '@/composables/useTableActions';
import { TextInput, Select, Textarea } from '@/composables/useFormSchema';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Pencil, Archive, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    tasks: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const columns = [
    TextColumn.make('title').label('Task').sortable().grow(),
    TextColumn.make('assignee').label('Assigned To').sortable(),
    BadgeColumn.make('priority').sortable().colors({
        low: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }),
    BadgeColumn.make('status').sortable().colors({
        open: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        in_progress: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    }),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const actions = [
    // Edit via modal — opens form in a dialog instead of navigating away
    Action.make('Edit')
        .icon(Pencil)
        .modal({
            schema: [
                TextInput.make('title').label('Task Title').required(),
                Textarea.make('description').label('Description').rows(3),
                Select.make('priority').options({
                    low: 'Low',
                    medium: 'Medium',
                    high: 'High',
                }).required(),
                Select.make('status').options({
                    open: 'Open',
                    in_progress: 'In Progress',
                    completed: 'Completed',
                }).required(),
                TextInput.make('assignee').label('Assignee'),
            ],
            routeName: 'admin.demo.update-task',
            method: 'put',
            defaultsFn: (row: any) => ({
                title: row.title,
                description: row.description,
                priority: row.priority,
                status: row.status,
                assignee: row.assignee,
            }),
            submitLabel: 'Update Task',
        }),

    // Change status via a simple modal
    Action.make('Change Status')
        .modal({
            schema: [
                Select.make('status')
                    .label('New Status')
                    .options({
                        open: 'Open',
                        in_progress: 'In Progress',
                        completed: 'Completed',
                    })
                    .required(),
            ],
            routeName: 'admin.demo.update-task',
            method: 'put',
            defaultsFn: (row: any) => ({
                status: row.status,
            }),
            submitLabel: 'Update Status',
        }),

    // Delete
    DeleteAction.make('admin.demo.delete-task'),
];

// Grouped variant: section headings, a divider, a nested submenu, a badge on the
// trigger, and collapseAfter — one ActionGroup, no per-page markup.
const groupedActions = [
    ActionGroup.make([
        ActionSectionLabel.make('Edit'),
        Action.make('Edit')
            .icon(Pencil)
            .action(() => {}),
        Action.make('Archive')
            .icon(Archive)
            .color('warning')
            .route('admin.demo.archive-task', 'post')
            .requiresConfirmation('Archive task', 'The task is hidden from the active board.')
            .successMessage('Task archived.'),

        ActionSectionLabel.make('Duplicate'),
        // Payload-driven: the server owns the copy.
        ReplicateAction.make('admin.demo.replicate-task')
            .except(['assignee'])
            .withRelations(['comments'])
            .suffix('title')
            .successMessage('Task duplicated.'),
        // Edit-before-save: rides the existing ActionModal path.
        ReplicateAction.make('admin.demo.replicate-task')
            .label('Duplicate and edit…')
            .icon(Sparkles)
            .schema([
                TextInput.make('title').label('New title').required(),
                Select.make('priority').options({ low: 'Low', medium: 'Medium', high: 'High' }).required(),
            ])
            .overrides((row: any) => ({ title: `${row.title} (copy)`, status: 'open' })),

        ActionDivider.make(),
        ActionSectionLabel.make('More'),
        ActionGroup.make([
            Action.make('Copy link').action(() => {}),
            Action.make('Open in new tab')
                .url((row: any) => `/demo/tasks/${row.id}`)
                .external(),
        ]).label('Share').icon(Sparkles),

        ActionDivider.make(),
        DeleteAction.make('admin.demo.delete-task'),
    ])
        .tooltip('Task actions')
        .badge((row: any) => (row.priority === 'high' ? '!' : null))
        .width('lg')
        .collapseAfter(3),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Action Modals' }]">
        <Head title="Action Modals Demo" />

        <PageHeader title="Action Modals" description="Inline CRUD via modal dialogs — no page navigation required.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="tasks"
                :filters="filters"
                :actions="actions"
                route-name="admin.demo.action-modals"
                search-placeholder="Search tasks..."
            />
        </div>

        <div class="mt-10">
            <h2 class="text-lg font-semibold">Action grouping</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Section headings, dividers, a nested submenu, a trigger badge and
                <code>collapseAfter(3)</code> — all declared in one <code>ActionGroup</code>.
            </p>
            <div class="mt-4">
                <DataTable
                    :columns="columns"
                    :data="tasks"
                    :filters="filters"
                    :actions="groupedActions"
                    :searchable="false"
                    route-name="admin.demo.action-modals"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
