import { defineAsyncComponent, type Component } from 'vue';
import type { ChartData, ChartDataset, ChartOptions } from 'chart.js';
import type { ChartTheme } from './chartTheme';
import { seriesColor, withAlpha } from './chartTheme';
import type { DimensionType, ReportResultPayload } from './types';

export type ChartType =
    | 'bar' | 'stackedBar' | 'horizontalBar' | 'line' | 'area' | 'stackedArea'
    | 'pie' | 'doughnut' | 'radar' | 'scatter' | 'polarArea'
    | 'heatmap' | 'funnel' | 'gauge' | 'sparkline';

/** Rendered by Chart.js — all of them behind the single ChartCanvas chunk. */
export const CANVAS_TYPES: ChartType[] = [
    'bar', 'stackedBar', 'horizontalBar', 'line', 'area', 'stackedArea',
    'pie', 'doughnut', 'radar', 'scatter', 'polarArea',
];

/** Rendered as inline SVG — no chart library, no chunk. */
export const SVG_TYPES: ChartType[] = ['heatmap', 'funnel', 'gauge', 'sparkline'];

export const CHART_TYPES: ChartType[] = [...CANVAS_TYPES, ...SVG_TYPES];

export const CIRCULAR_TYPES: ChartType[] = ['pie', 'doughnut', 'polarArea'];

const canvasLoader = () => import('./ChartCanvas.vue');

/**
 * Lazy renderer map. Nothing here is imported eagerly, so a dashboard of stat
 * cards never pays for chart.js.
 */
export const chartComponents: Record<ChartType, () => Promise<Component>> = {
    bar: canvasLoader,
    stackedBar: canvasLoader,
    horizontalBar: canvasLoader,
    line: canvasLoader,
    area: canvasLoader,
    stackedArea: canvasLoader,
    pie: canvasLoader,
    doughnut: canvasLoader,
    radar: canvasLoader,
    scatter: canvasLoader,
    polarArea: canvasLoader,
    heatmap: () => import('./HeatmapChart.vue'),
    funnel: () => import('./FunnelChart.vue'),
    gauge: () => import('./GaugeChart.vue'),
    sparkline: () => import('./SparklineChart.vue'),
};

export function isChartType(value: unknown): value is ChartType {
    return typeof value === 'string' && (CHART_TYPES as string[]).includes(value);
}

export function resolveChartComponent(type: ChartType): Component {
    return defineAsyncComponent(chartComponents[type] ?? canvasLoader);
}

export function usesCanvas(type: ChartType): boolean {
    return CANVAS_TYPES.includes(type);
}

/** Which chart types a dimension/measure combination can honestly render. */
export function chartTypesFor(dimensionType: DimensionType, measureCount: number): ChartType[] {
    const multi = measureCount > 1;

    if (dimensionType === 'date') {
        const types: ChartType[] = ['line', 'area', 'bar'];
        if (multi) types.push('stackedBar', 'stackedArea', 'radar');
        types.push('sparkline', 'heatmap');
        return types;
    }

    const types: ChartType[] = ['bar', 'horizontalBar', 'pie', 'doughnut', 'polarArea', 'funnel'];
    if (measureCount === 1) types.push('gauge');
    if (multi) types.push('stackedBar', 'radar', 'scatter', 'heatmap');
    return types;
}

export interface ChartOptionInput {
    stacked?: boolean;
    legend?: boolean;
    goal?: number | null;
}

/**
 * Reactive Chart.js options. Callers MUST wrap this in a computed — the old
 * ChartWidget captured a plain object at setup and could never react to a
 * type or theme change.
 */
export function chartOptionsFor(
    type: ChartType,
    theme: ChartTheme,
    opts: ChartOptionInput = {},
): ChartOptions {
    const circular = CIRCULAR_TYPES.includes(type);
    const stacked = opts.stacked ?? (type === 'stackedBar' || type === 'stackedArea');

    const base: Record<string, any> = {
        responsive: true,
        maintainAspectRatio: false,
        animation: prefersReducedMotion() ? false : undefined,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: opts.legend ?? (circular || type === 'radar'),
                labels: { color: theme.tick },
            },
            tooltip: { enabled: true },
        },
    };

    if (opts.goal !== null && opts.goal !== undefined) {
        base.plugins.goalLine = { value: opts.goal, color: theme.comparison };
    }

    if (circular) return base as ChartOptions;

    if (type === 'radar') {
        base.scales = {
            r: {
                beginAtZero: true,
                grid: { color: theme.grid },
                angleLines: { color: theme.grid },
                pointLabels: { color: theme.tick },
                ticks: { color: theme.tick, backdropColor: 'transparent' },
            },
        };
        return base as ChartOptions;
    }

    if (type === 'horizontalBar') base.indexAxis = 'y';

    base.scales = {
        x: {
            stacked,
            grid: { display: type === 'horizontalBar', color: theme.grid },
            ticks: { color: theme.tick },
            border: { color: theme.grid },
        },
        y: {
            stacked,
            beginAtZero: true,
            grid: { display: type !== 'horizontalBar', color: theme.grid },
            ticks: { color: theme.tick },
            border: { color: theme.grid },
        },
    };

    return base as ChartOptions;
}

