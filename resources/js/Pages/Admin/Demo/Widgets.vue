<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatWidget, ChartWidget, TableWidget } from '@/composables/useDashboardWidgets';
import {
    ArrowLeft, Users, DollarSign, ShoppingCart, TrendingUp,
} from 'lucide-vue-next';

const props = defineProps<{
    data: {
        totalUsers: number;
        revenue: number;
        orders: number;
        conversionRate: number;
        salesByMonth: Array<{ month: string; sales: number; revenue: number }>;
        topProducts: Array<{ name: string; sales: number; revenue: string }>;
        recentOrders: Array<{ id: string; customer: string; total: string; status: string }>;
    };
}>();

const statWidgets = [
    StatWidget.make('total_users').title('Total Users')
        .value(p => p.data.totalUsers)
        .icon(Users)
        .trend(() => ({ value: 12, isPositive: true }))
        .description(() => 'from last month'),
    StatWidget.make('revenue').title('Revenue')
        .value(p => `$${p.data.revenue.toLocaleString()}`)
        .icon(DollarSign)
        .trend(() => ({ value: 8, isPositive: true }))
        .description(() => 'from last month'),
    StatWidget.make('orders').title('Orders')
        .value(p => p.data.orders)
        .icon(ShoppingCart)
        .trend(() => ({ value: -3, isPositive: false }))
        .description(() => 'from last month'),
    StatWidget.make('conversion').title('Conversion Rate')
        .value(p => `${p.data.conversionRate}%`)
        .icon(TrendingUp)
        .description(() => 'of visitors'),
];

const chartWidgets = [
    ChartWidget.make('sales_chart').title('Monthly Sales')
        .type('bar').colSpan(2)
        .data(p => ({
            labels: p.data.salesByMonth.map((m: any) => m.month),
            datasets: [{
                label: 'Sales',
                data: p.data.salesByMonth.map((m: any) => m.sales),
                backgroundColor: 'oklch(0.646 0.222 41.116)',
                borderRadius: 6,
                borderSkipped: false,
            }],
        })),
    ChartWidget.make('revenue_chart').title('Revenue Trend')
        .type('line').colSpan(2).collapsible()
        .data(p => ({
            labels: p.data.salesByMonth.map((m: any) => m.month),
            datasets: [{
                label: 'Revenue',
                data: p.data.salesByMonth.map((m: any) => m.revenue),
                borderColor: 'oklch(0.6 0.2 250)',
                backgroundColor: 'oklch(0.6 0.2 250 / 0.1)',
                fill: true,
                tension: 0.3,
            }],
        })),
];

const tableWidgets = [
    TableWidget.make('top_products').title('Top Products').colSpan(2)
        .columns(() => [
            { key: 'name', label: 'Product' },
            { key: 'sales', label: 'Sales', class: 'text-right' },
            { key: 'revenue', label: 'Revenue', class: 'text-right' },
        ])
        .data(p => p.data.topProducts),
    TableWidget.make('recent_orders').title('Recent Orders').colSpan(2)
        .columns(() => [
            { key: 'id', label: 'Order' },
            { key: 'customer', label: 'Customer' },
            { key: 'total', label: 'Total', class: 'text-right' },
            { key: 'status', label: 'Status' },
        ])
        .data(p => p.data.recentOrders)
        .footerLink(() => ({ label: 'View all orders', href: '#' })),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Dashboard Widgets' },
    ]">
        <Head title="Dashboard Widgets Demo" />

        <PageHeader title="Dashboard Widget System" description="Configurable widget builders for stat cards, charts, and tables.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Stat Widgets</CardTitle>
                    <CardDescription>StatWidget.make() with value, icon, trend, and description builders.</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="statWidgets" :page-props="props" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Chart Widgets</CardTitle>
                    <CardDescription>ChartWidget.make() with bar, line, pie, and doughnut types. Revenue chart is collapsible.</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="chartWidgets" :page-props="props" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Table Widgets</CardTitle>
                    <CardDescription>TableWidget.make() with columns, data, and footer link builders.</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="tableWidgets" :page-props="props" />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
