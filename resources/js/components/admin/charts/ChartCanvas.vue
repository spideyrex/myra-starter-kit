<script setup lang="ts">
import { computed, ref, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Bar, Line, Pie, Doughnut, Radar, Scatter, PolarArea } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, RadialLinearScale, BarElement,
    PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler,
    type Chart, type ChartData, type ChartOptions,
} from 'chart.js';
import { chartOptionsFor, type ChartType, type MyraDataset, toChartData } from './ChartRegistry';
import type { ChartTheme } from './chartTheme';
import type { ReportResultPayload, ReportRow } from './types';

/**
 * The ONLY file that imports chart.js or vue-chartjs. Everything else reaches
 * it through ChartRegistry's lazy map, so a stat-only dashboard never loads it.
 */
ChartJS.register(
    CategoryScale, LinearScale, RadialLinearScale, BarElement,
    PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler,
);

/** Horizontal reference line for a measure goal — replaces an annotation plugin. */
const goalLinePlugin = {
    id: 'goalLine',
    afterDatasetsDraw(chart: Chart, _args: unknown, options: { value?: number; color?: string }) {
        if (options?.value === undefined || options.value === null) return;

        const scale = chart.scales.y;
        if (!scale) return;

        const y = scale.getPixelForValue(options.value);
        const { left, right } = chart.chartArea;
        const ctx = chart.ctx;

        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([6, 4]);
        ctx.lineWidth = 1.5;
        ctx.strokeStyle = options.color ?? 'currentColor';
        ctx.moveTo(left, y);
        ctx.lineTo(right, y);
        ctx.stroke();
        ctx.restore();
    },
};

ChartJS.register(goalLinePlugin as any);

const props = withDefaults(defineProps<{
    type: ChartType;
    theme: ChartTheme;
    result?: ReportResultPayload | null;
    measures?: string[];
    /** Legacy `.data(fn)` pass-through — already Chart.js shaped. */
    data?: ChartData | null;
    stacked?: boolean;
    legend?: boolean;
    goal?: number | null;
    ariaLabel?: string;
}>(), {
    result: null,
    data: null,
    goal: null,
});

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();
const canvasRef = ref<any>(null);

/**
 * Typed as the generic `Component` on purpose. vue-chartjs narrows `data`/`options`
 * to one ChartTypeRegistry key per component, so a union of the seven renderers
 * would demand props assignable to ALL of them at once — impossible, since
 * ChartData<'line'> and ChartData<'pie'> disagree on their data element type.
 * Erasing the narrowing here is the only structural option; correctness still
 * holds because toChartData/chartOptionsFor branch on the same `props.type`.
 */
const renderer = computed<Component>(() => {
    switch (props.type) {
        case 'line':
        case 'area':
        case 'stackedArea':
            return Line;
        case 'pie':
            return Pie;
        case 'doughnut':
            return Doughnut;
        case 'radar':
            return Radar;
        case 'scatter':
            return Scatter;
        case 'polarArea':
            return PolarArea;
        default:
            return Bar;
    }
});

const chartData = computed<ChartData>(() => {
    const raw = props.result
        ? toChartData(props.result, props.type, props.theme, props.measures)
        : (props.data ?? { labels: [], datasets: [] } as ChartData);

    return {
        ...raw,
        datasets: (raw.datasets ?? []).map((dataset: any) => {
            const meta = (dataset as MyraDataset).myra;
            if (!meta) return dataset;

            return {
                ...dataset,
                label: meta.isComparison
                    ? `${t(meta.labelKey)} · ${t('charts.comparison')}`
                    : t(meta.labelKey),
            };
        }),
    } as ChartData;
});

const chartOptions = computed<ChartOptions>(() => ({
    ...chartOptionsFor(props.type, props.theme, {
        stacked: props.stacked,
        legend: props.legend,
        goal: props.goal,
    }),
    onClick: handleClick,
} as ChartOptions));

function handleClick(_event: unknown, elements: Array<{ index: number; datasetIndex: number }>): void {
    const hit = elements?.[0];
    const rows = props.result?.rows;
    if (!hit || !rows?.length) return;

    const row = rows[hit.index];
    if (!row) return;

    const dataset = chartData.value.datasets?.[hit.datasetIndex] as MyraDataset | undefined;
    const measureKey = dataset?.myra?.measureKey ?? props.result?.measures?.[0]?.key ?? '';

    emit('segment', row, measureKey);
}

const summary = computed(() => props.ariaLabel ?? t('charts.a11y.canvas', {
    chart: t(`charts.type.${props.type}`),
    rows: props.result?.rows?.length ?? chartData.value.labels?.length ?? 0,
}));
</script>

<template>
    <div class="relative size-full">
        <component
            :is="renderer"
            ref="canvasRef"
            :data="chartData"
            :options="chartOptions"
            role="img"
            :aria-label="summary"
        />
    </div>
</template>
