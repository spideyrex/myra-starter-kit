import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';

const submitted: Array<Record<string, unknown>> = [];

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (data: Record<string, unknown>) => {
        const form = reactive({
            ...data,
            processing: false,
            put: () => submitted.push({ ...form }),
        });

        return form;
    },
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
    default: { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' },
}));

const route = (name: string) => `/${name.replace(/\./g, '/')}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import fixture from './fixtures/component-demos.json';
import Landing from '@/Pages/Admin/Landing/Index.vue';
import { auditA11y } from './helpers/a11yBaseline';

const SECTIONS = ['hero', 'features', 'testimonials', 'pricing', 'cta'];

function mountLanding(overrides: Record<string, unknown> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(Landing, {
        props: {
            templates: fixture.landingTemplates.templates,
            current: 'classic',
            sectionOrder: [...SECTIONS],
            sections: SECTIONS,
            sectionsEnabled: { hero: true, features: true, testimonials: true, pricing: true, cta: true },
            ...overrides,
        } as any,
        attachTo: document.body,
        global: { plugins: [i18n, ziggy] },
    });
}

function labels(w: ReturnType<typeof mountLanding>): string[] {
    return w.findAll('li p.text-sm.font-medium').map(p => p.text());
}

describe('section order control', () => {
    it('is keyboard operable — every move is a named button, never drag-only', async () => {
        const w = mountLanding();
        await flushPromises();

        const up = w.findAll('button').filter(b => (b.attributes('aria-label') ?? '').startsWith('Move ') && b.attributes('aria-label')!.endsWith(' up'));
        const down = w.findAll('button').filter(b => (b.attributes('aria-label') ?? '').endsWith(' down'));

        expect(up).toHaveLength(SECTIONS.length);
        expect(down).toHaveLength(SECTIONS.length);
        expect(up[0].attributes('disabled')).toBeDefined();
        expect(down.at(-1)!.attributes('disabled')).toBeDefined();
    });

    it('reorders and announces the move politely', async () => {
        const w = mountLanding();
        await flushPromises();

        const before = labels(w);
        const down = w.findAll('button').filter(b => (b.attributes('aria-label') ?? '').endsWith(' down'));

        await down[0].trigger('click');
        await flushPromises();

        const after = labels(w);

        expect(after[0]).toBe(before[1]);
        expect(after[1]).toBe(before[0]);

        const status = w.find('[role="status"]');

        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.text()).toContain(before[0]);
        expect(status.text()).not.toBe('');
    });

    it('submits the reordered list, not the original one', async () => {
        submitted.length = 0;

        const w = mountLanding();
        await flushPromises();

        const down = w.findAll('button').filter(b => (b.attributes('aria-label') ?? '').endsWith(' down'));
        await down[0].trigger('click');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(submitted).toHaveLength(1);
        expect(submitted[0].section_order).toEqual(['features', 'hero', 'testimonials', 'pricing', 'cta']);
    });

    it('greys out a section the chosen template does not render', async () => {
        const w = mountLanding({ current: 'minimal' });
        await flushPromises();

        // Minimal renders hero, features and cta only.
        const rows = w.findAll('li[aria-disabled="true"]');

        expect(rows.length).toBe(2);
        expect(rows[0].text()).toContain(en.landing.unsupported.help);
    });

    it('says so when a supported section is switched off in the settings', async () => {
        const w = mountLanding({
            sectionsEnabled: { hero: true, features: false, testimonials: true, pricing: true, cta: true },
        });
        await flushPromises();

        expect(w.text()).toContain(en.landing.unsupported.disabled);
    });

    it('offers a preview that opens in a new tab for the picked template', async () => {
        const w = mountLanding();
        await flushPromises();

        const preview = w.findAll('a').find(a => a.text() === en.landing.preview.openInNewTab);

        expect(preview).toBeTruthy();
        expect(preview!.attributes('href')).toBe('/?template=classic');
        expect(preview!.attributes('target')).toBe('_blank');
        expect(preview!.attributes('rel')).toContain('noopener');
    });

    it('clears the R1–R5 baseline', async () => {
        const w = mountLanding();
        await flushPromises();

        const findings = auditA11y(w.element as Element);

        expect(findings, findings.map(f => `${f.rule}: ${f.detail}`).join('\n')).toEqual([]);
    });
});
