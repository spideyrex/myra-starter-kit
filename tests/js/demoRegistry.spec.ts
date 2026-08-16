import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<span />' },
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
// THE REAL PAYLOAD: written by tests/Feature/Demo/DemoRegistryTest from the
// props DemoController::index() actually produced.
import fixture from './fixtures/demo-registry.json';
import Index from '@/Pages/Admin/Demo/Index.vue';
import { DEMO_ICONS, resolveDemoIcon } from '@/demo/registry';

function mountPage(props: Record<string, unknown> = {}, locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(Index, {
        props: { demos: fixture.demos, ...props } as any,
        global: { plugins: [i18n, ziggy] },
    });
}

describe('feature gallery', () => {
    it('renders one card per entry in the real payload', () => {
        const w = mountPage();

        expect(fixture.demos.length).toBeGreaterThan(20);
        expect(w.findAll('[data-slot="card"]')).toHaveLength(fixture.demos.length);
    });

    it('resolves an icon for every entry the server shipped', () => {
        for (const demo of fixture.demos) {
            expect(
                Object.prototype.hasOwnProperty.call(DEMO_ICONS, demo.icon),
                `icon [${demo.icon}] for [${demo.key}] is not in DEMO_ICONS`,
            ).toBe(true);
        }
    });

    it('falls back to a placeholder rather than a blank slot for an unknown icon', () => {
        expect(resolveDemoIcon('NotAnIcon')).toBe(DEMO_ICONS.LayoutGrid);
    });

    it('links every card at the route name the server declared', () => {
        const hrefs = mountPage().findAll('a').map(a => a.attributes('href'));

        for (const demo of fixture.demos) {
            expect(hrefs).toContain(route(demo.route));
        }
    });

    it('filters as you type and marks the matched run', async () => {
        const w = mountPage();
        const input = w.find('#gallery-search');

        await input.setValue('playground');

        const cards = w.findAll('[data-slot="card"]');
        expect(cards.length).toBeGreaterThan(0);
        expect(cards.length).toBeLessThan(fixture.demos.length);
        expect(w.find('mark').exists()).toBe(true);
    });

    it('shows an empty state rather than a blank grid when nothing matches', async () => {
        const w = mountPage();

        await w.find('#gallery-search').setValue('zzzzzznotathing');

        expect(w.findAll('[data-slot="card"]')).toHaveLength(0);
        expect(w.text()).toContain(en.gallery.noResults);
    });

    it('filters by tag and restores on a second click', async () => {
        const w = mountPage();
        const tagButtons = w.findAll('[aria-pressed]');
        const platform = tagButtons.find(b => b.text().startsWith(en.gallery.tags.platform));

        expect(platform).toBeTruthy();

        await platform!.trigger('click');
        const filtered = w.findAll('[data-slot="card"]').length;
        expect(filtered).toBeLessThan(fixture.demos.length);

        await platform!.trigger('click');
        expect(w.findAll('[data-slot="card"]')).toHaveLength(fixture.demos.length);
    });

    it('renders the fallback list rather than nothing when the registry failed to seed', () => {
        const w = mountPage({ demos: [] });

        expect(w.findAll('[data-slot="card"]').length).toBeGreaterThan(0);
        expect(w.text()).toContain(en.gallery.demos.formBuilder.title);
    });

    it('ships no hardcoded English — every visible string came from t()', () => {
        const english = mountPage();
        const chinese = mountPage({}, 'zh');

        expect(english.find('h1').text()).toBe(en.gallery.title);
        expect(chinese.find('h1').text()).toBe(zh.gallery.title);
        expect(chinese.text()).toContain(zh.gallery.viewDemo);
        expect(chinese.text()).not.toContain(en.gallery.subtitle);
        expect(mountPage({}, 'ms').text()).toContain(ms.gallery.viewDemo);
    });

    it('has a polite result announcer', () => {
        const status = mountPage().find('[role="status"]');

        expect(status.exists()).toBe(true);
        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.attributes('aria-atomic')).toBe('true');
    });
});
