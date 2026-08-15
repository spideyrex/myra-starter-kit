import { computed, ref, type ComputedRef } from 'vue';

/**
 * The chart palette. `useThemeColors.ts` is a theme-PRESET registry — it maps
 * preset names to --primary/--ring values and exposes no series colours.
 *
 * Every colour a chart draws comes from here, so light/dark and the active
 * preset are honoured without a single literal in a chart component.
 */
export interface ChartTheme {
    series: string[];
    grid: string;
    tick: string;
    muted: string;
    comparison: string;
    good: string;
    bad: string;
}

/** Legible on both --background values. app.css ships --chart-1..5 only. */
const FALLBACK_SERIES = [
    'oklch(0.646 0.222 41.116)',
    'oklch(0.600 0.118 184.704)',
    'oklch(0.548 0.190 264.376)',
    'oklch(0.700 0.180 145.000)',
    'oklch(0.680 0.210 300.000)',
    'oklch(0.720 0.170 85.000)',
    'oklch(0.620 0.200 20.000)',
    'oklch(0.660 0.130 220.000)',
];

const FALLBACK: ChartTheme = {
    series: FALLBACK_SERIES,
    grid: 'oklch(0.708 0 0 / 0.18)',
    tick: 'oklch(0.556 0 0)',
    muted: 'oklch(0.556 0 0)',
    comparison: 'oklch(0.556 0 0)',
    good: 'oklch(0.596 0.145 163.225)',
    bad: 'oklch(0.577 0.245 27.325)',
};

/** Bumped by the theme observer so every ChartTheme computed re-reads. */
const revision = ref(0);
let observer: MutationObserver | null = null;

function watchThemeChanges(): void {
    if (observer || typeof MutationObserver === 'undefined' || typeof document === 'undefined') return;

    observer = new MutationObserver(() => {
        revision.value++;
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
}

function read(style: CSSStyleDeclaration, name: string, fallback: string): string {
    const value = style.getPropertyValue(name).trim();
    return value || fallback;
}

export function resolveChartTheme(): ChartTheme {
    if (typeof document === 'undefined' || typeof getComputedStyle !== 'function') {
        return { ...FALLBACK, series: [...FALLBACK_SERIES] };
    }

    const style = getComputedStyle(document.documentElement);

    return {
        series: FALLBACK_SERIES.map((fallback, i) => read(style, `--chart-${i + 1}`, fallback)),
        grid: read(style, '--border', FALLBACK.grid),
        tick: read(style, '--muted-foreground', FALLBACK.tick),
        muted: read(style, '--muted-foreground', FALLBACK.muted),
        comparison: read(style, '--muted-foreground', FALLBACK.comparison),
        good: read(style, '--success', FALLBACK.good),
        bad: read(style, '--destructive', FALLBACK.bad),
    };
}

export function useChartTheme(): ComputedRef<ChartTheme> {
    watchThemeChanges();

    return computed(() => {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        revision.value;
        return resolveChartTheme();
    });
}

/** Transparent variant of a token colour — never a literal, never a parse. */
export function withAlpha(color: string, alpha: number): string {
    const pct = Math.round(Math.max(0, Math.min(1, alpha)) * 100);
    return `color-mix(in oklch, ${color} ${pct}%, transparent)`;
}

/** Series colour for index i, cycling. */
export function seriesColor(theme: ChartTheme, i: number): string {
    return theme.series[i % theme.series.length];
}
