<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { seriesColor, withAlpha, type ChartTheme } from './chartTheme';
import { formatMeasure } from './format';
import type { MeasureSchema, ReportResultPayload, ReportRow } from './types';

const props = withDefaults(defineProps<{
    theme: ChartTheme;
    result?: ReportResultPayload | null;
    measures?: string[];
}>(), { result: null });

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();

const CELL = 22;
const GAP = 2;
const LABEL_W = 62;

const activeMeasures = computed<MeasureSchema[]>(() =>
    (props.result?.measures ?? []).filter(m => !props.measures || props.measures.includes(m.key)),
);

const rows = computed<ReportRow[]>(() => props.result?.rows ?? []);

const width = computed(() => LABEL_W + rows.value.length * (CELL + GAP));
const height = computed(() => Math.max(activeMeasures.value.length * (CELL + GAP) + 16, CELL));

/** Per-measure scale — measures rarely share a unit, so one ramp each. */
const scales = computed<Record<string, number>>(() => {
    const out: Record<string, number> = {};
    for (const measure of activeMeasures.value) {
        const nums = rows.value
            .map(r => r.values[measure.key])
            .filter((v): v is number => typeof v === 'number' && Number.isFinite(v));
        out[measure.key] = Math.max(1, ...nums);
    }
    return out;
});

interface Cell {
    row: ReportRow;
    measure: MeasureSchema;
    x: number;
    y: number;
    value: number | null;
    fill: string;
    display: string;
}

const cells = computed<Cell[]>(() => {
    const out: Cell[] = [];

    activeMeasures.value.forEach((measure, m) => {
        const base = seriesColor(props.theme, m);

        rows.value.forEach((row, i) => {
            const value = row.values[measure.key] ?? null;
            const ratio = value === null ? 0 : Math.min(1, value / scales.value[measure.key]);

            out.push({
                row,
                measure,
                x: LABEL_W + i * (CELL + GAP),
                y: m * (CELL + GAP),
                value,
                fill: value === null ? withAlpha(props.theme.muted, 0.1) : withAlpha(base, 0.12 + ratio * 0.78),
                display: formatMeasure(value, measure.format, measure.decimals),
            });
        });
    });

    return out;
});

const summary = computed(() => t('charts.a11y.summary', {
    chart: t('charts.type.heatmap'),
    rows: rows.value.length,
    measure: activeMeasures.value.map(m => t(m.labelKey)).join(', ') || t('charts.empty.noData'),
    total: activeMeasures.value
        .map(m => formatMeasure(props.result?.totals?.[m.key] ?? null, m.format, m.decimals))
        .join(' / '),
}));

function segmentLabel(cell: Cell): string {
    return t('charts.a11y.segment', {
        label: cell.row.label,
        measure: t(cell.measure.labelKey),
        value: cell.display,
    });
}

function activate(cell: Cell): void {
    emit('segment', cell.row, cell.measure.key);
}
</script>

<template>
    <div v-if="cells.length > 0" class="myra-heatmap w-full overflow-x-auto">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            :style="{ minWidth: `${width}px` }"
            class="h-full w-full"
            role="img"
            :aria-label="summary"
        >
            <title>{{ summary }}</title>

            <text
                v-for="(measure, m) in activeMeasures"
                :key="`label-${measure.key}`"
                x="0"
                :y="m * (CELL + GAP) + CELL / 2 + 4"
                font-size="9"
                :fill="theme.tick"
            >{{ t(measure.labelKey) }}</text>

            <g
                v-for="cell in cells"
                :key="`${cell.measure.key}-${cell.row.key}`"
                class="myra-segment"
                role="button"
                tabindex="0"
                :aria-label="segmentLabel(cell)"
                @click="activate(cell)"
                @keydown.enter.prevent="activate(cell)"
                @keydown.space.prevent="activate(cell)"
            >
                <rect :x="cell.x" :y="cell.y" :width="CELL" :height="CELL" rx="3" :fill="cell.fill" />
                <!-- Value as text, so colour is never the sole carrier of meaning. -->
                <text
                    v-if="cell.value !== null && cell.display.length <= 4"
                    :x="cell.x + CELL / 2"
                    :y="cell.y + CELL / 2 + 3"
                    text-anchor="middle"
                    font-size="8"
                    :fill="theme.tick"
                >{{ cell.display }}</text>
            </g>

            <text
                v-for="(row, i) in rows"
                :key="`col-${row.key}`"
                :x="LABEL_W + i * (CELL + GAP) + CELL / 2"
                :y="height - 3"
                text-anchor="middle"
                font-size="8"
                :fill="theme.tick"
            >{{ row.label }}</text>
        </svg>
    </div>

    <p v-else class="text-sm text-muted-foreground">{{ t('charts.empty.noData') }}</p>
</template>

<style scoped>
.myra-segment {
    transition: opacity 150ms ease;
}

.myra-segment:hover {
    opacity: 0.8;
}

.myra-segment:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}

@media (prefers-reduced-motion: reduce) {
    .myra-segment {
        transition: none;
    }
}
</style>
