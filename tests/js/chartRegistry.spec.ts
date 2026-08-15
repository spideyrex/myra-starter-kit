import { describe, expect, it } from 'vitest';
import {
    CHART_TYPES, chartComponents, chartOptionsFor, chartTypesFor, toChartData,
    type ChartType,
} from '@/components/admin/charts/ChartRegistry';
import { measure, result, row, testTheme } from './helpers/reportFixture';

describe('chart registry', () => {
    it('has a lazy renderer for every declared chart type', () => {
        for (const type of CHART_TYPES) {
            expect(typeof chartComponents[type]).toBe('function');
        }

        expect(Object.keys(chartComponents).sort()).toEqual([...CHART_TYPES].sort());
    });

    it('offers only chart types a dimension can honestly render', () => {
        expect(chartTypesFor('date', 1)).toContain('line');
        expect(chartTypesFor('date', 1)).not.toContain('stackedBar');
        expect(chartTypesFor('date', 2)).toContain('stackedBar');

        expect(chartTypesFor('field', 1)).toContain('gauge');
        expect(chartTypesFor('field', 2)).not.toContain('gauge');
        expect(chartTypesFor('relation', 1)).toContain('pie');
    });
});

describe('chartOptionsFor', () => {
    it('stacks both axes for a stacked bar', () => {
        const options = chartOptionsFor('stackedBar', testTheme) as any;

        expect(options.scales.x.stacked).toBe(true);
        expect(options.scales.y.stacked).toBe(true);
    });

    it('emits no scales for a pie', () => {
        const options = chartOptionsFor('pie', testTheme) as any;

        expect(options.scales).toBeUndefined();
    });

    it('flips the index axis for a horizontal bar', () => {
        expect((chartOptionsFor('horizontalBar', testTheme) as any).indexAxis).toBe('y');
    });

    it('takes every colour from the theme, never a literal', () => {
        const options = chartOptionsFor('bar', testTheme) as any;

        expect(options.scales.y.grid.color).toBe(testTheme.grid);
        expect(options.scales.y.ticks.color).toBe(testTheme.tick);
    });

    it('declares a goal reference line only when a goal is set', () => {
        expect((chartOptionsFor('bar', testTheme) as any).plugins.goalLine).toBeUndefined();
        expect((chartOptionsFor('bar', testTheme, { goal: 100 }) as any).plugins.goalLine.value).toBe(100);
    });

    it('is a fresh object per call, so a computed can react to a type change', () => {
        expect(chartOptionsFor('bar', testTheme)).not.toBe(chartOptionsFor('bar', testTheme));
    });
});

describe('toChartData', () => {
    it('builds one dataset per measure', () => {
        const payload = result({ measures: [measure(), measure({ key: 'active' })] });
        const data = toChartData(payload, 'line', testTheme) as any;

        expect(data.datasets).toHaveLength(2);
        expect(data.labels).toEqual(['a', 'b']);
    });

    it('adds a dashed comparison dataset of equal length', () => {
        const payload = result({
            comparison: { mode: 'previous', from: '2026-06-17', to: '2026-07-16' },
            rows: [
                row('a', { signups: 5 }, { previous: { signups: 3 } }),
                row('b', { signups: 8 }, { previous: { signups: 9 } }),
            ],
        });

        const data = toChartData(payload, 'line', testTheme) as any;

        expect(data.datasets).toHaveLength(2);
        expect(data.datasets[0].data).toHaveLength(data.datasets[1].data.length);
        expect(data.datasets[1].borderDash?.length).toBeGreaterThan(0);
        expect(data.datasets[1].myra.isComparison).toBe(true);
        expect(data.datasets[1].data).toEqual([3, 9]);
    });

    it('puts the comparison series in its own stack so a stacked chart never doubles a bar', () => {
        const payload = result({
            comparison: { mode: 'previous', from: '2026-06-17', to: '2026-07-16' },
            rows: [
                row('a', { signups: 5 }, { previous: { signups: 3 } }),
                row('b', { signups: 8 }, { previous: { signups: 9 } }),
            ],
        });

        const data = toChartData(payload, 'stackedBar', testTheme) as any;

        expect(data.datasets[0].stack).toBe('current');
        expect(data.datasets[1].stack).toBe('previous');
        expect(data.datasets[0].stack).not.toBe(data.datasets[1].stack);
    });

    it('keeps a missing value null so Chart.js draws a gap, never a false zero', () => {
        const payload = result({ rows: [row('a', { signups: null }), row('b', { signups: 8 })] });
        const data = toChartData(payload, 'line', testTheme) as any;

        expect(data.datasets[0].data[0]).toBeNull();
        expect(data.datasets[0].data[0]).not.toBe(0);
    });

    it('mutes an Other slice in a circular chart instead of colouring it as a series', () => {
        const payload = result({
            rows: [row('a', { signups: 5 }), row('__other', { signups: 2 }, { isOther: true })],
        });

        const data = toChartData(payload, 'pie', testTheme) as any;

        expect(data.datasets).toHaveLength(1);
        expect(data.datasets[0].backgroundColor[1]).toBe(testTheme.muted);
    });

    it('pairs two measures into points for a scatter', () => {
        const payload = result({
            measures: [measure(), measure({ key: 'active' })],
            rows: [row('a', { signups: 5, active: 2 })],
        });

        const data = toChartData(payload, 'scatter', testTheme) as any;

        expect(data.datasets[0].data[0]).toEqual({ x: 5, y: 2 });
    });

    it('never throws on an empty result', () => {
        const empty = result({ rows: [], totals: {}, groupCount: 0 });

        for (const type of CHART_TYPES as ChartType[]) {
            expect(() => toChartData(empty, type, testTheme)).not.toThrow();
        }
    });
});
