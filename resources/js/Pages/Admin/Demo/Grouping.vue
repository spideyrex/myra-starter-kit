<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { ArrowLeft } from 'lucide-vue-next';
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
    TextColumn.make('quantity').label('Qty').alignEnd().summarize('sum'),
    TextColumn.make('price').label('Price').money().alignEnd().sortable().summarize('sum'),
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
                    <CardDescription>Click group headers to expand/collapse. Summary rows show aggregated totals per group.</CardDescription>
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
