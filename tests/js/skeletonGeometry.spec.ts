import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SkeletonStat from '@/components/admin/skeletons/SkeletonStat.vue';
import SkeletonChart from '@/components/admin/skeletons/SkeletonChart.vue';
import SkeletonTable from '@/components/admin/skeletons/SkeletonTable.vue';
import WidgetSkeleton from '@/components/admin/skeletons/WidgetSkeleton.vue';
import { CHART_HEIGHT, ROW_HEIGHT, STAT_HEIGHT } from '@/components/admin/skeletons/geometry';

/**
 * A skeleton that resizes on load feels slower than a spinner. These assertions
 * are the contract that it does not.
 */
describe('skeleton geometry', () => {
    it('reserves exactly StatCard height', () => {
        const w = mount(SkeletonStat);

        expect(w.find('[data-skeleton="stat"]').attributes('style')).toContain(`height: ${STAT_HEIGHT}px`);
    });

    it('mirrors StatCard chrome so the box is not merely tall but identical', () => {
        const w = mount(SkeletonStat);
        const classes = w.find('[data-skeleton="stat"]').classes();

        for (const cls of ['rounded-xl', 'border', 'bg-card', 'p-5']) {
            expect(classes).toContain(cls);
        }
    });

    it('honours the chart height it is given, and defaults to the chart default', () => {
        const custom = mount(SkeletonChart, { props: { height: 320 } });
        expect(custom.find('[data-skeleton="chart"]').attributes('style')).toContain('height: 320px');

        const fallback = mount(SkeletonChart);
        expect(fallback.find('[data-skeleton="chart"]').attributes('style')).toContain(`height: ${CHART_HEIGHT}px`);
    });

    it('renders one bar per requested row at the real row height', () => {
        const w = mount(SkeletonTable, { props: { rows: 7 } });

        const rows = w.findAll('[data-skeleton-row]');

        expect(rows).toHaveLength(7);
        expect(rows[0].attributes('style')).toContain(`height: ${ROW_HEIGHT}px`);
    });

    it('caps a hostile row count rather than rendering thousands of bars', () => {
        const w = mount(SkeletonTable, { props: { rows: 9_999 } });

        expect(w.findAll('[data-skeleton-row]').length).toBeLessThanOrEqual(50);
    });

    it('dispatches by widget type', () => {
        expect(mount(WidgetSkeleton, { props: { type: 'stat' } }).find('[data-skeleton="stat"]').exists()).toBe(true);
        expect(mount(WidgetSkeleton, { props: { type: 'chart' } }).find('[data-skeleton="chart"]').exists()).toBe(true);
        expect(mount(WidgetSkeleton, { props: { type: 'table' } }).find('[data-skeleton="table"]').exists()).toBe(true);
    });

    it('drops the pulse under reduced motion', () => {
        const w = mount(SkeletonStat);

        expect(w.html()).toContain('motion-reduce:animate-none');
    });
});
