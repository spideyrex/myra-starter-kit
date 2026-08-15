import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import { measure, result, row, testTheme } from './helpers/reportFixture';
import HeatmapChart from '@/components/admin/charts/HeatmapChart.vue';
import FunnelChart from '@/components/admin/charts/FunnelChart.vue';
import GaugeChart from '@/components/admin/charts/GaugeChart.vue';
import SparklineChart from '@/components/admin/charts/SparklineChart.vue';

const charts = [
    ['HeatmapChart', HeatmapChart],
    ['FunnelChart', FunnelChart],
    ['GaugeChart', GaugeChart],
    ['SparklineChart', SparklineChart],
] as const;

const payload = result({
    rows: [
        row('a', { signups: 5 }),
        row('b', { signups: 8 }),
        row('c', { signups: 2 }),
    ],
});

function chart(component: any, props: Record<string, any> = {}) {
    return mount(component, {
        props: { theme: testTheme, result: payload, ...props },
        global: { plugins: [testI18n()] },
    });
}

describe.each(charts)('%s a11y', (_name, component) => {
    it('exposes the whole chart as one labelled image', () => {
        const svg = chart(component).find('svg');

        expect(svg.exists()).toBe(true);
        expect(svg.attributes('role')).toBe('img');
        expect(svg.attributes('aria-label')?.length).toBeGreaterThan(0);
        expect(svg.find('title').text().length).toBeGreaterThan(0);
    });

    it('makes every segment focusable and labelled', () => {
        const segments = chart(component).findAll('g[role="button"]');

        expect(segments.length).toBeGreaterThan(0);
        segments.forEach((segment) => {
            expect(segment.attributes('tabindex')).toBe('0');
            expect(segment.attributes('aria-label')?.length).toBeGreaterThan(0);
        });
    });

    it('fires the same segment event on click, Enter and Space', async () => {
        const w = chart(component);
        const segment = w.findAll('g[role="button"]')[0];

        await segment.trigger('click');
        await segment.trigger('keydown', { key: 'Enter' });
        await segment.trigger('keydown', { key: ' ' });

        const emitted = w.emitted('segment') as any[][];
        expect(emitted).toHaveLength(3);
        expect(emitted[1]).toEqual(emitted[0]);
        expect(emitted[2]).toEqual(emitted[0]);
    });

    it('renders an empty state rather than crashing on zero rows', () => {
        const w = chart(component, { result: result({ rows: [], totals: {}, groupCount: 0 }) });

        expect(w.find('svg').exists()).toBe(false);
        expect(w.text()).toContain('No data available.');
    });
});

describe('GaugeChart', () => {
    it('reads as good when the goal is met and bad when it is not', () => {
        const met = chart(GaugeChart, {
            result: result({ measures: [measure({ goal: 10 })], totals: { signups: 15 } }),
        });
        const missed = chart(GaugeChart, {
            result: result({ measures: [measure({ goal: 100 })], totals: { signups: 15 } }),
        });

        expect(met.html()).toContain(testTheme.good);
        expect(missed.html()).toContain(testTheme.bad);
    });

    it('inverts the verdict for an inverted-trend measure', () => {
        const w = chart(GaugeChart, {
            result: result({ measures: [measure({ goal: 10, invertTrend: true })], totals: { signups: 15 } }),
        });

        expect(w.html()).toContain(testTheme.bad);
    });
});

describe('SparklineChart', () => {
    it('renders a bare series without adding tab stops on a stat card', () => {
        const w = mount(SparklineChart, {
            props: { theme: testTheme, points: [1, 4, 2, 9], interactive: false },
            global: { plugins: [testI18n()] },
        });

        expect(w.find('svg').attributes('role')).toBe('img');
        expect(w.findAll('g[role="button"]')).toHaveLength(0);
        expect(w.find('polyline').exists()).toBe(true);
    });

    it('draws the comparison series dashed and separate', () => {
        const w = chart(SparklineChart, {
            result: result({
                comparison: { mode: 'previous', from: 'x', to: 'y' },
                rows: [
                    row('a', { signups: 5 }, { previous: { signups: 3 } }),
                    row('b', { signups: 8 }, { previous: { signups: 9 } }),
                ],
            }),
        });

        const dashed = w.findAll('polyline').filter(p => p.attributes('stroke-dasharray'));
        expect(dashed).toHaveLength(1);
    });
});

describe('HeatmapChart', () => {
    it('scales each measure on its own ramp', () => {
        const w = chart(HeatmapChart, {
            result: result({
                measures: [measure(), measure({ key: 'active' })],
                rows: [row('a', { signups: 100, active: 1 }), row('b', { signups: 50, active: 2 })],
            }),
        });

        expect(w.findAll('g[role="button"]')).toHaveLength(4);
    });
});
