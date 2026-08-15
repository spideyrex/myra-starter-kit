import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import { result, row } from './helpers/reportFixture';
import { ChartWidget, TableWidget } from '@/composables/useDashboardWidgets';
import ChartWidgetVue from '@/components/admin/widgets/ChartWidget.vue';
import TableWidgetVue from '@/components/admin/widgets/TableWidget.vue';

const canvasStub = { name: 'ChartCanvas', props: ['type', 'theme', 'result'], template: '<div class="stub-canvas" />' };

function chartWidget(widget: any, props: Record<string, any> = {}) {
    return mount(ChartWidgetVue, {
        props: { widget: widget.toSchema(), pageProps: {}, ...props },
        global: {
            plugins: [testI18n()],
            stubs: { ChartCanvas: canvasStub, AsyncComponentWrapper: canvasStub },
        },
    });
}

describe('ChartWidget states', () => {
    it('announces loading instead of rendering an empty canvas', () => {
        const w = chartWidget(ChartWidget.make('sales').report('demo'), { loading: true });

        expect(w.find('[aria-busy="true"]').exists()).toBe(true);
        expect(w.find('[role="status"]').text()).toBe('Loading chart data…');
    });

    it('renders an empty state rather than a blank card', () => {
        const w = chartWidget(
            ChartWidget.make('sales').report('demo'),
            { result: result({ rows: [], totals: {}, groupCount: 0 }) },
        );

        expect(w.text()).toContain('No data to chart');
    });

    it('uses the declared empty-state keys when given', () => {
        const widget = ChartWidget.make('sales').report('demo')
            .emptyState({ headingKey: 'charts.empty.noData', descriptionKey: 'charts.other' });

        const w = chartWidget(widget, { result: result({ rows: [], totals: {}, groupCount: 0 }) });

        expect(w.text()).toContain('No data available.');
        expect(w.text()).toContain('Other');
    });

    it('says out loud that the categorical chart is truncated', () => {
        const w = chartWidget(
            ChartWidget.make('sales').report('demo'),
            { result: result({ truncated: true, groupCount: 24 }) },
        );

        expect(w.text()).toContain('Showing top 2 of 24+');
    });

    it('offers the data as a table, so colour is never the only channel', async () => {
        const w = chartWidget(
            ChartWidget.make('sales').report('demo'),
            { result: result({ rows: [row('alpha', { signups: 7 })] }) },
        );

        const toggle = w.find('button[aria-pressed]');
        expect(toggle.exists()).toBe(true);
        expect(toggle.attributes('aria-pressed')).toBe('false');

        await toggle.trigger('click');

        expect(w.find('table').exists()).toBe(true);
        expect(w.text()).toContain('alpha');
        expect(w.text()).toContain('7');
    });

    it('keeps the legacy closure path working without a result', () => {
        const widget = ChartWidget.make('legacy').type('bar')
            .data(() => ({ labels: ['x'], datasets: [{ data: [1] }] }));

        const w = chartWidget(widget);

        expect(w.find('button[aria-pressed]').exists()).toBe(false);
        expect(w.text()).not.toContain('No data to chart');
    });
});

describe('TableWidget', () => {
    it('translates its empty state instead of hardcoding English', () => {
        const w = mount(TableWidgetVue, {
            props: { widget: TableWidget.make('t').columns(() => []).data(() => []).toSchema(), pageProps: {} },
            global: { plugins: [testI18n()] },
        });

        expect(w.text()).toContain('No data available.');
    });

    it('renders report buckets with an Other row labelled, not blank', () => {
        const payload = result({
            rows: [row('a', { signups: 5 }), row('__other', { signups: 2 }, { isOther: true })],
        });

        const w = mount(TableWidgetVue, {
            props: {
                widget: TableWidget.make('t').report('demo').measures(['signups']).toSchema(),
                pageProps: {},
                result: payload,
            },
            global: { plugins: [testI18n()] },
        });

        expect(w.findAll('tbody tr')).toHaveLength(2);
        expect(w.text()).toContain('Other');
    });
});
