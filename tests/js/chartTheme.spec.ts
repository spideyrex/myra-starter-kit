import { readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { resolveChartTheme, seriesColor, withAlpha } from '@/components/admin/charts/chartTheme';

const CHARTS_DIR = resolve(__dirname, '../../resources/js/components/admin/charts');

afterEach(() => {
    vi.restoreAllMocks();
});

function stubComputedStyle(vars: Record<string, string>) {
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({
        getPropertyValue: (name: string) => vars[name] ?? '',
    } as unknown as CSSStyleDeclaration);
}

describe('chart theme', () => {
    it('falls back to a legible ramp when --chart-N is undefined', () => {
        stubComputedStyle({});
        const theme = resolveChartTheme();

        expect(theme.series).toHaveLength(8);
        theme.series.forEach(color => expect(color).toMatch(/^oklch\(/));
        expect(theme.grid).toBeTruthy();
        expect(theme.good).toBeTruthy();
        expect(theme.bad).toBeTruthy();
    });

    it('reads the CSS custom properties when they are defined', () => {
        stubComputedStyle({
            '--chart-1': 'oklch(0.5 0.1 10)',
            '--chart-3': 'oklch(0.5 0.1 30)',
            '--border': 'oklch(0.9 0 0)',
            '--muted-foreground': 'oklch(0.6 0 0)',
            '--success': 'oklch(0.6 0.1 160)',
            '--destructive': 'oklch(0.6 0.2 27)',
        });

        const theme = resolveChartTheme();

        expect(theme.series[0]).toBe('oklch(0.5 0.1 10)');
        expect(theme.series[2]).toBe('oklch(0.5 0.1 30)');
        expect(theme.grid).toBe('oklch(0.9 0 0)');
        expect(theme.tick).toBe('oklch(0.6 0 0)');
        expect(theme.good).toBe('oklch(0.6 0.1 160)');
        expect(theme.bad).toBe('oklch(0.6 0.2 27)');
    });

    it('keeps a token colour a token when made transparent', () => {
        expect(withAlpha('var(--chart-1)', 0.2)).toBe('color-mix(in oklch, var(--chart-1) 20%, transparent)');
        expect(withAlpha('x', 5)).toContain('100%');
        expect(withAlpha('x', -1)).toContain('0%');
    });

    it('cycles the series palette rather than running out', () => {
        stubComputedStyle({});
        const theme = resolveChartTheme();

        expect(seriesColor(theme, 8)).toBe(theme.series[0]);
        expect(seriesColor(theme, 9)).toBe(theme.series[1]);
    });
});

describe('chart component sources', () => {
    /** Colour must come from the theme composable, never from a literal. */
    it('contain no hex colour literal', () => {
        const offenders: string[] = [];

        for (const file of readdirSync(CHARTS_DIR)) {
            if (!/\.(vue|ts)$/.test(file)) continue;

            const source = readFileSync(join(CHARTS_DIR, file), 'utf8');
            if (/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/.test(source)) offenders.push(file);
        }

        expect(offenders).toEqual([]);
    });

    /** A stat-only dashboard must never pay for the chart library. */
    it('keeps chart.js behind a single module', () => {
        const importers: string[] = [];

        for (const file of readdirSync(CHARTS_DIR)) {
            if (!/\.(vue|ts)$/.test(file)) continue;

            const source = readFileSync(join(CHARTS_DIR, file), 'utf8');
            const pullsRuntime = source.split('\n').some(line =>
                /from ['"](chart\.js|vue-chartjs)['"]/.test(line) && !/^\s*import type/.test(line));

            if (pullsRuntime) importers.push(file);
        }

        expect(importers).toEqual(['ChartCanvas.vue']);
    });
});
