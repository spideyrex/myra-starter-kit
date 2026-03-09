<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction, Action, DeleteAction } from '@/composables/useTableActions';
import { useConfirmAction } from '@/composables/useConfirmAction';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Trash2, RotateCcw, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    users: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { confirmDelete, confirmPost } = useConfirmAction();

const columns = [
    TextColumn.make('name').label('Name').sortable().grow(),
    TextColumn.make('email').sortable(),
    BadgeColumn.make('status').sortable().colors({
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const tableFilters = [
    SelectFilter.make('trashed').label('Trash Status').options({
        '': 'Active Only',
        only: 'Trashed Only',
        with: 'All (Include Trashed)',
    }),
];

const actions = [
    // Edit action — only for non-trashed
    Action.make('Edit')
        .icon(Pencil)
        .action(() => {})
        .visible((row: any) => !row.deleted_at),

    // Restore — only for trashed
    Action.make('Restore')
        .icon(RotateCcw)
        .action((row: any) => confirmPost(
            'admin.demo.restore',
            row.id,
            {},
            { title: 'Restore User', description: 'Restore this user from trash?', confirmText: 'Restore' },
        ))
        .visible((row: any) => !!row.deleted_at),

    // Soft delete — only for non-trashed
    DeleteAction.make('admin.demo.soft-delete')
        .confirmTitle('Move to Trash')
        .confirmDescription('This user will be moved to trash. You can restore them later.')
        .visible((row: any) => !row.deleted_at),

    // Force delete — only for trashed
    Action.make('Force Delete')
        .icon(Trash2)
        .destructive()
        .separator()
        .action((row: any) => confirmDelete(
            'admin.demo.force-delete',
            row.id,
            { title: 'Permanently Delete', description: 'This action cannot be undone. The user will be permanently removed.', confirmText: 'Delete Forever' },
        ))
        .visible((row: any) => !!row.deleted_at),
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Users', 'Are you sure you want to delete the selected users?')
        .icon(Trash2),
    BulkAction.make('Restore')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'restore' }))
        .requiresConfirmation('Restore Users', 'Restore the selected users from trash?')
        .icon(RotateCcw),
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
