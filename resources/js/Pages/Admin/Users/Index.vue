<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import ImportModal from '@/components/ImportModal.vue';
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
    softDeleteActions,
    softDeleteBulkActions,
} from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ref } from 'vue';
import { Plus, Trash2, Download, Upload, UserCog } from 'lucide-vue-next';

const props = defineProps<{
    users: PaginatedData<any>;
    roles: string[];
    filters: Record<string, string>;
}>();

const { can } = usePermissions();
const showImport = ref(false);

const tableFilters = [
    SelectFilter.make('status').options({
        active: 'Active',
        suspended: 'Suspended',
        pending: 'Pending',
    }),
    SelectFilter.make('role').label('Role').options(
        props.roles.map(r => ({ label: r, value: r })),
    ),
    trashedFilter(),
];

const columns: Column[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'roles', label: 'Role' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Created', sortable: true },
];

const isTrashedView = props.filters.trashed === 'only' || props.filters.trashed === 'with';

function bulkAction(action: string, ids: number[]) {
    if (ids.length === 0) return;
    router.post(route('admin.users.bulk-action'), { ids, action });
}

const bulkActions = [
    BulkAction.make('Activate')
        .action((ids) => bulkAction('activate', ids))
        .permission('users.edit'),
    BulkAction.make('Suspend')
        .action((ids) => bulkAction('suspend', ids))
        .permission('users.edit'),
    BulkAction.make('Delete')
        .action((ids) => bulkAction('delete', ids))
        .destructive()
        .requiresConfirmation('Delete Users', 'Are you sure you want to delete the selected users?')
        .icon(Trash2)
        .permission('users.delete'),
    ...(isTrashedView ? softDeleteBulkActions('admin.users') : []),
];

const [editAction, deleteAction, restoreAction, forceDeleteAction] = softDeleteActions('admin.users');

const actions = [
    ActionGroup.make([
        editAction,
        Action.make('Impersonate')
            .icon(UserCog)
            .route('admin.users.impersonate', 'post')
            .permission('users.edit')
            .requiresConfirmation('Impersonate user', 'You will be signed in as this user until you stop impersonating.')
            .visible((row: any) => !row.deleted_at),
        ActionDivider.make(),
        ActionSectionLabel.make('Danger zone'),
        deleteAction.confirmTitle('Delete user').confirmDescription('The account is moved to trash. You can restore it later.'),
        restoreAction,
        forceDeleteAction,
    ]).tooltip('User actions'),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Users' }]">
        <Head title="Users" />

        <PageHeader title="Users" description="Manage user accounts and role assignments.">
            <template #actions>
                <Button variant="outline" as="a" :href="route('admin.users.export-csv')">
                    <Download class="mr-2 size-4" />
                    Export CSV
                </Button>
                <Button v-if="can('users.create')" variant="outline" @click="showImport = true">
                    <Upload class="mr-2 size-4" />
                    Import
                </Button>
                <Button v-if="can('users.create')" as-child>
                    <Link :href="route('admin.users.create')">
                        <Plus class="mr-2 size-4" />
                        Add User
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
                route-name="admin.users.index"
                search-placeholder="Search users..."
            >
                <template #cell-name="{ row }">
                    <div class="flex items-center gap-2">
                        <span :class="{ 'text-muted-foreground line-through': row.deleted_at }">{{ row.name }}</span>
                        <Badge v-if="row.deleted_at" variant="destructive" class="text-[10px]">Trashed</Badge>
                    </div>
                </template>

                <template #cell-roles="{ value }">
                    <Badge v-for="role in value" :key="role" :variant="role === 'super-admin' ? 'destructive' : role === 'admin' ? 'default' : 'secondary'" class="mr-1">
                        {{ role }}
                    </Badge>
                </template>

                <template #cell-status="{ value }">
                    <StatusBadge :status="value" />
                </template>

                <template #cell-created_at="{ value }">
                    <DateCell :value="value" />
                </template>

            </DataTable>
        </div>

        <ImportModal
            v-model:open="showImport"
            title="Import Users"
            resource="users"
        />
    </AuthenticatedLayout>
</template>
