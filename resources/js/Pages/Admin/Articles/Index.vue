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
    articles: PaginatedData<any>;
    categories: Array<{ id: number; name: string }>;
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
    SelectFilter.make('category_id').label('Category').options(
        props.categories.map(c => ({ label: c.name, value: String(c.id) })),
    ),
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
    { key: 'category', label: 'Category' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'is_public', label: 'Visibility' },
    { key: 'published_at', label: 'Published', sortable: true },
];

const isTrashedView = props.filters.trashed === 'only' || props.filters.trashed === 'with';

function bulkAction(action: string, ids: number[]) {
    if (ids.length === 0) return;
    router.post(route('admin.articles.bulk-action'), { ids, action });
}

const bulkActions = [
    BulkAction.make('Publish')
        .action((ids) => bulkAction('publish', ids))
        .permission('articles.edit'),
    BulkAction.make('Archive')
        .action((ids) => bulkAction('archive', ids))
        .permission('articles.edit'),
    BulkAction.make('Delete')
        .action((ids) => bulkAction('delete', ids))
        .destructive()
        .requiresConfirmation('Delete Articles', 'Are you sure you want to delete the selected articles?')
        .icon(Trash2)
        .permission('articles.delete'),
    ...(isTrashedView ? [
        BulkAction.make('Restore')
            .action((ids) => bulkAction('restore', ids))
            .icon(RotateCcw)
            .permission('articles.edit'),
        BulkAction.make('Force Delete')
            .action((ids) => bulkAction('force_delete', ids))
            .destructive()
            .requiresConfirmation('Permanently Delete', 'This action cannot be undone. These articles will be permanently removed.')
            .icon(AlertTriangle)
            .permission('articles.delete'),
    ] : []),
];

function getRowActions(row: any) {
    if (row.deleted_at) {
        return [
            { label: 'Restore', icon: RotateCcw, permission: 'articles.edit', onClick: () => router.post(route('admin.articles.restore', row.id)) },
            { label: 'Force Delete', icon: AlertTriangle, permission: 'articles.delete', destructive: true, separator: true, onClick: async () => {
                const confirmed = await confirm({ title: 'Permanently Delete', description: 'This article will be permanently removed and cannot be recovered.', variant: 'destructive', confirmText: 'Delete Forever' });
                if (confirmed) router.delete(route('admin.articles.force-delete', row.id));
            }},
        ];
    }
    return [
        { label: 'Edit', icon: Pencil, href: route('admin.articles.edit', row.id), permission: 'articles.edit' },
        { label: 'View', icon: ExternalLink, href: `/blog/${row.slug}`, external: true },
        { label: 'Delete', icon: Trash2, permission: 'articles.delete', destructive: true, separator: true, onClick: () => confirmDelete('admin.articles.destroy', row.id, { title: 'Delete Article', description: 'Are you sure you want to delete this article?' }) },
    ];
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Content' }, { label: 'Articles' }]">
        <Head title="Articles" />

        <PageHeader title="Articles" description="Manage blog articles and content.">
            <template #actions>
                <Button v-if="can('articles.create')" as-child>
                    <Link :href="route('admin.articles.create')">
                        <Plus class="mr-2 size-4" />
                        Add Article
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="articles"
                :filters="filters"
                :table-filters="tableFilters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.articles.index"
                search-placeholder="Search articles..."
            >
                <template #cell-title="{ row }">
                    <div class="flex items-center gap-2">
                        <span :class="{ 'text-muted-foreground line-through': row.deleted_at }">{{ row.title }}</span>
                        <Badge v-if="row.deleted_at" variant="destructive" class="text-[10px]">Trashed</Badge>
                    </div>
                </template>

                <template #cell-category="{ row }">
                    <Badge v-if="row.category" variant="secondary">{{ row.category.name }}</Badge>
                    <span v-else class="text-muted-foreground">—</span>
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
