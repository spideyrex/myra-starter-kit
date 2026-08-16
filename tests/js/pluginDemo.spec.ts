import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<span />' },
    Link: { name: 'Link', template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

// The layout and the syntax highlighter are not what this spec is about, and
// pulling either in drags the whole chrome (echo, sonner, codemirror) into jsdom.
vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
    default: { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' },
}));
vi.mock('@/components/admin/CodeBlock.vue', () => ({
    default: { name: 'CodeBlock', props: ['value'], template: '<pre class="stub-code" />' },
}));

(globalThis as any).route = (name: string) => `/${name.replace(/\./g, '/')}`;

import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
// THE REAL PAYLOAD: written by tests/Feature/Plugin/PluginDemoPropsTest from the
// props DemoController::plugins() actually produced. No hand-written stand-in.
import fixture from './fixtures/plugin-demo.json';
import Plugins from '@/Pages/Admin/Demo/Plugins.vue';

function mountPage(props: Record<string, unknown> = {}, locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(Plugins, {
        props: {
            plugins: fixture.plugins,
            failed: fixture.failed,
            strict: fixture.strict,
            ...props,
        } as any,
        global: { plugins: [i18n] },
    });
}

describe('plugins demo page', () => {
    it('renders one table row per plugin in the real payload', () => {
        const w = mountPage();
        const rows = w.findAll('tbody tr');

        expect(fixture.plugins.length).toBeGreaterThan(0);
        expect(rows).toHaveLength(fixture.plugins.length);
        expect(rows[0].text()).toContain(fixture.plugins[0].id);
        expect(rows[0].text()).toContain(fixture.plugins[0].class);
    });

    it('shows the surface counts the server computed, not a client-side guess', () => {
        const cells = mountPage().findAll('tbody tr td').map(c => c.text());

        expect(cells).toContain(String(fixture.plugins[0].routes));
        expect(cells).toContain(String(fixture.plugins[0].permissions));
        expect(cells).toContain(String(fixture.plugins[0].migrations));
    });

    it('renders no failed-plugin card when nothing failed', () => {
        expect(fixture.failed).toEqual([]);
        expect(mountPage().text()).not.toContain(en.plugins.failedTitle);
    });

    it('renders the failed-plugin card, with the exception message, only when something failed', () => {
        const w = mountPage({
            failed: [{ class: 'App\\Plugins\\Broken\\BrokenPlugin', message: 'Duplicate plugin id [broken].' }],
        });

        expect(w.text()).toContain(en.plugins.failedTitle);
        expect(w.text()).toContain('BrokenPlugin');
        expect(w.text()).toContain('Duplicate plugin id [broken].');
    });

    it('shows an empty state rather than a blank table when no plugin is registered', () => {
        const w = mountPage({ plugins: [] });

        expect(w.findAll('tbody tr')).toHaveLength(0);
        expect(w.text()).toContain(en.plugins.empty);
    });

    it('reflects the server-resolved failure mode', () => {
        expect(mountPage({ strict: true }).text()).toContain(en.plugins.strictOn);
        expect(mountPage({ strict: false }).text()).toContain(en.plugins.strictOff);
    });

    it('renders no raw English literal — every visible string came from t()', () => {
        const english = mountPage();
        const chinese = mountPage({}, 'zh');

        expect(english.find('h1').text()).toBe(en.plugins.title);
        expect(chinese.find('h1').text()).toBe(zh.plugins.title);

        // Copy that cannot also appear inside the payload itself.
        for (const key of ['description', 'loaded', 'back'] as const) {
            expect(english.text()).toContain(en.plugins[key]);
            expect(chinese.text()).toContain(zh.plugins[key]);
            expect(chinese.text()).not.toContain(en.plugins[key]);
        }

        expect(mountPage({}, 'ms').text()).toContain(ms.plugins.loaded);
    });
});
