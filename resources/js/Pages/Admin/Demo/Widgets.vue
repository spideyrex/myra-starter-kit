<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import DeltaBadge from '@/components/admin/charts/DeltaBadge.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatWidget, ChartWidget, TableWidget } from '@/composables/useDashboardWidgets';
import { chartTypesFor, type ChartType } from '@/components/admin/charts/ChartRegistry';
import type {
    Delta, MeasureSchema, ReportResultPayload, ReportState, StatResultPayload,
} from '@/components/admin/charts/types';
import { ArrowLeft, Users, DollarSign, ShoppingCart, TrendingUp } from 'lucide-vue-next';

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

const { t } = useI18n();

// --- Mock report payloads ----------------------------------------------------
// Bundle B's ReportController is not in this worktree. These literals are the
// frozen §2.11 wire shape, so swapping in the real server payload is a no-op.

const salesMeasure: MeasureSchema = {
    key: 'sales', labelKey: 'charts.demo.measure.sales', format: 'number',
    decimals: 0, goal: 500, invertTrend: false, additive: true,
};

const revenueMeasure: MeasureSchema = {
    key: 'revenue', labelKey: 'charts.demo.measure.revenue', format: 'currency',
    decimals: 0, goal: null, invertTrend: false, additive: true,
};

const churnMeasure: MeasureSchema = {
    key: 'churn', labelKey: 'charts.demo.measure.churn', format: 'percent',
    decimals: 1, goal: null, invertTrend: true, additive: false,
};

function state(dimension: string, measures: string[]): ReportState {
    return { period: { preset: 'last_90_days' }, compare: 'previous', dimension, measures };
}

function delta(absolute: number, percent: number | null, good: boolean): Delta {
    return {
        absolute,
        percent,
        direction: absolute > 0 ? 'up' : absolute < 0 ? 'down' : 'flat',
        good,
    };
}

/** Date dimension, two measures, comparison on. */
const monthlyResult = computed<ReportResultPayload>(() => {
    const rows = props.data.salesByMonth.map((m, i) => {
        const previousSales = Math.round(m.sales * 0.86);
        return {
            key: m.month,
            label: m.month,
            values: { sales: m.sales, revenue: m.revenue },
            previous: { sales: previousSales, revenue: Math.round(m.revenue * 0.9) },
            deltas: {
                sales: delta(m.sales - previousSales, previousSales === 0 ? null : 14 + i, true),
                revenue: delta(Math.round(m.revenue * 0.1), 10, true),
            },
            isOther: false,
            drill: null,
        };
    });

    const total = (key: 'sales' | 'revenue') => rows.reduce((sum, r) => sum + (r.values[key] ?? 0), 0);

    return {
        report: 'demo_sales',
        state: state('month', ['sales', 'revenue']),
        period: { preset: 'last_90_days', from: '2026-05-18', to: '2026-08-16', tz: 'UTC', bucket: 'month' },
        comparison: { mode: 'previous', from: '2026-02-17', to: '2026-05-17' },
        dimension: {
            key: 'month', labelKey: 'charts.demo.dim.month', type: 'date',
            drillable: false, allowedBuckets: ['month', 'quarter'],
        },
        measures: [salesMeasure, revenueMeasure],
        rows,
        totals: { sales: total('sales'), revenue: total('revenue') },
        previousTotals: { sales: Math.round(total('sales') * 0.86), revenue: Math.round(total('revenue') * 0.9) },
        deltas: { sales: delta(180, 16.2, true), revenue: delta(9100, 11.4, true) },
        truncated: false,
        groupCount: rows.length,
    };
});

/** Categorical dimension, top-N truncated with a synthetic Other row. */
const productResult = computed<ReportResultPayload>(() => {
    const rows = props.data.topProducts.map(p => ({
        key: p.name,
        label: p.name,
        values: { sales: p.sales, churn: null },
        previous: null,
        deltas: { sales: null, churn: null },
        isOther: false,
        drill: null,
    }));

    const shown = rows.reduce((sum, r) => sum + (r.values.sales ?? 0), 0);

    // Additive measures get a real Other value; non-additive ones stay null.
    rows.push({
        key: '__other',
        label: t('charts.other'),
        values: { sales: Math.round(shown * 0.35), churn: null },
        previous: null,
        deltas: { sales: null, churn: null },
        isOther: true,
        drill: null,
    });

    return {
        report: 'demo_products',
        state: state('product', ['sales']),
        period: { preset: 'last_90_days', from: '2026-05-18', to: '2026-08-16', tz: 'UTC', bucket: null },
        comparison: null,
        dimension: {
            key: 'product', labelKey: 'charts.demo.dim.product', type: 'field',
            drillable: false, allowedBuckets: [],
        },
        measures: [salesMeasure, churnMeasure],
        rows,
        totals: { sales: Math.round(shown * 1.35), churn: null },
        previousTotals: null,
        deltas: { sales: null, churn: null },
        truncated: true,
        groupCount: 24,
    };
});

