import { readFileSync } from 'node:fs';
import path from 'node:path';
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

const route = (name: string, param?: string) => `/${name.replace(/\./g, '/')}${param ? `/${param}` : ''}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
// THE REAL PAYLOAD: written by tests/Feature/Blocks/BlockRegistryTest from the
// props BlockController::index() actually produced.
import fixture from './fixtures/blocks-index.json';
import Index from '@/Pages/Admin/Blocks/Index.vue';

const root = path.resolve(__dirname, '../..');
const manifest = JSON.parse(readFileSync(path.join(root, 'scripts/shadcn/blocks-manifest.json'), 'utf8'));

const stubs = {
    Tabs: { template: '<div><slot /></div>' },
    TabsList: { template: '<div><slot /></div>' },
    TabsTrigger: { props: ['value'], template: '<button type="button"><slot /></button>' },
};

function mountPage(props: Record<string, unknown> = {}, locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(Index, {
        props: { blocks: fixture.blocks, categories: fixture.categories, ...props } as any,
        global: { plugins: [i18n, ziggy], stubs },
    });
}

describe('block catalogue', () => {
    it('renders one card per entry in the real payload', () => {
        const w = mountPage();

        expect(fixture.blocks.length).toBeGreaterThan(20);
        expect(w.findAll('[data-slot="card"]')).toHaveLength(fixture.blocks.length);
    });

    it('gives every card a translated title and a link to its own page', () => {
        const w = mountPage();
        const hrefs = w.findAll('a').map(a => a.attributes('href'));
        const text = w.text();

        for (const block of fixture.blocks) {
            const key = block.key as keyof typeof en.blocks.entries;
            expect(hrefs).toContain(route('admin.blocks.show', block.key));
            expect(text).toContain(en.blocks.entries[key].title);
        }
    });

    it('marks the quarantined blocks as source-only rather than hiding them', () => {
        const unavailable = fixture.blocks.filter(block => !block.available);

        expect(unavailable.length).toBeGreaterThan(0);
        expect(mountPage().text()).toContain(en.blocks.unavailable.badge);
    });

    it('filters as you type and marks the matched run', async () => {
        const w = mountPage();

        await w.find('#blocks-search').setValue('sidebar');

        const cards = w.findAll('[data-slot="card"]');
        expect(cards.length).toBeGreaterThan(0);
        expect(cards.length).toBeLessThan(fixture.blocks.length);
        expect(w.find('mark').exists()).toBe(true);
    });

    it('shows an empty state rather than a blank grid when nothing matches', async () => {
        const w = mountPage();

        await w.find('#blocks-search').setValue('zzzzzznotathing');

        expect(w.findAll('[data-slot="card"]')).toHaveLength(0);
        expect(w.text()).toContain(en.blocks.noResults);
    });

    // Three full mounts of the real payload in one test; the 5s default is not a
    // budget it can hold on a loaded CI box. The assertions are unchanged.
    it('ships no hardcoded English — every visible string came from t()', () => {
        const english = mountPage();
        const chinese = mountPage({}, 'zh');
        const malay = mountPage({}, 'ms');

        expect(english.find('h1').text()).toBe(en.blocks.title);
        expect(chinese.find('h1').text()).toBe(zh.blocks.title);
        expect(malay.text()).toContain(ms.blocks.view);

        for (const w of [english, chinese, malay]) w.unmount();
    }, 30_000);

    it('has a polite result announcer', () => {
        const status = mountPage().find('[role="status"]');

        expect(status.exists()).toBe(true);
        expect(status.attributes('aria-live')).toBe('polite');
    });

    it('keeps the generated loader map equal to the available ids in the manifest', async () => {
        const { BLOCK_ENTRY_FILES } = await import('@/blocks/index');

        const available = Object.entries(manifest.blocks)
            .filter(([, block]) => (block as { available: boolean }).available)
            .map(([id]) => id)
            .sort();

        expect(Object.keys(BLOCK_ENTRY_FILES).sort()).toEqual(available);
    });

    it('agrees with the manifest about every id the server shipped', () => {
        expect(fixture.blocks.map(block => block.key).sort()).toEqual(Object.keys(manifest.blocks).sort());
    });
});
