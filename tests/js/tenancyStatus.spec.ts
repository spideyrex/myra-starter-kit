import { beforeAll, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import en from '@/i18n/locales/en.json';
import status from './fixtures/tenancy-status.json';
import TenancyPage from '@/Pages/Admin/Demo/Tenancy.vue';

const layoutStub = { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' };
const headStub = { name: 'Head', props: ['title'], template: '<span />' };
const linkStub = { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' };

beforeAll(() => {
    (globalThis as any).route = (name: string) => `/${name.replace(/\./g, '/')}`;
});

function mountPage(overrides: Record<string, any> = {}) {
    return mount(TenancyPage, {
        props: { status: { ...(status as any), ...overrides } },
        global: {
            plugins: [testI18n()],
            stubs: { AuthenticatedLayout: layoutStub, Head: headStub, Link: linkStub },
        },
    });
}

describe('Tenancy demo page', () => {
    it('renders the DISABLED banner from the real server payload', () => {
        const w = mountPage();

        expect((status as any).enabled).toBe(false);
        expect(w.get('[data-testid="tenancy-status"]').text()).toBe(en.tenancy.statusDisabled);
        expect(w.get('[data-testid="tenancy-banner"]').text()).toContain(en.tenancy.bannerDisabled);
    });

    it('shows one row per configured model, every one unscoped', () => {
        const w = mountPage();
        const rows = w.findAll('[data-testid="tenancy-model-row"]');

        expect(rows.length).toBe((status as any).models.length);
        expect(rows.length).toBeGreaterThan(0);

        for (const badge of w.findAll('[data-testid="tenancy-model-scoped"]')) {
            expect(badge.text()).toBe(en.tenancy.no);
        }

        expect(w.text()).toContain('App\\Models\\Article');
    });

    it('switches to the ENABLED banner without any other markup change', () => {
        const w = mountPage({ enabled: true });

        expect(w.get('[data-testid="tenancy-status"]').text()).toBe(en.tenancy.statusEnabled);
        expect(w.get('[data-testid="tenancy-banner"]').text()).toContain(en.tenancy.bannerEnabled);
    });

    it('renders the current team name, or the translated fallback', () => {
        expect(mountPage().text()).toContain(en.tenancy.noTeam);
        expect(mountPage({ currentTeam: { id: 7, name: 'Alpha' } }).text()).toContain('Alpha');
    });

    it('emits no untranslated literal — every rendered text node comes from the catalogue', () => {
        const w = mountPage();
        const catalogue = new Set(Object.values(en.tenancy as Record<string, string>));

        // Model class names, the tenant column and row counts are data, not UI copy.
        const data = new Set<string>([
            (status as any).column,
            ...(status as any).models.map((m: any) => m.class),
            ...(status as any).models.map((m: any) => String(m.nullRows)),
        ]);

        for (const text of textNodes(w.element)) {
            expect(
                catalogue.has(text) || data.has(text),
                `Untranslated literal rendered: "${text}"`,
            ).toBe(true);
        }
    });
});

function textNodes(root: Node): string[] {
    const out: string[] = [];

    const walk = (node: Node) => {
        if (node.nodeType === 3) {
            const text = (node.textContent ?? '').trim();
            if (text) out.push(text);
            return;
        }
        node.childNodes.forEach(walk);
    };

    walk(root);

    return out;
}
