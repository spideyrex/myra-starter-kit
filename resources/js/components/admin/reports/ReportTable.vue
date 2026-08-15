<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowDown, ArrowUp, Minus } from 'lucide-vue-next';
import type { Delta, MeasureSchema, ReportResultPayload } from '@/types/reports';

/**
 * The tabular rendering of a result. It is also the accessible fallback the
 * chart bundle's "view as table" toggle resolves to, so it stays the canonical
 * presentation of `rows` even once charts land.
 */
const props = defineProps<{ result: ReportResultPayload }>();

const { t, locale } = useI18n();

const comparing = computed(() => props.result.comparison !== null);

function formatValue(value: number | null | undefined, measure: MeasureSchema): string {
    if (value === null || value === undefined) return t('reports.notApplicable');

    const options: Intl.NumberFormatOptions = {
        minimumFractionDigits: measure.decimals,
        maximumFractionDigits: measure.decimals,
    };

    if (measure.format === 'percent') options.style = 'percent';

    return new Intl.NumberFormat(locale.value, options).format(
        measure.format === 'percent' ? value / 100 : value,
    );
}

function deltaLabel(delta: Delta | null | undefined): string {
    if (!delta) return t('reports.notApplicable');
    if (delta.percent === null) return t('reports.notApplicable');

    return `${delta.percent > 0 ? '+' : ''}${delta.percent}%`;
}

/** Colour follows delta.good, not delta.direction — that is what makes invertTrend visible. */
function deltaClass(delta: Delta | null | undefined): string {
    if (!delta || delta.direction === 'flat') return 'text-muted-foreground';
    return delta.good ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive';
}

function deltaIcon(delta: Delta | null | undefined) {
    if (!delta || delta.direction === 'flat') return Minus;
    return delta.direction === 'up' ? ArrowUp : ArrowDown;
}

function rowLabel(row: ReportResultPayload['rows'][number]): string {
    return row.isOther ? t('reports.other') : row.label;
}
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead scope="col">{{ t(result.dimension.labelKey) }}</TableHead>
                    <template v-for="m in result.measures" :key="m.key">
                        <TableHead scope="col" class="text-end">{{ t(m.labelKey) }}</TableHead>
                        <TableHead v-if="comparing" scope="col" class="text-end">
                            {{ t('reports.export.previous') }}
                        </TableHead>
                        <TableHead v-if="comparing" scope="col" class="text-end">
                            {{ t('reports.export.change') }}
                        </TableHead>
                    </template>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-if="result.rows.length === 0">
                    <TableCell :colspan="1 + result.measures.length * (comparing ? 3 : 1)" class="text-center text-muted-foreground">
                        {{ t('reports.noRows') }}
                    </TableCell>
                </TableRow>

                <TableRow v-for="row in result.rows" :key="row.key" :class="row.isOther ? 'text-muted-foreground italic' : ''">
                    <TableCell class="font-medium">{{ rowLabel(row) }}</TableCell>
                    <template v-for="m in result.measures" :key="m.key">
                        <TableCell class="text-end tabular-nums">{{ formatValue(row.values[m.key], m) }}</TableCell>
                        <TableCell v-if="comparing" class="text-end tabular-nums">
                            {{ formatValue(row.previous?.[m.key] ?? null, m) }}
                        </TableCell>
                        <TableCell v-if="comparing" class="text-end tabular-nums">
                            <span class="inline-flex items-center gap-1" :class="deltaClass(row.deltas[m.key])">
                                <component :is="deltaIcon(row.deltas[m.key])" class="size-3" aria-hidden="true" />
                                {{ deltaLabel(row.deltas[m.key]) }}
                            </span>
                        </TableCell>
                    </template>
                </TableRow>
            </TableBody>

            <TableFooter>
                <TableRow>
                    <TableCell class="font-semibold">{{ t('reports.total') }}</TableCell>
                    <template v-for="m in result.measures" :key="m.key">
                        <TableCell class="text-end font-semibold tabular-nums">
                            {{ formatValue(result.totals[m.key], m) }}
                        </TableCell>
                        <TableCell v-if="comparing" class="text-end tabular-nums">
                            {{ formatValue(result.previousTotals?.[m.key] ?? null, m) }}
                        </TableCell>
                        <TableCell v-if="comparing" class="text-end tabular-nums">
                            <span class="inline-flex items-center gap-1" :class="deltaClass(result.deltas[m.key])">
                                <component :is="deltaIcon(result.deltas[m.key])" class="size-3" aria-hidden="true" />
                                {{ deltaLabel(result.deltas[m.key]) }}
                            </span>
                        </TableCell>
                    </template>
                </TableRow>
            </TableFooter>
        </Table>
    </div>
</template>
