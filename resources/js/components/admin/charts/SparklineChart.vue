<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { seriesColor, withAlpha, type ChartTheme } from './chartTheme';
import { formatMeasure } from './format';
import type { ReportResultPayload, ReportRow } from './types';

/**
 * Deliberately NOT Chart.js: a stat card's sparkline must never pull the chart
 * library into the initial dashboard chunk.
 */
const props = withDefaults(defineProps<{
    theme: ChartTheme;
    result?: ReportResultPayload | null;
    measures?: string[];
    /** Alternative source — StatResultPayload.spark, which has no rows. */
    points?: Array<number | null>;
    /** A stat card sets this false so 30 points do not become 30 tab stops. */
    interactive?: boolean;
}>(), {
    result: null,
    interactive: true,
});

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();

const W = 100;
const H = 32;

const measure = computed(() => {
    const all = props.result?.measures ?? [];
    return all.find(m => !props.measures || props.measures.includes(m.key)) ?? all[0] ?? null;
});

const rows = computed<ReportRow[]>(() => props.result?.rows ?? []);

const values = computed<Array<number | null>>(() => {
    if (props.points) return props.points;
    const key = measure.value?.key;
    return key ? rows.value.map(row => row.values[key] ?? null) : [];
});

const previousValues = computed<Array<number | null>>(() => {
    const key = measure.value?.key;
    if (!key || !props.result?.comparison) return [];
    return rows.value.map(row => row.previous?.[key] ?? null);
});

const bounds = computed(() => {
    const nums = [...values.value, ...previousValues.value].filter(
        (v): v is number => typeof v === 'number' && Number.isFinite(v),
    );
    if (nums.length === 0) return { min: 0, max: 1 };

    const min = Math.min(0, ...nums);
    const max = Math.max(...nums);
    return { min, max: max === min ? min + 1 : max };
});

function x(i: number, total: number): number {
    return total <= 1 ? W / 2 : (i / (total - 1)) * W;
}

function y(value: number): number {
    const { min, max } = bounds.value;
    return H - ((value - min) / (max - min)) * (H - 4) - 2;
}

function toPolyline(series: Array<number | null>): string {
    return series
        .map((v, i) => (v === null ? null : `${x(i, series.length).toFixed(2)},${y(v).toFixed(2)}`))
        .filter((p): p is string => p !== null)
        .join(' ');
}

const line = computed(() => toPolyline(values.value));
const previousLine = computed(() => toPolyline(previousValues.value));

const area = computed(() => {
    if (!line.value) return '';
    return `${x(0, values.value.length).toFixed(2)},${H} ${line.value} ${x(values.value.length - 1, values.value.length).toFixed(2)},${H}`;
});

const color = computed(() => seriesColor(props.theme, 0));
const fill = computed(() => withAlpha(color.value, 0.16));

const summary = computed(() => t('charts.a11y.summary', {
    chart: t('charts.type.sparkline'),
    rows: values.value.length,
    measure: measure.value ? t(measure.value.labelKey) : t('charts.empty.noData'),
    total: formatMeasure(
        values.value.reduce<number>((sum, v) => sum + (v ?? 0), 0),
        measure.value?.format,
        measure.value?.decimals ?? 0,
    ),
}));

function segmentLabel(i: number): string {
    const row = rows.value[i];
    return t('charts.a11y.segment', {
        label: row?.label ?? String(i + 1),
        measure: measure.value ? t(measure.value.labelKey) : '',
        value: formatMeasure(values.value[i], measure.value?.format, measure.value?.decimals ?? 0),
    });
}

function activate(i: number): void {
    const row = rows.value[i];
    if (!row || !measure.value) return;
    emit('segment', row, measure.value.key);
}

const canInteract = computed(() => props.interactive && rows.value.length > 0);
</script>

<template>
    <svg
        v-if="values.length > 0"
        class="myra-spark size-full overflow-visible"
        :viewBox="`0 0 ${W} ${H}`"
        preserveAspectRatio="none"
        role="img"
        :aria-label="summary"
    >
        <title>{{ summary }}</title>

        <polygon v-if="area" :points="area" :fill="fill" stroke="none" />
        <polyline
            v-if="previousLine"
            :points="previousLine"
            fill="none"
            :stroke="theme.comparison"
            stroke-width="1.25"
            stroke-dasharray="3 2"
            vector-effect="non-scaling-stroke"
        />
        <polyline
            v-if="line"
            :points="line"
            fill="none"
            :stroke="color"
            stroke-width="1.75"
            stroke-linejoin="round"
            stroke-linecap="round"
            vector-effect="non-scaling-stroke"
        />

        <g v-if="canInteract">
            <g
                v-for="(value, i) in values"
                :key="i"
                class="myra-segment"
                role="button"
                tabindex="0"
                :aria-label="segmentLabel(i)"
                @click="activate(i)"
                @keydown.enter.prevent="activate(i)"
                @keydown.space.prevent="activate(i)"
            >
                <rect
                    :x="values.length > 1 ? (i / values.length) * W : 0"
                    y="0"
                    :width="W / values.length"
                    :height="H"
                    fill="transparent"
                />
                <circle
                    v-if="value !== null"
                    :cx="x(i, values.length)"
                    :cy="y(value)"
                    r="1.5"
                    :fill="color"
                    class="myra-point"
                />
            </g>
        </g>
    </svg>

    <p v-else class="text-xs text-muted-foreground">{{ t('charts.empty.noData') }}</p>
</template>

<style scoped>
.myra-segment:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}

.myra-point {
    opacity: 0;
    transition: opacity 150ms ease;
}

.myra-segment:hover .myra-point,
.myra-segment:focus-visible .myra-point {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .myra-point {
        transition: none;
    }
}
</style>
