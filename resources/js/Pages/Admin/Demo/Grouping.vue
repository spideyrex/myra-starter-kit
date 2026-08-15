<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn, ToggleColumn, CheckboxColumn } from '@/composables/useTableSchema';
import { ArrowLeft } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    orders: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const columns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('order_number').label('Order').sortable(),
    TextColumn.make('customer').label('Customer').sortable(),
    BadgeColumn.make('status').label('Status').colors({
        completed: 'default',
        processing: 'secondary',
        pending: 'outline',
        cancelled: 'destructive',
    }),
    // Inline-editable columns render correctly inside groups too.
    CheckboxColumn.make('is_paid')
        .label('Paid')
        .rowLabel(row => `Mark ${row.order_number} paid`)
        .onUpdate((row, value) => { row.is_paid = value; toast.success(`${row.order_number} paid: ${value}`); }),
    ToggleColumn.make('is_rush')
        .label('Rush')
        .rowLabel(row => `Rush ${row.order_number}`)
        .onUpdate((row, value) => { row.is_rush = value; toast.success(`${row.order_number} rush: ${value}`); }),
    TextColumn.make('quantity').label('Qty').alignEnd().summarize('sum'),
    TextColumn.make('price').label('Price').money().alignEnd().sortable()
        .summary({ type: 'sum', label: 'Total', currency: 'USD' }),
    TextColumn.make('rating').label('Rating').alignEnd()
        .summary({ type: 'median', label: 'Median', decimals: 1 }),
    TextColumn.make('delivery_days').label('Delivery').alignEnd().suffix(' d')
        .summary({ type: 'range', label: 'Range', separator: ' to ', decimals: 0 }),
    DateColumn.make('created_at').label('Date').format('date').sortable(),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Row Grouping' },
    ]">
        <Head title="Row Grouping Demo" />

        <PageHeader title="Row Grouping & Summarizers" description="Group rows by column values with aggregated summaries.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Orders Grouped by Status</CardTitle>
                    <CardDescription>
                        Click group headers to expand/collapse. Summary rows aggregate each group: <code>sum</code>,
                        <code>median</code> and a formatted <code>range</code>. The Paid and Rush columns are
                        inline-editable and render correctly inside groups.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="columns"
                        :data="orders"
                        :filters="filters"
                        route-name="admin.demo.grouping"
                        group-by="status"
                        search-placeholder="Search orders..."
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
