<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { ArrowRight } from 'lucide-vue-next';
import { formatMeasure } from '@/components/admin/charts/format';
import type { ReportResultPayload, ReportRow } from '@/components/admin/charts/types';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

const props = withDefaults(defineProps<{
    widget: WidgetSchema;
    pageProps?: any;
    /** Server-aggregated buckets. Absent for legacy `.data(fn)` widgets. */
    result?: ReportResultPayload | null;
    loading?: boolean;
}>(), { result: null, loading: false });

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();

const measures = computed(() => (props.result?.measures ?? []).filter(
    m => !props.widget.measureKeys || props.widget.measureKeys.includes(m.key),
));

const reportColumns = computed(() => [
    { key: '__dimension', label: props.result ? t(props.result.dimension.labelKey) : '', class: '' },
    ...measures.value.map(m => ({ key: m.key, label: t(m.labelKey), class: 'text-right tabular-nums' })),
]);

const columns = computed(() => props.result
    ? reportColumns.value
    : props.widget.columnsFn?.(props.pageProps) ?? []);

const rows = computed<any[]>(() => props.result
    ? props.result.rows
    : props.widget.rowsFn?.(props.pageProps) ?? []);

const footerLink = computed(() => props.widget.footerLinkFn?.(props.pageProps) ?? null);

function reportCell(row: ReportRow, key: string): string {
    if (key === '__dimension') return row.isOther ? t('charts.other') : row.label;

    const measure = measures.value.find(m => m.key === key);
    return formatMeasure(row.values[key] ?? null, measure?.format, measure?.decimals ?? 0);
}

function activate(row: ReportRow): void {
    if (!props.result || (props.widget.segmentMode ?? 'none') === 'none') return;
    emit('segment', row, measures.value[0]?.key ?? '');
}
</script>

<template>
    <Card class="animate-fade-in-up">
        <CardHeader>
            <CardTitle>{{ widget.title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-2" aria-busy="true">
                <span class="sr-only" role="status">{{ t('charts.a11y.loading') }}</span>
                <Skeleton v-for="i in 4" :key="i" class="h-8 w-full" />
            </div>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead v-for="col in columns" :key="col.key" scope="col" :class="col.class">
                            {{ col.label }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(row, idx) in rows"
                        :key="result ? row.key : idx"
                        @click="result && activate(row)"
                    >
                        <TableCell v-for="col in columns" :key="col.key" :class="col.class">
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ result ? reportCell(row, col.key) : row[col.key] }}
                            </slot>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="rows.length === 0">
                        <TableCell :colspan="Math.max(columns.length, 1)" class="py-8 text-center text-muted-foreground">
                            {{ t('charts.empty.noData') }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="footerLink" class="mt-3 text-center">
                <Link :href="footerLink.href" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                    {{ footerLink.label }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
