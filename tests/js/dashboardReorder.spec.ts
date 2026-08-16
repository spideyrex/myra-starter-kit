import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    router: { put: vi.fn(), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
}));

import { testI18n, uiStubs } from './helpers/i18n';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import { useDashboardLayout } from '@/composables/useDashboardLayout';
import { StatWidget } from '@/composables/useDashboardWidgets';

/**
 * The page renders SEVERAL grids over ONE global order. A positional index is
 * meaningful only inside the grid that produced it, so it must never cross the
 * component boundary.
 */
const declared = [
    StatWidget.make('stat_a').title('Stat A').value(() => 1),
    StatWidget.make('stat_b').title('Stat B').value(() => 2),
    StatWidget.make('chart_a').title('Chart A').value(() => 3),
    StatWidget.make('chart_b').title('Chart B').value(() => 4),
];

function build() {
    return useDashboardLayout({
        dashboard: 'admin.dashboard',
        declared,
        saved: ref(null),
        catalogue: () => [],
        editable: true,
        t: (key: string) => key,
        resolveRoute: (name: string) => `/${name}`,
    });
}

function order(d: ReturnType<typeof build>): string[] {
    return d.payload().entries.map(e => e.key);
}

describe('reorderWithin', () => {
    it('moves a tile inside its own grid and leaves the other grid untouched', () => {
        const d = build();

        // The chart grid renders ['chart_a', 'chart_b']; the user moves the
        // second one to the front of THAT grid.
        d.reorderWithin(['chart_b', 'chart_a'], 'chart_b');

        expect(order(d)).toEqual(['stat_a', 'stat_b', 'chart_b', 'chart_a']);
    });

    it('a grid-local index passed straight to moveTo would have hit the wrong tile', () => {
        const d = build();

        // moveTo is GLOBAL by contract: index 0 of the chart grid is not index 0
        // of the dashboard. This is exactly the bug reorderWithin removes.
        d.moveTo('chart_b', 0);

        expect(order(d)).toEqual(['chart_b', 'stat_a', 'stat_b', 'chart_a']);
    });

    it('keeps a hidden tile in its global slot', () => {
        const d = build();
        d.toggleHidden('stat_b');

        d.reorderWithin(['chart_b', 'chart_a'], 'chart_b');

        expect(order(d)).toEqual(['stat_a', 'stat_b', 'chart_b', 'chart_a']);
        expect(d.hidden.value.map(w => w.key)).toEqual(['stat_b']);
    });

    it('is a no-op for an unknown key and never marks the layout dirty', () => {
        const d = build();

        d.reorderWithin(['chart_b', 'nope']);
        d.reorderWithin([]);
        d.reorderWithin(['chart_a', 'chart_b']);

        expect(d.dirty.value).toBe(false);
        expect(order(d)).toEqual(['stat_a', 'stat_b', 'chart_a', 'chart_b']);
    });

    it('announces the moved tile', () => {
        const d = build();

        d.reorderWithin(['chart_b', 'chart_a'], 'chart_b');

        expect(d.announcement.value).not.toBe('');
    });
});

describe('DashboardGrid reorder emit', () => {
    it('emits its own key sequence, never a positional index', async () => {
        const w = mount(DashboardGrid, {
            props: {
                widgets: [
                    StatWidget.make('chart_a').title('Chart A').value(() => 1),
                    StatWidget.make('chart_b').title('Chart B').value(() => 2),
                ],
                pageProps: {},
                editing: true,
            } as any,
            global: {
                plugins: [testI18n()],
                // The tile chrome is a lazy chunk; only its handle matters here.
                stubs: {
                    ...uiStubs,
                    WidgetTileChrome: {
                        props: ['handleProps'],
                        template: '<button type="button" v-bind="handleProps" />',
                    },
                },
            },
        });

        await flushPromises();

        const handle = w.findAll('[data-reorder-key]')[0];
        expect(handle).toBeTruthy();

        await handle.trigger('keydown', { key: ' ' });
        await handle.trigger('keydown', { key: 'ArrowDown' });

        const emitted = w.emitted('reorder');

        expect(emitted).toBeTruthy();
        expect(emitted![0]).toEqual(['chart_a', ['chart_b', 'chart_a']]);
        expect(typeof emitted![0][1]).not.toBe('number');
    });
});
