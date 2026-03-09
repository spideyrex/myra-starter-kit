<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { RowActions, DateCell } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { Plus, Pencil, Trash2, ExternalLink, RotateCcw, AlertTriangle, Globe, Lock } from 'lucide-vue-next';

const props = defineProps<{
    pages: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();
const { confirm } = useConfirm();

const tableFilters = [
    SelectFilter.make('status').options({
        draft: 'Draft',
        published: 'Published',
        archived: 'Archived',
    }),
    SelectFilter.make('is_public').label('Visibility').options({
        '1': 'Public',
        '0': 'Private',
    }),
    SelectFilter.make('trashed').label('Trash').options({
        '': 'Active Only',
        only: 'Trashed Only',
        with: 'All (incl. Trashed)',
    }),
];

const columns: Column[] = [
    { key: 'title', label: 'Title', sortable: true },
    { key: 'slug', label: 'Slug', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'is_public', label: 'Visibility' },
    { key: 'published_at', label: 'Published', sortable: true },
];

const isTrashedView = props.filters.trashed === 'only' || props.filters.trashed === 'with';

function bulkAction(action: string, ids: number[]) {
    if (ids.length === 0) return;
    router.post(route('admin.pages.bulk-action'), { ids, action });
}

const bulkActions = [
    BulkAction.make('Publish')
        .action((ids) => bulkAction('publish', ids))
        .permission('pages.edit'),
    BulkAction.make('Archive')
        .action((ids) => bulkAction('archive', ids))
        .permission('pages.edit'),
    BulkAction.make('Delete')
        .action((ids) => bulkAction('delete', ids))
        .destructive()
        .requiresConfirmation('Delete Pages', 'Are you sure you want to delete the selected pages?')
        .icon(Trash2)
        .permission('pages.delete'),
    ...(isTrashedView ? [
        BulkAction.make('Restore')
            .action((ids) => bulkAction('restore', ids))
            .icon(RotateCcw)
            .permission('pages.edit'),
        BulkAction.make('Force Delete')
            .action((ids) => bulkAction('force_delete', ids))
            .destructive()
            .requiresConfirmation('Permanently Delete', 'This action cannot be undone. These pages will be permanently removed.')
            .icon(AlertTriangle)
            .permission('pages.delete'),
    ] : []),
];

function getRowActions(row: any) {
    if (row.deleted_at) {
        return [
            { label: 'Restore', icon: RotateCcw, permission: 'pages.edit', onClick: () => router.post(route('admin.pages.restore', row.id)) },
            { label: 'Force Delete', icon: AlertTriangle, permission: 'pages.delete', destructive: true, separator: true, onClick: async () => {
                const confirmed = await confirm({ title: 'Permanently Delete', description: 'This page will be permanently removed and cannot be recovered.', variant: 'destructive', confirmText: 'Delete Forever' });
                if (confirmed) router.delete(route('admin.pages.force-delete', row.id));
            }},
        ];
    }
    return [
        { label: 'Edit', icon: Pencil, href: route('admin.pages.edit', row.id), permission: 'pages.edit' },
        { label: 'View', icon: ExternalLink, href: `/pages/${row.slug}`, external: true },
        { label: 'Delete', icon: Trash2, permission: 'pages.delete', destructive: true, separator: true, onClick: () => confirmDelete('admin.pages.destroy', row.id, { title: 'Delete Page', description: 'Are you sure you want to delete this page?' }) },
    ];
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Content' }, { label: 'Pages' }]">
        <Head title="Pages" />

        <PageHeader title="Pages" description="Manage static pages like Terms of Service, Privacy Policy, and more.">
            <template #actions>
                <Button v-if="can('pages.create')" as-child>
                    <Link :href="route('admin.pages.create')">
                        <Plus class="mr-2 size-4" />
                        Add Page
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="pages"
                :filters="filters"
                :table-filters="tableFilters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.pages.index"
                search-placeholder="Search pages..."
            >
                <template #cell-title="{ row }">
                    <div class="flex items-center gap-2">
                        <span :class="{ 'text-muted-foreground line-through': row.deleted_at }">{{ row.title }}</span>
                        <Badge v-if="row.deleted_at" variant="destructive" class="text-[10px]">Trashed</Badge>
                    </div>
                </template>

                <template #cell-status="{ value }">
                    <StatusBadge :status="value" />
                </template>

                <template #cell-is_public="{ value }">
                    <div class="flex items-center gap-1.5">
                        <Globe v-if="value" class="size-4 text-success" />
                        <Lock v-else class="size-4 text-muted-foreground" />
                        <span class="text-sm">{{ value ? 'Public' : 'Private' }}</span>
                    </div>
                </template>

                <template #cell-published_at="{ value }">
                    <DateCell :value="value" />
                </template>

                <template #actions="{ row }">
                    <RowActions :actions="getRowActions(row)" />
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
