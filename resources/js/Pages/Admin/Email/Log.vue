<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { DateCell } from '@/components/admin';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { Download, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    logs: PaginatedData<any>;
    filters: Record<string, string>;
    statuses: string[];
}>();

const tableFilters = [
    SelectFilter.make('status').options(
        props.statuses.map(s => ({ label: s.charAt(0).toUpperCase() + s.slice(1), value: s })),
    ),
];

const columns: Column[] = [
    { key: 'to', label: 'To', sortable: true },
    { key: 'subject', label: 'Subject', sortable: true },
    { key: 'template_slug', label: 'Template' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'sent_at', label: 'Sent At', sortable: true },
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.email-logs.bulk-action'), { ids }))
        .destructive()
        .requiresConfirmation('Delete Email Logs', 'Are you sure you want to delete the selected email logs?')
        .icon(Trash2),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Email' }, { label: 'Email Log' }]">
        <Head title="Email Log" />
        <PageHeader title="Email Log" description="View sent email history.">
            <template #actions>
                <Button variant="outline" as="a" :href="route('admin.email-logs.export-csv')">
                    <Download class="mr-2 size-4" />
                    Export CSV
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable :columns="columns" :data="logs" :filters="filters" :table-filters="tableFilters" :selectable="true" :bulk-actions="bulkActions" route-name="admin.email-logs.index" search-placeholder="Search by email or subject...">
                <template #cell-status="{ value }">
                    <StatusBadge :status="value" />
                </template>
                <template #cell-sent_at="{ value }">
                    <DateCell :value="value" format="datetime" />
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
