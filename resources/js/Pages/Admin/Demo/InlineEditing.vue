<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import SimpleTable from '@/components/admin/SimpleTable.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    TextColumn, BadgeColumn, ToggleColumn, SelectColumn, TextInputColumn,
    CheckboxColumn, ColorColumn,
} from '@/composables/useTableSchema';
import { ArrowLeft } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    products: PaginatedData<any>;
    filters: Record<string, string>;
}>();

// Local mutable copy — the framework writes optimistically into these rows.
function cloneProducts(p: PaginatedData<any>): PaginatedData<any> {
    return { ...p, data: p.data.map(row => ({ ...row })) };
}
const localProducts = ref(cloneProducts(props.products));

// Re-clone when server data changes (pagination, search, sort, inline write)
watch(() => props.products, (v) => { localProducts.value = cloneProducts(v); });

const INLINE_ROUTE = 'admin.demo.inline-update';

const columns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('name').label('Product Name').sortable().grow(),
    ColorColumn.make('brand_color')
        .label('Brand')
        .copyable()
        .copyMessage('Brand colour copied'),
    CheckboxColumn.make('is_featured')
        .label('Featured')
        .updateRoute(INLINE_ROUTE)
        .rowLabel(row => `Feature ${row.name}`)
        .disabledWhen(row => row.status === 'archived')
        .indeterminateWhen(row => row.stock === 0 && !row.is_featured)
        .confirmWhen((row, value) => (value && row.stock === 0
            ? 'This product is out of stock. Feature it anyway?'
            : false)),
    SelectColumn.make('category')
        .label('Category')
        .options({
            Electronics: 'Electronics',
            Clothing: 'Clothing',
            Books: 'Books',
            'Home & Garden': 'Home & Garden',
        })
        .updateRoute(INLINE_ROUTE),
    TextInputColumn.make('price')
        .label('Price')
        .placeholder('0.00')
        .debounce(600)
        .updateRoute(INLINE_ROUTE)
        .alignEnd()
        .summary({ type: 'max', label: 'Highest', currency: 'USD' }),
    // Escape hatch: the page owns the request instead of the table.
    TextInputColumn.make('stock')
        .label('Stock')
        .placeholder('0')
        .alignEnd()
        .onUpdate((row, value) => {
            row.stock = value;
            toast.success(`Escape hatch: product #${row.id} stock → ${value}`);
        })
        .summary({ type: 'min', label: 'Lowest', decimals: 0 }),
    ToggleColumn.make('is_active')
        .label('Active')
        .updateRoute(INLINE_ROUTE)
        .rowLabel(row => `Activate ${row.name}`),
    BadgeColumn.make('status')
        .label('Status')
        .colors({ active: 'default', draft: 'secondary', archived: 'outline' }),
];

// Second table: the swatch-only / circular variants, rendered by SimpleTable.
const paletteColumns = [
    TextColumn.make('token').label('Token'),
    ColorColumn.make('value').label('Colour').swatchSize(20).copyable(),
    ColorColumn.make('swatch').label('Swatch only').swatchOnly().circular().swatchSize(24),
];

const palette = [
    { id: 1, token: 'primary', value: '#2563eb', swatch: '#2563eb' },
    { id: 2, token: 'success', value: '#16a34a', swatch: '#16a34a' },
    { id: 3, token: 'overlay', value: 'rgba(220, 38, 38, 0.4)', swatch: 'rgba(220, 38, 38, 0.4)' },
    { id: 4, token: 'warning-soft', value: '#f59e0bcc', swatch: '#f59e0bcc' },
    { id: 5, token: 'broken', value: 'red; background-image:url(x)', swatch: 'red; background-image:url(x)' },
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
                    <CardDescription>
                        Featured, Category, Price and Active use <code>.updateRoute()</code> — the table owns the request,
                        paints optimistically and rolls back on error. Stock keeps the <code>.onUpdate()</code> escape hatch.
                        Out-of-stock rows ask for confirmation before being featured; archived rows are disabled.
                    </CardDescription>
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

            <div>
                <h2 class="text-base font-semibold">Colour column variants</h2>
                <p class="text-sm text-muted-foreground">
                    Swatch + copyable value, swatch-only circular (the value becomes the accessible name), an alpha
                    colour on a checkerboard, and an unsafe value that falls back to plain text.
                </p>
                <SimpleTable :columns="paletteColumns" :items="palette" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
