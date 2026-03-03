<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import type { Column } from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableRow, TableCell } from '@/components/ui/table';
import { DateCell } from '@/components/admin';
import { SelectFilter } from '@/composables/useTableFilters';
import type { PaginatedData } from '@/types';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps<{
    activities: PaginatedData<any>;
    filters: Record<string, string>;
    logNames: string[];
}>();

const expandedId = ref<number | null>(null);

const tableFilters = [
    SelectFilter.make('log_name').label('Log Name').options(
        props.logNames.map(name => ({ label: name, value: name })),
    ),
];

const columns: Column[] = [
    { key: 'description', label: 'Description', sortable: true },
    { key: 'causer_name', label: 'User', sortable: true },
    { key: 'subject_type', label: 'Subject' },
    { key: 'event', label: 'Event' },
    { key: 'created_at', label: 'Date', sortable: true },
];

function toggleExpand(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

function formatKey(key: string): string {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'Activity Log' }]">
        <Head title="Activity Log" />
        <PageHeader title="Activity Log" description="View system activity and model changes." />

        <div class="mt-6">
            <DataTable :columns="columns" :data="activities" :filters="filters" :table-filters="tableFilters" route-name="admin.activity-logs.index" search-placeholder="Search activity...">
                <template #cell-event="{ value }">
                    <Badge variant="outline">{{ value || 'N/A' }}</Badge>
                </template>
                <template #cell-subject_type="{ value }">
                    <span class="text-sm">{{ value || '-' }}</span>
                </template>
                <template #cell-created_at="{ value }">
                    <DateCell :value="value" format="datetime" />
                </template>
                <template #actions="{ row }">
                    <Button v-if="row.properties && (row.properties.old || row.properties.attributes)" variant="ghost" size="sm" @click="toggleExpand(row.id)">
                        <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': expandedId === row.id }" />
                    </Button>
                </template>
                <template #expanded-row="{ row }">
                    <TableRow v-if="expandedId === row.id && row.properties && (row.properties.old || row.properties.attributes)">
                        <TableCell :colspan="columns.length + 1" class="bg-muted/30 p-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="row.properties.old">
                                    <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Before</p>
                                    <div class="space-y-1 rounded-md bg-background p-3 text-sm">
                                        <div v-for="(value, key) in row.properties.old" :key="key" class="flex justify-between gap-2">
                                            <span class="font-medium text-muted-foreground">{{ formatKey(String(key)) }}</span>
                                            <span class="truncate text-right">{{ value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="row.properties.attributes">
                                    <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">After</p>
                                    <div class="space-y-1 rounded-md bg-background p-3 text-sm">
                                        <div v-for="(value, key) in row.properties.attributes" :key="key" class="flex justify-between gap-2">
                                            <span class="font-medium text-muted-foreground">{{ formatKey(String(key)) }}</span>
                                            <span class="truncate text-right" :class="{ 'text-success font-medium': row.properties.old && row.properties.old[key] !== value }">{{ value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                    </TableRow>
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
