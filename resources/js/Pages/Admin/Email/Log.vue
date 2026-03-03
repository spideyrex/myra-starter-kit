<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { DateCell } from '@/components/admin';
import { SelectFilter } from '@/composables/useTableFilters';
import type { PaginatedData } from '@/types';

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
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Email' }, { label: 'Email Log' }]">
        <Head title="Email Log" />
        <PageHeader title="Email Log" description="View sent email history." />

        <div class="mt-6">
            <DataTable :columns="columns" :data="logs" :filters="filters" :table-filters="tableFilters" route-name="admin.email-logs.index" search-placeholder="Search by email or subject...">
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
