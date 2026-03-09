<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    TextColumn, BadgeColumn, ToggleColumn, SelectColumn, TextInputColumn,
} from '@/composables/useTableSchema';
import { ArrowLeft } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    products: PaginatedData<any>;
    filters: Record<string, string>;
}>();

// Local mutable copy — edits stay in memory (demo has no real database)
function cloneProducts(p: PaginatedData<any>): PaginatedData<any> {
    return { ...p, data: p.data.map(row => ({ ...row })) };
}
const localProducts = ref(cloneProducts(props.products));

// Re-clone when server data changes (pagination, search, sort)
watch(() => props.products, (v) => { localProducts.value = cloneProducts(v); });

function handleInlineUpdate(row: any, field: string, value: any) {
    row[field] = value;
    toast.success(`Updated product #${row.id}: ${field} → ${value}`);
}

const columns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('name').label('Product Name').sortable().grow(),
    SelectColumn.make('category')
        .label('Category')
        .options({
            Electronics: 'Electronics',
            Clothing: 'Clothing',
            Books: 'Books',
            'Home & Garden': 'Home & Garden',
        })
        .onUpdate((row, value) => handleInlineUpdate(row, 'category', value)),
    TextInputColumn.make('price')
        .label('Price')
        .placeholder('0.00')
        .debounce(600)
        .onUpdate((row, value) => handleInlineUpdate(row, 'price', value)),
    TextInputColumn.make('stock')
        .label('Stock')
        .placeholder('0')
        .onUpdate((row, value) => handleInlineUpdate(row, 'stock', value)),
    ToggleColumn.make('is_active')
        .label('Active')
        .onUpdate((row, value) => handleInlineUpdate(row, 'is_active', value)),
    BadgeColumn.make('status')
        .label('Status')
        .colors({ active: 'default', draft: 'secondary', archived: 'outline' }),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Inline Editing' },
    ]">
        <Head title="Inline Editing Demo" />

        <PageHeader title="Inline Table Editing" description="Edit cell values directly in the table without opening a form.">
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
                    <CardTitle>Products</CardTitle>
                    <CardDescription>Click on Category dropdowns, Price/Stock inputs, or Active toggles to edit inline.</CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="columns"
                        :data="localProducts"
                        :filters="filters"
                        route-name="admin.demo.inline-editing"
                        search-placeholder="Search products..."
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
