<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatCard from '@/components/StatCard.vue';
import { Card, CardContent } from '@/components/ui/card';
import DataSurface from '@/components/admin/DataSurface.vue';
import DeltaBadge from '@/components/admin/charts/DeltaBadge.vue';
import SparklineChart from '@/components/admin/charts/SparklineChart.vue';
import { useChartTheme } from '@/components/admin/charts/chartTheme';
import { formatMeasure } from '@/components/admin/charts/format';
import type { ReportRow, StatResultPayload } from '@/components/admin/charts/types';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';
import type { SurfaceState } from '@/composables/useAsyncSurface';

const props = withDefaults(defineProps<{
    widget: WidgetSchema;
    pageProps?: any;
    /** Server-aggregated value. Absent for legacy closure widgets. */
    result?: StatResultPayload | null;
    loading?: boolean;
    /** Overrides `loading` when given. Absent keeps the v2.4.0 two-state path. */
    state?: SurfaceState;
}>(), { result: null, loading: false });

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
    (e: 'retry'): void;
}>();

const { t } = useI18n();
const theme = useChartTheme();

const isBound = computed(() => props.result !== null && props.result !== undefined);

// --- legacy closure path (unchanged behaviour) ---
const legacyValue = computed(() => props.widget.valueFn?.(props.pageProps) ?? 0);
const legacyDescription = computed(() => props.widget.descriptionFn?.(props.pageProps));
const legacyTrend = computed(() => props.widget.trendFn?.(props.pageProps) ?? undefined);

// --- report-bound path ---
const value = computed(() => formatMeasure(
    props.result?.value ?? null,
    props.result?.format,
    props.result?.decimals ?? 0,
));

const goal = computed(() => props.widget.goalValue ?? props.result?.goal ?? null);

const goalPercent = computed(() => {
    if (!goal.value || props.result?.value == null) return null;
    return Math.round((props.result.value / goal.value) * 100);
});

const interactive = computed(() => (props.widget.segmentMode ?? 'none') !== 'none');

const surfaceState = computed<SurfaceState>(() => {
    if (props.state) return props.state;
    return props.loading ? 'loading' : 'ready';
});

const surfaceKeys = { loading: 'charts.a11y.loading' } as const;

function activate(): void {
    if (!interactive.value || !props.result) return;

    // Synthetic single-row payload — a stat card has one bucket by definition.
    emit('segment', {
        key: props.result.measure,
        label: props.widget.title ?? props.result.measure,
        values: { [props.result.measure]: props.result.value },
        previous: props.result.previous === null ? null : { [props.result.measure]: props.result.previous },
        deltas: { [props.result.measure]: props.result.delta },
        isOther: false,
        drill: props.result.drill,
    }, props.result.measure);
}
</script>

<template>
    <DataSurface
        :state="surfaceState"
        skeleton="stat"
        :label="widget.title"
        :keys="surfaceKeys"
        @retry="emit('retry')"
    >
    <!-- Report-bound: direction is the arrow, verdict is the colour. -->
    <Card v-if="isBound" class="animate-fade-in-up">
        <CardContent class="p-5">
            <component
                :is="interactive ? 'button' : 'div'"
                :type="interactive ? 'button' : undefined"
                class="w-full text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ring)]"
                @click="activate"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-muted-foreground">{{ widget.title }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums">{{ value }}</p>
                    </div>
                    <div v-if="widget.icon" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <component :is="widget.icon" class="size-5 text-muted-foreground" aria-hidden="true" />
                    </div>
                </div>

                <div class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                    <DeltaBadge
                        :delta="result?.delta"
                        :format="result?.format"
                        :decimals="result?.decimals ?? 0"
                    />
                    <span v-if="widget.descriptionFn">{{ widget.descriptionFn(pageProps) }}</span>
                    <span v-else-if="goalPercent !== null">{{ t('charts.goalProgress', { percent: goalPercent }) }}</span>
                </div>

                <div v-if="widget.sparklineFlag && result?.spark?.length" class="mt-3 h-8">
                    <SparklineChart :theme="theme" :points="result.spark" :interactive="false" />
                </div>
            </component>
        </CardContent>
    </Card>

    <!-- Legacy closure path -->
    <StatCard
        v-else
        :title="widget.title || ''"
        :value="legacyValue"
        :icon="widget.icon"
        :description="legacyDescription"
        :trend="legacyTrend"
        :color="widget.color"
    />
    </DataSurface>
</template>
