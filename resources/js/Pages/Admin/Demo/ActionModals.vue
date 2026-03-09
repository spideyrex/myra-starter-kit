<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { Action, DeleteAction } from '@/composables/useTableActions';
import { TextInput, Select, Textarea } from '@/composables/useFormSchema';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Pencil } from 'lucide-vue-next';

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
    </AuthenticatedLayout>
</template>
