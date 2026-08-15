<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { trashedFilter } from '@/composables/useTableFilters';
import { Action, ActionDivider, ActionGroup, ActionSectionLabel, BulkAction, softDeleteActions, softDeleteBulkActions } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Trash2, Pencil } from 'lucide-vue-next';

defineProps<{
    users: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const columns = [
    TextColumn.make('name').label('Name').sortable().grow(),
    TextColumn.make('email').sortable(),
    BadgeColumn.make('status').sortable().colors({
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const tableFilters = [trashedFilter()];

// The whole trash workflow in one call — Delete / Restore / Delete permanently,
// each with the right icon, confirmation and deleted_at visibility rule.
const [deleteAction, restoreAction, forceDeleteAction] = softDeleteActions('admin.demo', {
    module: false,
    edit: false,
    deleteRoute: 'admin.demo.soft-delete',
});

const actions = [
    ActionGroup.make([
        Action.make('Edit').icon(Pencil).action(() => {}).visible((row: any) => !row.deleted_at),
        ActionDivider.make(),
        ActionSectionLabel.make('Trash'),
        deleteAction
            .confirmTitle('Move to trash')
            .confirmDescription('This user will be moved to trash. You can restore them later.'),
        restoreAction,
        forceDeleteAction,
    ]).tooltip('Row actions'),
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Users', 'Are you sure you want to delete the selected users?')
        .icon(Trash2),
    ...softDeleteBulkActions('admin.demo', false),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Soft Deletes' }]">
        <Head title="Soft Deletes Demo" />

        <PageHeader title="Soft Deletes & Trash" description="Trash, restore, and force-delete workflow with conditional row actions.">
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
                :data="users"
                :filters="filters"
                :table-filters="tableFilters"
                :selectable="true"
                :bulk-actions="bulkActions"
                :actions="actions"
                route-name="admin.demo.soft-deletes"
                search-placeholder="Search users..."
            >
                <template #cell-name="{ row }">
                    <div class="flex items-center gap-2">
                        <span>{{ row.name }}</span>
                        <Badge v-if="row.deleted_at" variant="destructive" class="text-[10px] px-1 py-0">Trashed</Badge>
                    </div>
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
