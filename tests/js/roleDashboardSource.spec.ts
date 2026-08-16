import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const put = vi.hoisted(() => vi.fn());
const destroy = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    router: { put, delete: destroy },
    usePage: () => ({ props: {} }),
}));

// THE REAL PAYLOAD: the bytes DashboardController actually rendered.
import fixture from './fixtures/dashboard-layout.json';
import { useDashboardLayout, type DashboardLayoutSource } from '@/composables/useDashboardLayout';
import { StatWidget, type DashboardLayoutPayload } from '@/composables/useDashboardWidgets';

const declared = [
    StatWidget.make('total_users').title('Total users').value(() => 1),
    StatWidget.make('pending_verifications').title('Pending').value(() => 2),
];

function realPayload(): DashboardLayoutPayload {
    return JSON.parse(JSON.stringify(fixture.layout)) as DashboardLayoutPayload;
}

/** The same server payload, permuted — a role default that differs from the personal one. */
function permutedPayload(): DashboardLayoutPayload {
    const payload = realPayload();
    const entries = [...payload.entries];
    entries.push(entries.shift()!);
    payload.entries = entries.map((entry, order) => ({ ...entry, order }));

    return payload;
}

function build(options: {
    saved?: DashboardLayoutPayload | null;
    source?: DashboardLayoutSource | null;
    authoringRole?: { id: number; name: string } | null;
} = {}) {
    const saved = ref<DashboardLayoutPayload | null>(options.saved ?? null);
    const source = ref<DashboardLayoutSource | null>(options.source ?? null);

    const layout = useDashboardLayout({
        dashboard: 'admin.dashboard',
        declared,
        saved,
        source,
        authoringRole: options.authoringRole ?? null,
        catalogue: () => fixture.catalogue as any[],
        editable: true,
        t: (key: string) => key,
        resolveRoute: (name: string, params?: any) => `/${name}/${params ?? ''}`,
    });

    return { layout, saved, source };
}

beforeEach(() => {
    put.mockReset();
    destroy.mockReset();
});

describe('dashboard layout source', () => {
    it('is inert with nothing configured: no source, no saved row, no overlay', () => {
        const { layout } = build();

        expect(layout.layout.value).toBeNull();
        expect(layout.customised.value).toBe(false);
        expect(layout.roleDefault.value).toBeNull();
        expect((layout.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'pending_verifications']);
    });

    it('offers no reset on a role default nobody has touched', () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'role', role: 'manager', hasRoleDefault: true },
        });

        expect(layout.customised.value).toBe(false);
        expect(layout.roleDefault.value).toBe('manager');
        // The role default is still rendered — only the RESET affordance is withheld.
        expect((layout.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'trend#fixture']);
    });

    it('offers reset on a personal layout, named after the role behind it', () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'personal', role: 'manager', hasRoleDefault: true },
        });

        expect(layout.customised.value).toBe(true);
        expect(layout.roleDefault.value).toBe('manager');
    });

    it('names no role when there is no role default to fall back to', () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'personal', role: null, hasRoleDefault: false },
        });

        expect(layout.customised.value).toBe(true);
        expect(layout.roleDefault.value).toBeNull();
    });

    it('reports nothing customised when the source is none', () => {
        const { layout } = build({ saved: null, source: { source: 'none', role: null, hasRoleDefault: false } });

        expect(layout.customised.value).toBe(false);
        expect(layout.roleDefault.value).toBeNull();
    });

    it('falls back to the v2.6 reading when the server emits no source at all', () => {
        const { layout } = build({ saved: realPayload() });

        expect(layout.customised.value).toBe(true);
    });
});

describe('reset to the role default', () => {
    it('re-derives from the payload the reload returned and lands clean', async () => {
        const roleDefault = permutedPayload();
        const { layout, saved, source } = build({
            saved: realPayload(),
            source: { source: 'personal', role: 'manager', hasRoleDefault: true },
        });

        layout.moveTo('trend#fixture', 0);
        expect(layout.dirty.value).toBe(true);

        destroy.mockImplementation((_url: string, options: any) => {
            // What the partial reload brings back: the role default now wins.
            saved.value = roleDefault;
            source.value = { source: 'role', role: 'manager', hasRoleDefault: true };
            options.onFinish();
        });

        await layout.reset();

        expect(layout.dirty.value).toBe(false);
        expect(layout.customised.value).toBe(false);
        expect((layout.widgets.value as any[]).map(w => w.key))
            .toEqual(roleDefault.entries.filter(e => e.hidden !== true).map(e => e.key));
    });

    it('lands clean with an empty state when nothing replaces the personal row', async () => {
        const { layout, saved, source } = build({
            saved: realPayload(),
            source: { source: 'personal', role: null, hasRoleDefault: false },
        });

        destroy.mockImplementation((_url: string, options: any) => {
            saved.value = null;
            source.value = { source: 'none', role: null, hasRoleDefault: false };
            options.onFinish();
        });

        await layout.reset();

        expect(layout.dirty.value).toBe(false);
        expect(layout.layout.value).toBeNull();
        expect((layout.widgets.value as any[]).map(w => w.key)).toEqual(['total_users', 'pending_verifications']);
    });

    it('uses the existing personal endpoint — resetting to a role default is a label, not a mechanism', async () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'personal', role: 'manager', hasRoleDefault: true },
        });

        destroy.mockImplementation((_url: string, options: any) => options.onFinish());
        await layout.reset();

        expect(destroy.mock.calls[0][0]).toBe('/admin.dashboard-layouts.destroy/admin.dashboard');
        expect(destroy.mock.calls[0][1].only).toContain('dashboardLayoutSource');
    });
});

describe('authoring a role dashboard', () => {
    it('points save and reset at the role endpoints', async () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'role', role: 'manager', hasRoleDefault: true },
            authoringRole: { id: 7, name: 'manager' },
        });

        put.mockImplementation((_url: string, _data: unknown, options: any) => options.onFinish());
        destroy.mockImplementation((_url: string, options: any) => options.onFinish());

        layout.moveTo('trend#fixture', 0);
        await layout.save();
        await layout.reset();

        expect(put.mock.calls[0][0]).toBe('/admin.role-dashboards.update/7');
        expect(put.mock.calls[0][1].dashboard_key).toBe('admin.dashboard');
        expect(destroy.mock.calls[0][0]).toBe('/admin.role-dashboards.destroy/7');
    });

    it('PUTs the same request shape a personal save does', () => {
        const { layout } = build({
            saved: realPayload(),
            source: { source: 'role', role: 'manager', hasRoleDefault: true },
            authoringRole: { id: 7, name: 'manager' },
        });

        const instance = layout.payload().instances[0];

        expect(instance.catalogue).toBe('trend');
        expect(instance.binding).not.toHaveProperty('report');
        expect(JSON.stringify(layout.payload())).not.toContain('"permission"');
    });
});
