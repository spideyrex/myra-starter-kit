<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { TextColumn, DateColumn, BadgeColumn } from '@/composables/useTableSchema';
import { SelectFilter, TernaryFilter, Filter, DateRangeFilter, QueryBuilderFilter } from '@/composables/useTableFilters';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    products: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const columns = [
    TextColumn.make('name').label('Name').sortable(),
    TextColumn.make('category').label('Category').sortable(),
    TextColumn.make('price').label('Price').sortable().money(),
    TextColumn.make('stock').label('Stock').sortable(),
    BadgeColumn.make('status').label('Status').colors({
        active: 'default',
        draft: 'secondary',
        archived: 'outline',
    }),
    DateColumn.make('created_at').label('Created').sortable().format('date'),
];

const tableFilters = [
    // Select filter — dropdown with predefined options
    SelectFilter.make('category')
        .label('Category')
        .placeholder('All Categories')
        .options([
            { label: 'Electronics', value: 'Electronics' },
            { label: 'Clothing', value: 'Clothing' },
            { label: 'Books', value: 'Books' },
            { label: 'Home & Garden', value: 'Home & Garden' },
        ]),

    // Select filter — status dropdown
    SelectFilter.make('status')
        .label('Status')
        .placeholder('All Statuses')
        .options({
            active: 'Active',
            draft: 'Draft',
            archived: 'Archived',
        }),

    // Ternary filter — Yes / No / All
    TernaryFilter.make('in_stock')
        .label('In Stock')
        .trueLabel('In Stock')
        .falseLabel('Out of Stock'),

    // Checkbox filter — simple toggle
    Filter.make('high_value')
        .label('High Value (≥ $500)'),

    // Date range filter — from/to date pickers
    DateRangeFilter.make('created')
        .label('Created Date')
        .minDate('2024-01-01')
        .maxDate('2026-12-31'),

    // Query builder filter — recursive AND/OR rule groups
    QueryBuilderFilter.make('query_builder')
        .label('Advanced Query Builder')
        .fields([
            { name: 'name', label: 'Product Name', operators: ['=', 'contains', 'starts_with'] },
            { name: 'price', label: 'Price', operators: ['=', '>', '<', '>=', '<='] },
            { name: 'category', label: 'Category', operators: ['=', '!='] },
            { name: 'status', label: 'Status', operators: ['=', '!='] },
            { name: 'stock', label: 'Stock', operators: ['=', '>', '<', '>=', '<='] },
        ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Advanced Filters' }]">
        <Head title="Advanced Filters" />

        <PageHeader
            title="Advanced Filters"
            description="Demonstrates all 5 filter types: SelectFilter, TernaryFilter, CheckboxFilter, DateRangeFilter, and QueryBuilderFilter with recursive AND/OR group nesting."
        />

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="products"
                :filters="filters"
                :table-filters="tableFilters"
                route-name="admin.demo.advanced-filters"
            />
        </div>
    </AuthenticatedLayout>
</template>
