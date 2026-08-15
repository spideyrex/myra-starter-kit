<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DateCell } from '@/components/admin';
import { usePermissions } from '@/composables/usePermissions';
import { SelectFilter, trashedFilter } from '@/composables/useTableFilters';
import {
    Action,
    ActionDivider,
    ActionSectionLabel,
    ActionGroup,
    BulkAction,
    ReplicateAction,
    softDeleteActions,
    softDeleteBulkActions,
} from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { Plus, Trash2, ExternalLink, Globe, Lock } from 'lucide-vue-next';

const props = defineProps<{
    pages: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { can } = usePermissions();

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
    trashedFilter(),
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
    ...(isTrashedView ? softDeleteBulkActions('admin.pages') : []),
];

const [editAction, deleteAction, restoreAction, forceDeleteAction] = softDeleteActions('admin.pages');

const actions = [
    ActionGroup.make([
        editAction,
        Action.make('View on site')
            .icon(ExternalLink)
            .url((row: any) => `/pages/${row.slug}`)
            .external()
            .visible((row: any) => !row.deleted_at),
        ActionSectionLabel.make('Duplicate'),
        ReplicateAction.make('admin.pages.replicate')
            .permission('pages.create')
            .except(['published_at'])
            .suffix('title')
            .overrides(() => ({ status: 'draft', is_public: false }))
            .visible((row: any) => !row.deleted_at),
        ActionDivider.make(),
        ActionSectionLabel.make('Danger zone'),
        deleteAction.confirmTitle('Delete page').confirmDescription('The page is moved to trash. You can restore it later.'),
        restoreAction,
        forceDeleteAction,
    ]).tooltip('Page actions'),
];
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
                :actions="actions"
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
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
