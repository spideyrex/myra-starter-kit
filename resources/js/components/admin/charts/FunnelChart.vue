<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { seriesColor, type ChartTheme } from './chartTheme';
import { formatMeasure } from './format';
import type { ReportResultPayload, ReportRow } from './types';

const props = withDefaults(defineProps<{
    theme: ChartTheme;
    result?: ReportResultPayload | null;
    measures?: string[];
}>(), { result: null });

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();

const W = 100;
const BAND = 26;
const GAP = 4;

const measure = computed(() => {
    const all = props.result?.measures ?? [];
    return all.find(m => !props.measures || props.measures.includes(m.key)) ?? all[0] ?? null;
});

const rows = computed<ReportRow[]>(() => props.result?.rows ?? []);
const height = computed(() => Math.max(rows.value.length * (BAND + GAP), BAND));

const max = computed(() => {
    const key = measure.value?.key;
    if (!key) return 1;
    const nums = rows.value.map(r => r.values[key] ?? 0);
    return Math.max(1, ...nums);
});

interface Band {
    row: ReportRow;
    value: number | null;
    path: string;
    color: string;
    y: number;
    width: number;
    label: string;
    display: string;
}

const bands = computed<Band[]>(() => {
    const key = measure.value?.key;
    if (!key) return [];

    return rows.value.map((row, i) => {
        const value = row.values[key] ?? null;
        const next = rows.value[i + 1]?.values[key] ?? value;

        const top = ((value ?? 0) / max.value) * W;
        const bottom = ((next ?? 0) / max.value) * W;
        const y = i * (BAND + GAP);

        const topLeft = (W - top) / 2;
        const bottomLeft = (W - bottom) / 2;

        return {
            row,
            value,
            y,
            width: top,
            color: row.isOther ? props.theme.muted : seriesColor(props.theme, i),
            path: `M ${topLeft} ${y} L ${topLeft + top} ${y} L ${bottomLeft + bottom} ${y + BAND} L ${bottomLeft} ${y + BAND} Z`,
            label: row.label,
            display: formatMeasure(value, measure.value?.format, measure.value?.decimals ?? 0),
        };
    });
});

const summary = computed(() => t('charts.a11y.summary', {
    chart: t('charts.type.funnel'),
    rows: rows.value.length,
    measure: measure.value ? t(measure.value.labelKey) : t('charts.empty.noData'),
    total: formatMeasure(
        measure.value ? (props.result?.totals?.[measure.value.key] ?? null) : null,
        measure.value?.format,
        measure.value?.decimals ?? 0,
    ),
}));

function segmentLabel(band: Band): string {
    return t('charts.a11y.segment', {
        label: band.label,
        measure: measure.value ? t(measure.value.labelKey) : '',
        value: band.display,
    });
}

function activate(band: Band): void {
    if (!measure.value) return;
    emit('segment', band.row, measure.value.key);
}
</script>

<template>
    <svg
        v-if="bands.length > 0"
        class="myra-funnel size-full"
        :viewBox="`0 0 ${W} ${height}`"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        :aria-label="summary"
    >
        <title>{{ summary }}</title>

        <g
            v-for="band in bands"
            :key="band.row.key"
            class="myra-segment"
            role="button"
            tabindex="0"
            :aria-label="segmentLabel(band)"
            @click="activate(band)"
            @keydown.enter.prevent="activate(band)"
            @keydown.space.prevent="activate(band)"
        >
            <path :d="band.path" :fill="band.color" />
            <!-- Value as text: colour is never the sole carrier of meaning. -->
            <text
                v-if="band.width > 34"
                :x="W / 2"
                :y="band.y + BAND / 2 + 2"
                text-anchor="middle"
                font-size="7"
                fill="var(--background)"
            >{{ band.label }} · {{ band.display }}</text>
        </g>
    </svg>

    <p v-else class="text-sm text-muted-foreground">{{ t('charts.empty.noData') }}</p>
</template>

<style scoped>
.myra-segment {
    transition: opacity 150ms ease;
}

.myra-segment:hover {
    opacity: 0.85;
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
