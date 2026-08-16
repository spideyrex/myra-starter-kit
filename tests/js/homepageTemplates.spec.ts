import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: null } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

// The theme composable installs a MutationObserver on documentElement; the
// templates are what this spec is about, not the colour engine.
vi.mock('@/composables/useThemeColors', () => ({ useThemeColors: () => ({}) }));

import en from '@/i18n/locales/en.json';
import Home from '@/Pages/Home.vue';
import fixture from './fixtures/homepage-settings.json';

const TEMPLATE_DIR = resolve(__dirname, '../../resources/js/Pages/Public/Templates');

function templateFiles(): string[] {
    return readdirSync(TEMPLATE_DIR).filter(name => name.endsWith('.vue'));
}

// The dispatcher lazy-loads each template. Warm every chunk once so the async
// wrapper resolves in a microtask instead of racing a module transform.
await Promise.all(
    Object.values(import.meta.glob('@/Pages/Public/Templates/*.vue')).map(load => load()),
);

async function settle(w: { html: () => string }): Promise<void> {
    for (let i = 0; i < 50 && w.html().trim() === ''; i++) {
        await flushPromises();
    }

    await flushPromises();
}

function mountHome(props: Record<string, unknown> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(Home, {
        props: { settings: fixture.settings, authenticated: false, ...props } as any,
        global: { plugins: [i18n] },
    });
}

describe('landing templates — structure', () => {
    it('ships more than one template', () => {
        expect(templateFiles().length).toBeGreaterThan(1);
    });

    it('routes every template through the shared navbar and footer', () => {
        for (const file of templateFiles()) {
            const source = readFileSync(resolve(TEMPLATE_DIR, file), 'utf8');

            expect(source, `${file} must import _shared/SiteNavbar.vue`).toContain('./_shared/SiteNavbar.vue');
            expect(source, `${file} must import _shared/SiteFooter.vue`).toContain('./_shared/SiteFooter.vue');
        }
    });

    it('gives every template a single main landmark with the skip-link target', () => {
        for (const file of templateFiles()) {
            const source = readFileSync(resolve(TEMPLATE_DIR, file), 'utf8');
            const mains = source.match(/<main\b/g) ?? [];

            expect(mains, `${file} must have exactly one <main>`).toHaveLength(1);
            expect(source, `${file} must anchor the skip link`).toContain('id="content"');
        }
    });

    it('hardcodes no site name or brand colour in any template', () => {
        for (const file of templateFiles()) {
            const source = readFileSync(resolve(TEMPLATE_DIR, file), 'utf8');

            expect(source, `${file} must resolve the brand, not hardcode it`).not.toMatch(/charAt\(0\)/);
            expect(source, `${file} must use theme tokens, not literal hex`).not.toMatch(/#[0-9a-fA-F]{6}/);
        }
    });
});

describe('landing templates — dispatcher', () => {
    it('renders the requested template', async () => {
        const w = mountHome({ template: 'minimal' });
        await settle(w);

        expect(w.html()).toContain(fixture.settings.hero_title);
        // Minimal drops the pricing wall entirely.
        expect(w.html()).not.toContain(fixture.settings.pricing_title);
    });

    it('falls back to Classic for an unknown key rather than rendering blank', async () => {
        const w = mountHome({ template: 'not-a-template' });
        await settle(w);

        expect(w.html()).toContain(fixture.settings.hero_title);
        expect(w.html()).toContain(fixture.settings.pricing_title);
    });

    it('falls back to Classic when no template prop is sent at all', async () => {
        const w = mountHome();
        await settle(w);

        expect(w.html()).toContain(fixture.settings.features_title);
        expect(w.html()).toContain(fixture.settings.cta_title);
    });

    it('resolves the lower-case registry key onto the upstream-cased SaaS.vue', async () => {
        const w = mountHome({ template: 'saas' });
        await settle(w);

        expect(w.html()).toContain(en.landing.faq.title);
    });

    it('honours the server-resolved section order', async () => {
        const w = mountHome({ template: 'classic', sectionOrder: ['cta', 'hero'] });
        await settle(w);

        const html = w.html();

        expect(html.indexOf(fixture.settings.cta_title)).toBeLessThan(html.indexOf(fixture.settings.hero_title));
        expect(html).not.toContain(fixture.settings.pricing_title);
    });

    it('renders the brand name from the shared props, never a literal', async () => {
        const w = mountHome();
        await settle(w);

        expect(w.text()).toContain('Acme Corp');
    });

    it('exposes a skip link as the first focusable element', async () => {
        const w = mountHome();
        await settle(w);

        const first = w.find('a');

        expect(first.attributes('href')).toBe('#content');
        expect(first.text()).toBe(en.landing.nav.skipToContent);
    });
});
