import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
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
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
// The real gallery payload, written by tests/Feature/Demo/DemoRegistryTest.
import registry from './fixtures/demo-registry.json';
import PageBuilder from '@/Pages/Admin/Demo/PageBuilder.vue';
import { DEMO_ICONS } from '@/demo/registry';

const locales: Record<string, Record<string, unknown>> = { en, ms, zh };

/**
 * A stand-in for SectionRegistry::toClientSchema(). Bundle A owns the real
 * declarations; this spec only proves the GALLERY renders whatever shape the
 * server sends, which is why every assertion reads the constant rather than a
 * literal repeated in the expectation.
 */
const SCHEMA = [
    {
        key: 'hero', labelKey: 'pageBuilder.sections.hero.label',
        descriptionKey: 'pageBuilder.sections.hero.description',
        icon: 'Rocket', group: 'content', titleField: 'title', maxPerPage: 1,
        variants: { align: ['center', 'split', 'left'], compact: 'bool' },
        fields: [
            { name: 'title', type: 'text', labelKey: 'x', required: false, default: '', max: 120 },
            { name: 'cta_url', type: 'url', labelKey: 'x', required: false, default: '', max: null },
        ],
    },
    {
        key: 'testimonials', labelKey: 'pageBuilder.sections.testimonials.label',
        descriptionKey: 'pageBuilder.sections.testimonials.description',
        icon: 'Quote', group: 'proof', titleField: 'title', maxPerPage: 0,
        variants: { tinted: 'bool' },
        fields: [{ name: 'title', type: 'text', labelKey: 'x', required: false, default: '', max: 120 }],
    },
    {
        key: 'divider', labelKey: 'pageBuilder.sections.divider.label',
        descriptionKey: 'pageBuilder.sections.divider.description',
        icon: 'Minus', group: 'layout', titleField: null, maxPerPage: 0,
        variants: {},
        fields: [{ name: 'style', type: 'select', labelKey: 'x', required: false, default: 'rule', max: null, options: ['rule', 'space'] }],
    },
    {
        key: 'acme_banner', labelKey: 'acme.banner.label',
        descriptionKey: 'acme.banner.description',
        icon: 'NotAnIconThatExists', group: 'conversion', titleField: 'title', maxPerPage: 2,
        variants: {},
        fields: [],
    },
];

function messageAt(messages: Record<string, unknown>, key: string): string | null {
    let node: unknown = messages;

    for (const segment of key.split('.')) {
        if (!node || typeof node !== 'object' || !(segment in (node as Record<string, unknown>))) return null;
        node = (node as Record<string, unknown>)[segment];
    }

    return typeof node === 'string' && node !== '' ? node : null;
}

function mountPage(props: Record<string, unknown> = {}, locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(PageBuilder, {
        props: { sections: SCHEMA, settings: {}, ...props } as any,
        global: { plugins: [i18n, ziggy] },
    });
}

describe('page builder demo — registry entry', () => {
    it('is advertised by the real gallery payload', () => {
        const entry = registry.demos.find(d => d.key === 'pageBuilder');

        expect(entry, 'the gallery payload has no pageBuilder entry').toBeTruthy();
        expect(entry!.route).toBe('admin.demo.page-builder');
        expect(entry!.since).toBe('2.7.0');
    });

    it('names an icon the client actually ships', () => {
        const entry = registry.demos.find(d => d.key === 'pageBuilder')!;

        expect(Object.prototype.hasOwnProperty.call(DEMO_ICONS, entry.icon)).toBe(true);
    });

    it('resolves its gallery copy in en, ms and zh', () => {
        const entry = registry.demos.find(d => d.key === 'pageBuilder')!;

        for (const key of [entry.titleKey, entry.descriptionKey, entry.badgeKey].filter(Boolean) as string[]) {
            for (const [code, messages] of Object.entries(locales)) {
                expect(messageAt(messages, key), `[${key}] is missing from ${code}.json`).toBeTruthy();
            }
        }
    });
});

describe('page builder demo — the page', () => {
    it('renders one catalogue row per section the server sent', async () => {
        const w = mountPage();
        await flushPromises();

        expect(w.findAll('li').length).toBe(SCHEMA.length);
    });

    it('groups the catalogue and labels every group', async () => {
        const w = mountPage();
        await flushPromises();

        const text = w.text();

        for (const group of new Set(SCHEMA.map(s => s.group))) {
            expect(text).toContain((en.pageBuilder.demo.groups as Record<string, string>)[group]);
        }
    });

    it('falls back to the type key rather than a raw i18n path for a section with no copy', async () => {
        const w = mountPage();
        await flushPromises();

        expect(w.text()).toContain('acme_banner');
        expect(w.text()).not.toContain('acme.banner.label');
    });

    it('shows an empty state instead of a blank card when nothing is registered', async () => {
        const w = mountPage({ sections: [] });
        await flushPromises();

        expect(w.text()).toContain(en.pageBuilder.demo.catalogue.empty);
        expect(w.findAll('li')).toHaveLength(0);
    });

    it('says so rather than blanking when the public renderer is absent', async () => {
        const w = mountPage();
        await flushPromises();

        // The renderer ships with the model bundle and is resolved at runtime;
        // whichever way it resolves, the pane must say something.
        const text = w.text();
        const resolved = w.findAll('section').length > 0;

        expect(resolved || text.includes(en.pageBuilder.demo.preview.unavailable)).toBe(true);
        expect(text.trim()).not.toBe('');
    });

    it('offers a route to the builder even before the editor bundle registers one', async () => {
        const w = mountPage();
        await flushPromises();

        const link = w.findAll('a').find(a => a.text().includes(en.pageBuilder.demo.openBuilder));

        expect(link).toBeTruthy();
        expect(link!.attributes('href')).toBe('/dashboard/landing/builder');
    });

    it('ships no hardcoded English — every visible string came from t()', async () => {
        const english = mountPage();
        const chinese = mountPage({}, 'zh');
        const malay = mountPage({}, 'ms');
        await flushPromises();

        expect(english.text()).toContain(en.pageBuilder.demo.readOnly);
        expect(chinese.text()).toContain(zh.pageBuilder.demo.readOnly);
        expect(malay.text()).toContain(ms.pageBuilder.demo.readOnly);

        expect(chinese.text()).not.toContain(en.pageBuilder.demo.readOnly);
        expect(chinese.text()).not.toContain(en.pageBuilder.demo.catalogue.description);
        expect(malay.text()).not.toContain(en.pageBuilder.demo.catalogue.description);
    });

    it('never renders a raw pageBuilder i18n path', async () => {
        for (const locale of Object.keys(locales)) {
            const w = mountPage({}, locale);
            await flushPromises();

            expect(w.text(), `${locale} leaked an unresolved key`).not.toMatch(/pageBuilder\.demo\./);
        }
    });
});
