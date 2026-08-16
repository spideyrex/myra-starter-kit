import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const put = vi.hoisted(() => vi.fn());
const destroy = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    router: { put, delete: destroy },
    usePage: () => ({ props: {} }),
}));

// THE REAL PAYLOAD: the props DashboardController actually rendered.
import fixture from './fixtures/dashboard-layout.json';
import { useDashboardLayout } from '@/composables/useDashboardLayout';
import { StatWidget, type DashboardLayoutPayload } from '@/composables/useDashboardWidgets';

const saved = fixture.layout as unknown as DashboardLayoutPayload;
const catalogue = fixture.catalogue as any[];

const declared = [
    StatWidget.make('total_users').title('Total users').value(() => 1),
    StatWidget.make('pending_verifications').title('Pending').value(() => 2),
];

function build(savedLayout: DashboardLayoutPayload | null = saved) {
    return useDashboardLayout({
        dashboard: 'admin.dashboard',
        declared,
        saved: ref(savedLayout),
        catalogue: () => catalogue,
        editable: true,
        t: (key: string) => key,
        resolveRoute: (name: string) => `/${name}`,
    });
}

describe('useDashboardLayout over the server payload', () => {
    it('renders the server-resolved instance alongside the declared widgets', () => {
        const keys = (build().widgets.value as any[]).map(w => w.key);

        expect(keys).toContain('total_users');
        expect(keys).toContain('trend#fixture');
    });

    it('honours the stored order and the stored hidden flag', () => {
        const d = build();

        expect((d.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'trend#fixture']);
        expect(d.hidden.value.map(w => w.key)).toEqual(['pending_verifications']);
    });

    it('starts clean and goes dirty on the first move', () => {
        const d = build();

        expect(d.dirty.value).toBe(false);
        d.moveTo('trend#fixture', 0);
        expect(d.dirty.value).toBe(true);
        expect((d.widgets.value as any[])[0].key).toBe('trend#fixture');
    });

    it('PUTs instances as a request — a catalogue key and a binding, never a schema', () => {
        const instance = build().payload().instances[0];

        expect(instance.catalogue).toBe('trend');
        expect(instance.binding).toEqual({ dimension: 'created_at', measures: ['signups'], limit: 12 });
        // `report` is the server's to add: it comes from the catalogue entry.
        expect(instance.binding).not.toHaveProperty('report');
        expect(instance).not.toHaveProperty('type');
        expect(instance).not.toHaveProperty('permission');
    });

    it('never PUTs a resolved schema field back to the server', () => {
        const json = JSON.stringify(build().payload());

        expect(json).not.toContain('"permission"');
        expect(json).not.toContain('"type"');
    });

    it('mints a unique instance key per add and tracks catalogue usage', () => {
        const d = build(null);

        const first = d.addInstance('trend', { dimension: 'status' } as any);
        const second = d.addInstance('trend', { dimension: 'created_at' } as any);

        expect(first).not.toBe(second);
        expect(first.startsWith('trend#')).toBe(true);
        expect(d.usedCatalogueKeys.value).toEqual(['trend', 'trend']);
        expect(d.payload().instances).toHaveLength(2);
    });

    it('undoes the last edit, twenty deep', () => {
        const d = build();

        d.toggleHidden('total_users');
        expect(d.hidden.value.map(w => w.key)).toContain('total_users');

        d.undo();
        expect(d.hidden.value.map(w => w.key)).not.toContain('total_users');
    });

    it('cancel returns to the server payload', () => {
        const d = build();

        d.moveTo('trend#fixture', 0);
        d.cancel();

        expect(d.dirty.value).toBe(false);
        expect((d.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'trend#fixture']);
    });

    it('applies no overlay at all when there is no saved row and nothing was edited', () => {
        const d = build(null);

        expect(d.layout.value).toBeNull();
        expect((d.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'pending_verifications']);
    });

    it('reloads only the dashboardLayout prop on save, so instances come back resolved', async () => {
        const d = build();
        d.moveTo('trend#fixture', 0);

        put.mockImplementation((_url: string, _data: unknown, options: any) => options.onFinish());
        await d.save();

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][0]).toBe('/admin.dashboard-layouts.update');
        expect(put.mock.calls[0][1].dashboard_key).toBe('admin.dashboard');
        expect(put.mock.calls[0][2].only).toContain('dashboardLayout');
    });
});