// --- Widgets -----------------------------------------------------------------

const statWidgets = computed(() => [
    StatWidget.make('total_users').title(t('charts.dashboard.users'))
        .value(p => p.data.totalUsers).icon(Users)
        .trend(() => ({ value: 12, isPositive: true }))
        .description(() => t('charts.compare.previous')),
    StatWidget.make('revenue').title(t('charts.demo.measure.revenue'))
        .value(p => `$${p.data.revenue.toLocaleString()}`).icon(DollarSign)
        .trend(() => ({ value: 8, isPositive: true }))
        .description(() => t('charts.compare.previous')),
    StatWidget.make('orders').title(t('charts.demo.col.order'))
        .value(p => p.data.orders).icon(ShoppingCart)
        .trend(() => ({ value: -3, isPositive: false }))
        .description(() => t('charts.compare.previous')),
    StatWidget.make('conversion').title(t('charts.demo.measure.churn'))
        .value(p => `${p.data.conversionRate}%`).icon(TrendingUp),
]);

/** Report-bound stats: value, delta and sparkline all come from the server. */
const boundStatWidgets = computed(() => [
    StatWidget.make('bound_sales').title(t('charts.demo.measure.sales'))
        .report('demo_sales').measure('sales').compare('previous')
        .sparkline().icon(ShoppingCart),
    StatWidget.make('bound_revenue').title(t('charts.demo.measure.revenue'))
        .report('demo_sales').measure('revenue').compare('previous')
        .sparkline().icon(DollarSign),
    StatWidget.make('bound_churn').title(t('charts.demo.measure.churn'))
        .report('demo_sales').measure('churn').compare('previous')
        .invertTrend().icon(TrendingUp),
]);

function statResult(
    measure: MeasureSchema,
    value: number,
    previous: number,
    spark: number[],
): StatResultPayload {
    const absolute = value - previous;
    const percent = previous === 0 ? null : Math.round((absolute / Math.abs(previous)) * 1000) / 10;
    const direction = absolute > 0 ? 'up' : absolute < 0 ? 'down' : 'flat';
    const good = direction === 'flat' ? true : (measure.invertTrend ? direction === 'down' : direction === 'up');

    return {
        report: 'demo_sales',
        measure: measure.key,
        value,
        previous,
        delta: { absolute, percent, direction, good },
        spark,
        format: measure.format,
        decimals: measure.decimals,
        goal: measure.goal,
        drill: null,
    };
}

const spark = computed(() => props.data.salesByMonth.map(m => m.sales));

const statResults = computed(() => ({
    bound_sales: statResult(salesMeasure, 477, 402, spark.value),
    bound_revenue: statResult(revenueMeasure, 92100, 82700, props.data.salesByMonth.map(m => m.revenue)),
    // invertTrend: churn going UP is bad, so the badge is red on an "up" arrow.
    bound_churn: statResult(churnMeasure, 4.8, 3.9, []),
}));

const dateTypes = computed<ChartType[]>(() => chartTypesFor('date', 2));
const fieldTypes = computed<ChartType[]>(() => chartTypesFor('field', 1));

const galleryWidgets = computed(() => [
    ...dateTypes.value.map(type => ChartWidget.make(`date_${type}`)
        .title(t(`charts.type.${type}`))
        .report('demo_sales').dimension('month').measures(['sales', 'revenue'])
        .type(type).colSpan({ sm: 2, lg: 2 }).height(240)
        .stacked(type === 'stackedBar' || type === 'stackedArea')),
    ...fieldTypes.value.map(type => ChartWidget.make(`field_${type}`)
        .title(t(`charts.type.${type}`))
        .report('demo_products').dimension('product').measures(['sales'])
        .type(type).colSpan({ sm: 2, lg: 2 }).height(240)),
]);

const galleryResults = computed(() => {
    const out: Record<string, ReportResultPayload> = {};
    for (const type of dateTypes.value) out[`date_${type}`] = monthlyResult.value;
    for (const type of fieldTypes.value) out[`field_${type}`] = productResult.value;
    return out;
});

