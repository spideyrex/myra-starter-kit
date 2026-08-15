import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import {
    ChartWidget, StatWidget, TableWidget, resolveWidgets, spanClasses,
} from '@/composables/useDashboardWidgets';
import { chartComponents } from '@/components/admin/charts/ChartRegistry';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';

const allow = () => true;

describe('widget declaration guards', () => {
    it('refuses a widget that declares both a report binding and a data closure', () => {
        expect(() => ChartWidget.make('sales').data(() => ({})).report('users'))
            .toThrowError(/\[sales\]/);
    });

    it('refuses the reverse order too', () => {
        expect(() => ChartWidget.make('sales').report('users').data(() => ({})))
            .toThrowError(/\[sales\]/);
    });

    it('names the offending widget so the message is actionable', () => {
        expect(() => TableWidget.make('orders').data(() => []).report('users'))
            .toThrowError(/orders/);
    });

    it('leaves the closure path alone', () => {
        expect(() => ChartWidget.make('legacy').type('bar').data(() => ({ labels: [], datasets: [] })))
            .not.toThrow();
    });

    it('keeps a report binding serialisable', () => {
        const schema = ChartWidget.make('growth')
            .report('users').dimension('created_at').measures(['signups'])
            .bucket('month').compare('previous').limit(20)
            .toSchema();

        expect(schema.binding).toEqual({
            report: 'users',
            mode: 'series',
            dimension: 'created_at',
            measures: ['signups'],
            bucket: 'month',
            compare: 'previous',
            limit: 20,
        });
        expect(JSON.parse(JSON.stringify(schema.binding))).toEqual(schema.binding);
    });
});

describe('spanClasses', () => {
    it('clamps a span wider than the grid', () => {
        expect(spanClasses(9)).toEqual(['col-span-1', 'sm:col-span-2', 'lg:col-span-4']);
    });

    it('never emits a base-breakpoint span, because the grid is single column there', () => {
        expect(spanClasses(3)[0]).toBe('col-span-1');
    });

    it('accepts a per-breakpoint object', () => {
        expect(spanClasses({ sm: 1, lg: 3 })).toEqual(['col-span-1', 'sm:col-span-1', 'lg:col-span-3']);
    });

    it('falls back to a single column for a nonsense value', () => {
        expect(spanClasses(Number.NaN)).toEqual(['col-span-1', 'sm:col-span-1', 'lg:col-span-1']);
        expect(spanClasses(0)).toEqual(['col-span-1', 'sm:col-span-1', 'lg:col-span-1']);
    });

    it('adds a clamped row span only when asked', () => {
        expect(spanClasses(1)).not.toContain('row-span-2');
        expect(spanClasses(1, 9)).toContain('row-span-4');
    });
});

describe('resolveWidgets', () => {
    const widgets = [
        StatWidget.make('a').permission('reports.view'),
        StatWidget.make('b'),
        StatWidget.make('c').sort(-1),
    ];

    it('drops a widget the actor lacks permission for', () => {
        const keys = resolveWidgets(widgets, {}, ability => ability !== 'reports.view').map(w => w.key);

        expect(keys).not.toContain('a');
        expect(keys).toContain('b');
    });

    it('sorts by sort, then by declaration order', () => {
        expect(resolveWidgets(widgets, {}, allow).map(w => w.key)).toEqual(['c', 'a', 'b']);
    });

    it('honours a visibility predicate against the page props', () => {
        const conditional = [StatWidget.make('x').visible(p => p.showX)];

        expect(resolveWidgets(conditional, { showX: false }, allow)).toHaveLength(0);
        expect(resolveWidgets(conditional, { showX: true }, allow)).toHaveLength(1);
    });
});

describe('DashboardGrid', () => {
    function grid(widgets: any[], pageProps: any = {}) {
        return mount(DashboardGrid, {
            props: { widgets, pageProps },
            global: { plugins: [testI18n()] },
        });
    }

    it('never loads the chart library for a stat-only dashboard', () => {
        const loader = vi.spyOn(chartComponents, 'bar');

        grid([StatWidget.make('one').value(() => 1), StatWidget.make('two').value(() => 2)]);

        expect(loader).not.toHaveBeenCalled();
        loader.mockRestore();
    });

    it('emits clamped span classes rather than an inline grid-column style', () => {
        const w = grid([StatWidget.make('wide').colSpan(9).value(() => 1)]);
        const cell = w.find('.lg\\:col-span-4');

        expect(cell.exists()).toBe(true);
        expect(cell.attributes('style')).toBeUndefined();
    });

    it('hides a widget the signed-in actor lacks permission for', () => {
        const pageProps = { auth: { user: { roles: [], permissions: ['other'] } } };
        const w = grid([StatWidget.make('secret').permission('reports.view').value(() => 1)], pageProps);

        expect(w.findAll('.lg\\:col-span-1')).toHaveLength(0);
    });

    it('shows everything to a super-admin', () => {
        const pageProps = { auth: { user: { roles: ['super-admin'], permissions: [] } } };
        const w = grid([StatWidget.make('secret').permission('reports.view').value(() => 1)], pageProps);

        expect(w.findAll('.lg\\:col-span-1')).toHaveLength(1);
    });
});
