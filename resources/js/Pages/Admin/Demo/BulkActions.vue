<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { SelectFilter } from '@/composables/useTableFilters';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Download, Trash2, Archive, CheckCircle } from 'lucide-vue-next';

const props = defineProps<{
    products: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const columns = [
    TextColumn.make('name').label('Product Name').sortable().grow(),
    TextColumn.make('category').sortable(),
    TextColumn.make('price').sortable().money('USD'),
    TextColumn.make('stock').sortable().label('In Stock'),
    BadgeColumn.make('status').sortable().colors({
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        draft: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    }),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const tableFilters = [
    SelectFilter.make('category').options({
        Electronics: 'Electronics',
        Clothing: 'Clothing',
        Books: 'Books',
        'Home & Garden': 'Home & Garden',
    }),
    SelectFilter.make('status').options({
        active: 'Active',
        draft: 'Draft',
        archived: 'Archived',
    }),
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Products', 'Are you sure you want to delete the selected products?')
        .icon(Trash2),
    BulkAction.make('Archive')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'archive' }))
        .requiresConfirmation('Archive Products', 'Move selected products to archive?')
        .icon(Archive),
    BulkAction.make('Activate')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'activate' }))
        .icon(CheckCircle),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Bulk Actions' }]">
        <Head title="Bulk Actions Demo" />

        <PageHeader title="Bulk Actions" description="DataTable with row selection, bulk operations, filters, and export.">
            <template #actions>
                <Button variant="outline" as="a" :href="route('admin.demo.export-csv')">
                    <Download class="mr-2 size-4" />
                    Export CSV
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="products"
                :filters="filters"
                :table-filters="tableFilters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.demo.bulk-actions"
                search-placeholder="Search products..."
            />
        </div>
    </AuthenticatedLayout>
</template>
