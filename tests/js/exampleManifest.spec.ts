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

const route = (name: string, param?: string) =>
    `/${name.replace(/\./g, '/')}${param ? `/${param}` : ''}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
// THE REAL PAYLOAD: written by tests/Feature/Examples/ExampleRegistryTest from
// the props ExampleController::index() actually produced.
import fixture from './fixtures/examples-index.json';
import manifest from '../../scripts/shadcn/examples-manifest.json';
import { EXAMPLE_IDS, EXAMPLE_SHELLS } from '@/examples/index';
import Index from '@/Pages/Admin/Examples/Index.vue';

function mountPage(props: Record<string, unknown> = {}, locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(Index, {
        props: { examples: fixture.examples, ...props } as any,
        global: { plugins: [i18n, ziggy] },
    });
}

describe('example catalogue', () => {
    it('renders one card per entry in the real payload', () => {
        const w = mountPage();

        expect(fixture.examples.length).toBeGreaterThan(1);
        expect(w.findAll('[data-slot="card"]')).toHaveLength(fixture.examples.length);
    });

    it('links every card at the show route the server declared', () => {
        const hrefs = mountPage().findAll('a').map(a => a.attributes('href'));

        for (const example of fixture.examples) {
            expect(hrefs).toContain(route('admin.examples.show', example.key));
        }
    });

    it('names every entry through i18n in all three locales', () => {
        const english = mountPage();
        const chinese = mountPage({}, 'zh');

        expect(english.find('h1').text()).toBe(en.examples.title);
        expect(chinese.find('h1').text()).toBe(zh.examples.title);
        expect(mountPage({}, 'ms').text()).toContain(ms.examples.view);

        for (const example of fixture.examples) {
            expect(english.text()).toContain((en.examples.entries as any)[example.key].title);
        }
    });

    it('says WHY a quarantined example cannot be previewed rather than hiding it', () => {
        const quarantined = fixture.examples.filter(e => !e.available);

        expect(quarantined.length).toBeGreaterThan(0);

        const text = mountPage().text();

        for (const example of quarantined) {
            expect(example.unavailableReason).toBeTruthy();
            expect(text).toContain((en.examples.entries as any)[example.key].title);
        }
    });

    it('filters as you type and marks the matched run', async () => {
        const w = mountPage();

        await w.find('#examples-search').setValue('mail');

        const cards = w.findAll('[data-slot="card"]');
        expect(cards.length).toBeGreaterThan(0);
        expect(cards.length).toBeLessThan(fixture.examples.length);
        expect(w.find('mark').exists()).toBe(true);
    });

    it('shows an empty state rather than a blank grid when nothing matches', async () => {
        const w = mountPage();

        await w.find('#examples-search').setValue('zzzzzznotathing');

        expect(w.findAll('[data-slot="card"]')).toHaveLength(0);
        expect(w.text()).toContain(en.examples.noResults);
    });

    it('has a polite result announcer', () => {
        const status = mountPage().find('[role="status"]');

        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.attributes('aria-atomic')).toBe('true');
    });
});

describe('generated example index', () => {
    it('lists exactly the available manifest ids', () => {
        const available = Object.entries(manifest.examples)
            .filter(([, row]) => (row as any).available)
            .map(([id]) => id);

        expect([...EXAMPLE_IDS].sort()).toEqual(available.sort());
        expect(Object.keys(EXAMPLE_SHELLS).sort()).toEqual(available.sort());
    });

    it('points every shell at a file the preview glob can resolve', () => {
        const shells = import.meta.glob('@/examples/**/*.vue');
        const keys = Object.keys(shells);

        for (const id of EXAMPLE_IDS) {
            expect(keys.some(key => key.endsWith(`/examples/${EXAMPLE_SHELLS[id]}`))).toBe(true);
        }
    });

    it('agrees with the payload the server shipped', () => {
        const serverAvailable = fixture.examples.filter(e => e.available).map(e => e.key).sort();

        expect([...EXAMPLE_IDS].sort()).toEqual(serverAvailable);
    });
});
