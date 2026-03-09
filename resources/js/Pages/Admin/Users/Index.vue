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
import { RowActions, DateCell } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ref } from 'vue';
import { Plus, Pencil, Trash2, Download, Upload, UserCog, RotateCcw, AlertTriangle } from 'lucide-vue-next';

const props = defineProps<{
    users: PaginatedData<any>;
    roles: string[];
    filters: Record<string, string>;
}>();

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();
const { confirm } = useConfirm();
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
    SelectFilter.make('trashed').label('Trash').options({
        '': 'Active Only',
        only: 'Trashed Only',
        with: 'All (incl. Trashed)',
    }),
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
    ...(isTrashedView ? [
        BulkAction.make('Restore')
            .action((ids) => bulkAction('restore', ids))
            .icon(RotateCcw)
            .permission('users.edit'),
        BulkAction.make('Force Delete')
            .action((ids) => bulkAction('force_delete', ids))
            .destructive()
            .requiresConfirmation('Permanently Delete', 'This action cannot be undone. These users will be permanently removed.')
            .icon(AlertTriangle)
            .permission('users.delete'),
    ] : []),
];

function getRowActions(row: any) {
    if (row.deleted_at) {
        return [
            { label: 'Restore', icon: RotateCcw, permission: 'users.edit', onClick: () => router.post(route('admin.users.restore', row.id)) },
            { label: 'Force Delete', icon: AlertTriangle, permission: 'users.delete', destructive: true, separator: true, onClick: async () => {
                const confirmed = await confirm({ title: 'Permanently Delete', description: 'This user will be permanently removed and cannot be recovered.', variant: 'destructive', confirmText: 'Delete Forever' });
                if (confirmed) router.delete(route('admin.users.force-delete', row.id));
            }},
        ];
    }
    return [
        { label: 'Edit', icon: Pencil, href: route('admin.users.edit', row.id), permission: 'users.edit' },
        { label: 'Impersonate', icon: UserCog, permission: 'users.edit', onClick: () => router.post(route('admin.users.impersonate', row.id)) },
        { label: 'Delete', icon: Trash2, permission: 'users.delete', destructive: true, separator: true, onClick: () => confirmDelete('admin.users.destroy', row.id, { title: 'Delete User', description: 'Are you sure you want to delete this user? This action cannot be undone.' }) },
    ];
}
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

                <template #actions="{ row }">
                    <RowActions :actions="getRowActions(row)" />
                </template>
            </DataTable>
        </div>

        <ImportModal
            v-model:open="showImport"
            title="Import Users"
            :preview-route="route('admin.import.preview')"
            :execute-route="route('admin.import.execute')"
            resource="users"
            :expected-columns="['name', 'email', 'password', 'phone', 'status', 'role']"
        />
    </AuthenticatedLayout>
</template>
