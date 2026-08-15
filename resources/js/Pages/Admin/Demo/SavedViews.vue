<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { SelectFilter } from '@/composables/useTableFilters';
import { TableView } from '@/composables/useTableViews';
import { ArrowLeft, Bookmark, Star, Users } from 'lucide-vue-next';
import type { PaginatedData } from '@/types';
import type { SavedView } from '@/types/table-views';

defineProps<{
    products: PaginatedData<any>;
    filters: Record<string, string>;
    savedViews: SavedView[];
    canShareViews: boolean;
}>();

const columns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('name').label('Product').sortable().searchable(),
    TextColumn.make('category').label('Category').sortable(),
    TextColumn.make('price').label('Price').money().alignEnd().sortable(),
    TextColumn.make('stock').label('Stock').alignEnd().sortable(),
    BadgeColumn.make('status').label('Status').colors({
        active: 'default',
        draft: 'secondary',
        archived: 'outline',
    }),
    DateColumn.make('created_at').label('Created').format('date').sortable(),
];

const tableFilters = [
    SelectFilter.make('category').label('Category').options({
        Electronics: 'Electronics',
        Clothing: 'Clothing',
        Books: 'Books',
        'Home & Garden': 'Home & Garden',
    }),
    SelectFilter.make('status').label('Status').options({
        active: 'Active',
        draft: 'Draft',
        archived: 'Archived',
    }),
];

// Page-declared views are the same object the server stores, so a declared view
// and a user-saved view share one menu and one apply path.
const views = [
    TableView.make('All products').icon(Bookmark),
    TableView.make('Newest first').icon(Star).sort('created_at', 'desc').default(),
    TableView.make('Active electronics')
        .icon(Star)
        .filters({ category: 'Electronics', status: 'active' })
        .sort('price', 'desc'),
    TableView.make('Archived, price only')
        .icon(Users)
        .filters({ status: 'archived' })
        .columns({ stock: false, created_at: false })
        .columnOrder(['id', 'name', 'price', 'category', 'status', 'stock', 'created_at']),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Saved Views' },
    ]">
        <Head title="Saved Views Demo" />

        <PageHeader
            title="Saved Views & Column Manager"
            description="Named filter, sort and column presets — per user, shareable with a team."
        >
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
                        Three page-declared views (one marked <code>default()</code>) sit alongside anything you save.
                        Change a filter and the trigger shows a “modified” dot; save it as a private view, or share it
                        with your current team. The Columns button reorders with drag or <code>Alt</code>+arrow keys and
                        persists to <code>localStorage</code>. “Copy link” yields the full parameter URL — it is
                        shareable with anyone who can already open this table.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="columns"
                        :data="products"
                        :filters="filters"
                        :table-filters="tableFilters"
                        route-name="admin.demo.saved-views"
                        table-key="admin.demo.saved-views"
                        :views="views"
                        :saved-views="savedViews"
                        :can-share-views="canShareViews"
                        search-placeholder="Search products..."
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
