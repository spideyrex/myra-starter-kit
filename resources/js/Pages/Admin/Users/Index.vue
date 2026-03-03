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
import { usePermissions } from '@/composables/usePermissions';
import { SelectFilter } from '@/composables/useTableFilters';
import type { PaginatedData } from '@/types';
import { Plus, Pencil, Trash2, Download, UserCog } from 'lucide-vue-next';

const props = defineProps<{
    users: PaginatedData<any>;
    roles: string[];
    filters: Record<string, string>;
}>();

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();

const tableFilters = [
    SelectFilter.make('status').options({
        active: 'Active',
        suspended: 'Suspended',
        pending: 'Pending',
    }),
    SelectFilter.make('role').label('Role').options(
        props.roles.map(r => ({ label: r, value: r })),
    ),
];

const columns: Column[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'roles', label: 'Role' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Created', sortable: true },
];

function bulkAction(action: string, ids: number[]) {
    if (ids.length === 0) return;
    router.post(route('admin.users.bulk-action'), { ids, action });
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
                route-name="admin.users.index"
                search-placeholder="Search users..."
            >
                <template #toolbar="{ selectedIds }">
                    <template v-if="selectedIds.length > 0">
                        <Button variant="outline" size="sm" @click="bulkAction('activate', selectedIds)">Activate</Button>
                        <Button variant="outline" size="sm" @click="bulkAction('suspend', selectedIds)">Suspend</Button>
                        <Button variant="destructive" size="sm" @click="bulkAction('delete', selectedIds)">Delete</Button>
                    </template>
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
                    <RowActions :actions="[
                        { label: 'Edit', icon: Pencil, href: route('admin.users.edit', row.id), permission: 'users.edit' },
                        { label: 'Impersonate', icon: UserCog, permission: 'users.edit', onClick: () => router.post(route('admin.users.impersonate', row.id)) },
                        { label: 'Delete', icon: Trash2, permission: 'users.delete', destructive: true, separator: true, onClick: () => confirmDelete('admin.users.destroy', row.id, { title: 'Delete User', description: 'Are you sure you want to delete this user? This action cannot be undone.' }) },
                    ]" />
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
