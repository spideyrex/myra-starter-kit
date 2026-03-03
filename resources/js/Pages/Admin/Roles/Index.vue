<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { SimpleTable, RowActions } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { usePermissions } from '@/composables/usePermissions';
import { Pencil, Trash2, Plus, Shield, ShieldAlert } from 'lucide-vue-next';
import type { SimpleTableColumn } from '@/types/admin';

const props = defineProps<{
    roles: Array<{
        id: number;
        name: string;
        users_count: number;
        permissions: string[];
        created_at: string;
    }>;
}>();

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();

const columns: SimpleTableColumn[] = [
    { key: 'name', label: 'Role' },
    { key: 'users_count', label: 'Users' },
    { key: 'permissions', label: 'Permissions' },
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Roles' }]">
        <Head title="Roles" />

        <PageHeader title="Roles" description="Manage roles and their permissions.">
            <template #actions>
                <Button v-if="can('roles.create')" as-child>
                    <Link :href="route('admin.roles.create')">
                        <Plus class="mr-2 size-4" />
                        Add Role
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SimpleTable :columns="columns" :items="roles">
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <ShieldAlert v-if="row.name === 'super-admin'" class="size-4 text-destructive" />
                    <Shield v-else class="size-4 text-muted-foreground" />
                    <span class="font-medium">{{ row.name }}</span>
                    <Badge v-if="row.name === 'super-admin'" variant="destructive" class="text-xs">System</Badge>
                    <Badge v-else-if="row.name === 'admin'" variant="default" class="text-xs">System</Badge>
                </div>
            </template>
            <template #cell-users_count="{ value }">
                <Badge variant="secondary">{{ value }} users</Badge>
            </template>
            <template #cell-permissions="{ row }">
                <span v-if="row.name === 'super-admin'" class="text-sm text-muted-foreground">All (bypass)</span>
                <span v-else class="text-sm text-muted-foreground">{{ row.permissions.length }} permissions</span>
            </template>
            <template #actions="{ row }">
                <RowActions :actions="[
                    { label: 'Edit', icon: Pencil, href: route('admin.roles.edit', row.id), permission: 'roles.edit' },
                    { label: 'Delete', icon: Trash2, permission: 'roles.delete', destructive: true, separator: true, show: !['super-admin', 'admin'].includes(row.name), onClick: () => confirmDelete('admin.roles.destroy', row.id, { title: 'Delete Role', description: 'Are you sure? Users with this role will lose their permissions.' }) },
                ]" />
            </template>
        </SimpleTable>
    </AuthenticatedLayout>
</template>