const comparisonWidgets = computed(() => [
    ChartWidget.make('compare_line').title(t('charts.compare.previous'))
        .report('demo_sales').dimension('month').measures(['sales'])
        .type('line').compare('previous').colSpan({ sm: 2, lg: 4 }).height(280).legend(),
]);

const comparisonResults = computed(() => ({ compare_line: monthlyResult.value }));

const closureWidgets = computed(() => [
    ChartWidget.make('sales_chart').title(t('charts.demo.measure.sales'))
        .type('bar').colSpan({ sm: 2, lg: 2 })
        .data(p => ({
            labels: p.data.salesByMonth.map((m: any) => m.month),
            datasets: [{ label: t('charts.demo.measure.sales'), data: p.data.salesByMonth.map((m: any) => m.sales), borderRadius: 6 }],
        })),
    ChartWidget.make('revenue_chart').title(t('charts.demo.measure.revenue'))
        .type('line').colSpan({ sm: 2, lg: 2 }).collapsible()
        .data(p => ({
            labels: p.data.salesByMonth.map((m: any) => m.month),
            datasets: [{ label: t('charts.demo.measure.revenue'), data: p.data.salesByMonth.map((m: any) => m.revenue), fill: true, tension: 0.3 }],
        })),
]);

const tableWidgets = computed(() => [
    TableWidget.make('top_products').title(t('charts.demo.tableTitle')).colSpan({ sm: 2, lg: 2 })
        .columns(() => [
            { key: 'name', label: t('charts.demo.col.product') },
            { key: 'sales', label: t('charts.demo.col.sales'), class: 'text-right' },
            { key: 'revenue', label: t('charts.demo.col.revenue'), class: 'text-right' },
        ])
        .data(p => p.data.topProducts),
    TableWidget.make('recent_orders').title(t('charts.demo.col.order')).colSpan({ sm: 2, lg: 2 })
        .columns(() => [
            { key: 'id', label: t('charts.demo.col.order') },
            { key: 'customer', label: t('charts.demo.col.customer') },
            { key: 'total', label: t('charts.demo.col.total'), class: 'text-right' },
            { key: 'status', label: t('charts.demo.col.status') },
        ])
        .data(p => p.data.recentOrders),
    TableWidget.make('bound_products').title(t('charts.demo.reportTitle')).colSpan({ sm: 2, lg: 4 })
        .report('demo_products').dimension('product').measures(['sales']),
]);

const tableResults = computed(() => ({ bound_products: productResult.value }));

/** Colour follows `good`, never `direction` — an inverted trend proves it. */
const deltaSamples = computed<Array<{ key: string; label: string; delta: Delta | null }>>(() => [
    { key: 'goodUp', label: t('charts.demo.sample.goodUp'), delta: { absolute: 141, percent: 12.3, direction: 'up', good: true } },
    { key: 'badUp', label: t('charts.demo.sample.badUp'), delta: { absolute: 0.9, percent: 23.1, direction: 'up', good: false } },
    { key: 'goodDown', label: t('charts.demo.sample.goodDown'), delta: { absolute: -0.6, percent: -12.5, direction: 'down', good: true } },
    { key: 'flat', label: t('charts.demo.sample.flat'), delta: { absolute: 0, percent: 0, direction: 'flat', good: true } },
    { key: 'noPrevious', label: t('charts.demo.sample.noPrevious'), delta: { absolute: 5, percent: null, direction: 'up', good: true } },
    { key: 'none', label: t('charts.demo.sample.none'), delta: null },
]);
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: t('nav.featureDemos'), href: route('admin.demo.index') },
        { label: t('charts.demo.title') },
    ]">
        <Head :title="t('charts.demo.title')" />

        <PageHeader :title="t('charts.demo.title')" :description="t('charts.demo.description')">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        {{ t('charts.demo.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.statTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.statDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="statWidgets" :page-props="props" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.reportTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.reportDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="boundStatWidgets" :page-props="props" :results="statResults" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.compareTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.compareDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DashboardGrid :widgets="comparisonWidgets" :page-props="props" :results="comparisonResults" />

                    <ul class="flex flex-wrap gap-4">
                        <li v-for="sample in deltaSamples" :key="sample.key" class="flex items-center gap-2">
                            <DeltaBadge :delta="sample.delta" />
                            <span class="text-xs text-muted-foreground">{{ sample.label }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.typesTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.typesDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="galleryWidgets" :page-props="props" :results="galleryResults" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.closureTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.closureDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="closureWidgets" :page-props="props" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('charts.demo.tableTitle') }}</CardTitle>
                    <CardDescription>{{ t('charts.demo.tableDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <DashboardGrid :widgets="tableWidgets" :page-props="props" :results="tableResults" />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
