<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import NotificationDetailDialog from '@/components/NotificationDetailDialog.vue';
import { DateCell } from '@/components/admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { Plus, Eye, CheckCheck, Trash2 } from 'lucide-vue-next';

interface AdminNotification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url: string | null;
    user_name: string;
    user_email: string;
    read_at: string | null;
    created_at: string;
}

defineProps<{
    notifications: PaginatedData<AdminNotification>;
    filters: Record<string, string>;
}>();

const { can } = usePermissions();

const tableFilters = [
    SelectFilter.make('type').options({
        system: 'System',
        user_action: 'User Action',
        security_alert: 'Security Alert',
    }),
    SelectFilter.make('status').label('Read Status').options({
        read: 'Read',
        unread: 'Unread',
    }),
];

const columns: Column[] = [
    { key: 'type', label: 'Type' },
    { key: 'title', label: 'Title', sortable: true },
    { key: 'user_name', label: 'Recipient', sortable: true },
    { key: 'read_at', label: 'Status' },
    { key: 'created_at', label: 'Sent At', sortable: true },
];

const typeVariant: Record<string, string> = {
    system: 'default',
    user_action: 'secondary',
    security_alert: 'destructive',
};

const typeLabel: Record<string, string> = {
    system: 'System',
    user_action: 'User Action',
    security_alert: 'Security Alert',
};

const dialogOpen = ref(false);
const selectedNotification = ref<AdminNotification | null>(null);

function openNotification(row: AdminNotification) {
    selectedNotification.value = row;
    dialogOpen.value = true;
}

const bulkActions = [
    BulkAction.make('Mark Read')
        .action((ids) => router.post(route('admin.notifications.bulk-action'), { ids, action: 'mark_read' }))
        .icon(CheckCheck),
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.notifications.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Notifications', 'Are you sure you want to delete the selected notifications?')
        .icon(Trash2),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'Notifications' }]">
        <Head title="Notification Log" />
        <PageHeader title="Notification Log" description="View all notifications sent to users.">
            <template #actions>
                <Button v-if="can('notifications.create')" as-child>
                    <Link :href="route('admin.notifications.create')">
                        <Plus class="mr-2 size-4" />
                        Send Notification
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="notifications"
                :filters="filters"
                :table-filters="tableFilters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.notifications.index"
                search-placeholder="Search by recipient or content..."
            >
                <template #cell-type="{ value }">
                    <Badge :variant="(typeVariant[value as string] as any) ?? 'default'">
                        {{ typeLabel[value as string] ?? value }}
                    </Badge>
                </template>
                <template #cell-title="{ row }">
                    <button
                        type="button"
                        class="text-left hover:underline"
                        @click="openNotification(row)"
                    >
                        <p class="text-sm font-medium">{{ row.title }}</p>
                        <p class="text-xs text-muted-foreground line-clamp-1">{{ row.message }}</p>
                    </button>
                </template>
                <template #cell-user_name="{ row }">
                    <div>
                        <p class="text-sm font-medium">{{ row.user_name }}</p>
                        <p class="text-xs text-muted-foreground">{{ row.user_email }}</p>
                    </div>
                </template>
                <template #cell-read_at="{ value }">
                    <Badge :variant="value ? 'outline' : 'secondary'">
                        {{ value ? 'Read' : 'Unread' }}
                    </Badge>
                </template>
                <template #cell-created_at="{ value }">
                    <DateCell :value="value as string" format="relative" />
                </template>
                <template #actions="{ row }">
                    <Button variant="ghost" size="sm" class="size-8 p-0" @click="openNotification(row)">
                        <Eye class="size-4" />
                    </Button>
                </template>
            </DataTable>
        </div>

        <NotificationDetailDialog
            v-model:open="dialogOpen"
            :notification="selectedNotification"
            :show-recipient="true"
        />
    </AuthenticatedLayout>
</template>