function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Extra fields toChartData hangs on a dataset for the renderer/legend. */
export interface MyraDatasetMeta {
    labelKey: string;
    measureKey: string;
    isComparison: boolean;
}

export type MyraDataset = ChartDataset<any, any> & { myra: MyraDatasetMeta };

/**
 * ReportResultPayload -> Chart.js data. A comparison becomes a second dashed
 * dataset of identical length; the server already aligned the two series.
 * A missing value stays `null` so Chart.js draws a gap, never a false zero.
 */
export function toChartData(
    result: ReportResultPayload,
    type: ChartType,
    theme: ChartTheme,
    measureKeys?: string[],
): ChartData {
    const rows = result.rows ?? [];
    const labels = rows.map(row => row.label);
    const measures = (result.measures ?? []).filter(
        m => !measureKeys || measureKeys.includes(m.key),
    );

    if (type === 'scatter') {
        return scatterData(result, measures, theme);
    }

    if (CIRCULAR_TYPES.includes(type)) {
        const measure = measures[0];
        const data = measure ? rows.map(row => row.values[measure.key] ?? null) : [];

        return {
            labels,
            datasets: [{
                label: measure?.labelKey ?? '',
                data,
                backgroundColor: rows.map((row, i) => row.isOther ? theme.muted : seriesColor(theme, i)),
                borderColor: rows.map(() => 'transparent'),
                myra: { labelKey: measure?.labelKey ?? '', measureKey: measure?.key ?? '', isComparison: false },
            } as MyraDataset],
        } as ChartData;
    }

    const filled = type === 'area' || type === 'stackedArea';
    const datasets: MyraDataset[] = [];

    measures.forEach((measure, i) => {
        const color = seriesColor(theme, i);

        datasets.push({
            label: measure.labelKey,
            data: rows.map(row => row.values[measure.key] ?? null),
            backgroundColor: filled ? withAlpha(color, 0.18) : color,
            borderColor: color,
            borderWidth: 2,
            fill: filled ? 'origin' : false,
            tension: type === 'line' || type === 'area' || type === 'stackedArea' ? 0.3 : 0,
            borderRadius: type.toLowerCase().includes('bar') ? 6 : 0,
            borderSkipped: false,
            pointRadius: rows.length > 40 ? 0 : 3,
            spanGaps: false,
            myra: { labelKey: measure.labelKey, measureKey: measure.key, isComparison: false },
        } as MyraDataset);
    });

    if (result.comparison) {
        measures.forEach((measure) => {
            datasets.push({
                label: measure.labelKey,
                data: rows.map(row => row.previous?.[measure.key] ?? null),
                backgroundColor: withAlpha(theme.comparison, 0.12),
                borderColor: theme.comparison,
                borderWidth: 2,
                borderDash: [5, 4],
                fill: false,
                tension: 0.3,
                pointRadius: 0,
                spanGaps: false,
                myra: { labelKey: measure.labelKey, measureKey: measure.key, isComparison: true },
            } as MyraDataset);
        });
    }

    return { labels, datasets } as ChartData;
}

function scatterData(
    result: ReportResultPayload,
    measures: ReportResultPayload['measures'],
    theme: ChartTheme,
): ChartData {
    const [x, y] = measures;
    const rows = result.rows ?? [];

    if (!x || !y) return { labels: [], datasets: [] } as ChartData;

    return {
        datasets: [{
            label: y.labelKey,
            data: rows.map(row => ({ x: row.values[x.key] ?? null, y: row.values[y.key] ?? null })),
            backgroundColor: rows.map((row, i) => row.isOther ? theme.muted : seriesColor(theme, i)),
            pointRadius: 5,
            myra: { labelKey: y.labelKey, measureKey: y.key, isComparison: false },
        } as unknown as MyraDataset],
    } as ChartData;
}
