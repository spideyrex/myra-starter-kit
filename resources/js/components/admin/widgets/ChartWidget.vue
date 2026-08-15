<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Activity, ChevronDown, List } from 'lucide-vue-next';
import { resolveChartComponent, type ChartType } from '@/components/admin/charts/ChartRegistry';
import { useChartTheme } from '@/components/admin/charts/chartTheme';
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
const theme = useChartTheme();

const chartType = computed<ChartType>(() => props.widget.chartType ?? 'bar');
const renderer = computed(() => resolveChartComponent(chartType.value));

const legacyData = computed(() => props.widget.dataFn?.(props.pageProps) ?? null);
const rows = computed<ReportRow[]>(() => props.result?.rows ?? []);

const isEmpty = computed(() => {
    if (props.result) return rows.value.length === 0;
    const data = legacyData.value;
    return !data || (data.labels?.length ?? 0) === 0;
});

const emptyHeading = computed(() => t(props.widget.emptyStateKeys?.headingKey ?? 'charts.empty.heading'));
const emptyDescription = computed(() => t(props.widget.emptyStateKeys?.descriptionKey ?? 'charts.empty.description'));

const asTable = ref(false);
const measures = computed(() => (props.result?.measures ?? []).filter(
    m => !props.widget.measureKeys || props.widget.measureKeys.includes(m.key),
));

const truncationNotice = computed(() => props.result?.truncated
    ? t('charts.truncated', { shown: rows.value.length, total: props.result.groupCount })
    : null);

const height = computed(() => props.widget.height ?? 260);

// Collapsible state from localStorage (behaviour preserved).
const storageKey = `widget-collapsed-${props.widget.key}`;
const isOpen = ref(true);

onMounted(() => {
    if (props.widget.collapsible) {
        const saved = localStorage.getItem(storageKey);
        if (saved !== null) isOpen.value = saved !== 'true';
    }
});

function toggleCollapse(): void {
    isOpen.value = !isOpen.value;
    localStorage.setItem(storageKey, String(!isOpen.value));
}

function onSegment(row: ReportRow, measureKey: string): void {
    emit('segment', row, measureKey);
}

function cell(row: ReportRow, key: string, format?: any, decimals = 0): string {
    return formatMeasure(row.values[key] ?? null, format, decimals);
}
</script>

<template>
    <Card class="animate-fade-in-up">
        <CardHeader :class="widget.collapsible ? 'cursor-pointer' : undefined" @click="widget.collapsible && toggleCollapse()">
            <div class="flex items-center justify-between gap-2">
                <CardTitle>{{ widget.title }}</CardTitle>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="result"
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :aria-pressed="asTable"
                        :aria-label="asTable ? t('charts.viewAsChart') : t('charts.viewAsTable')"
                        @click.stop="asTable = !asTable"
                    >
                        <component :is="asTable ? Activity : List" class="size-4" />
                    </Button>
                    <ChevronDown
                        v-if="widget.collapsible"
                        class="size-4 text-muted-foreground transition-transform"
                        :class="{ 'rotate-180': isOpen }"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </CardHeader>

        <!-- Only a collapsible widget pays for the disclosure primitive. -->
        <component :is="widget.collapsible ? Collapsible : 'div'" :open="widget.collapsible ? isOpen : undefined">
            <component :is="widget.collapsible ? CollapsibleContent : 'div'">
                <CardContent>
                    <!-- Loading -->
                    <div v-if="loading" :style="{ height: `${height}px` }" aria-busy="true">
                        <span class="sr-only" role="status">{{ t('charts.a11y.loading') }}</span>
                        <Skeleton class="size-full rounded-lg" />
                    </div>

                    <!-- Empty -->
                    <div
                        v-else-if="isEmpty"
                        class="flex flex-col items-center justify-center gap-2 text-center"
                        :style="{ height: `${height}px` }"
                    >
                        <Activity class="size-8 text-muted-foreground/60" aria-hidden="true" />
                        <p class="text-sm font-medium">{{ emptyHeading }}</p>
                        <p class="text-xs text-muted-foreground">{{ emptyDescription }}</p>
                    </div>

                    <!-- Data as a table -->
                    <div v-else-if="asTable && result" class="overflow-x-auto" :style="{ maxHeight: `${height}px` }">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col">{{ t(result.dimension.labelKey) }}</TableHead>
                                    <TableHead v-for="m in measures" :key="m.key" scope="col" class="text-right">
                                        {{ t(m.labelKey) }}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in rows" :key="row.key">
                                    <TableCell>{{ row.isOther ? t('charts.other') : row.label }}</TableCell>
                                    <TableCell v-for="m in measures" :key="m.key" class="text-right tabular-nums">
                                        {{ cell(row, m.key, m.format, m.decimals) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Chart -->
                    <div v-else :style="{ height: `${height}px` }">
                        <component
                            :is="renderer"
                            :type="chartType"
                            :theme="theme"
                            :result="result"
                            :data="legacyData"
                            :measures="widget.measureKeys"
                            :stacked="widget.stacked || undefined"
                            :legend="widget.legend ?? undefined"
                            :goal="widget.goalValue ?? null"
                            @segment="onSegment"
                        />
                    </div>

                    <p v-if="truncationNotice" class="mt-2 text-xs text-muted-foreground">
                        {{ truncationNotice }}
                    </p>
                </CardContent>
            </component>
        </component>
    </Card>
</template>
