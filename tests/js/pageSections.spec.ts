import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: null } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/composables/useThemeColors', () => ({ useThemeColors: () => ({}) }));

/**
 * A deliberately throwing section, registered alongside the real ones. It only
 * appears when a block asks for type `boom`, so every other test sees the real
 * registry.
 */
vi.mock('@/Pages/Public/Templates/_shared/sectionRegistry', async importOriginal => {
    const actual = await importOriginal<
        typeof import('@/Pages/Public/Templates/_shared/sectionRegistry')
    >();

    const sectionComponents = {
        ...actual.sectionComponents,
        boom: {
            name: 'BoomSection',
            setup() {
                throw new Error('this section is broken');
            },
        },
    };

    return {
        sectionComponents,
        hasSectionComponent: (type: unknown) =>
            typeof type === 'string' && Object.prototype.hasOwnProperty.call(sectionComponents, type),
    };
});

import en from '@/i18n/locales/en.json';
import Home from '@/Pages/Home.vue';
import fixture from './fixtures/homepage-settings.json';

// The dispatcher lazy-loads each template; warm every chunk once.
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

const settings = fixture.settings as Record<string, any>;

/**
 * The same conversion LegacyHomepageBlocks does server-side, driven by the
 * COMMITTED SERVER FIXTURE rather than hand-typed copy.
 */
function blocksFromFixture() {
    return [
        {
            id: 'b-hero',
            type: 'hero',
            variant: {},
            data: {
                title: settings.hero_title,
                subtitle: settings.hero_subtitle,
                cta_text: settings.hero_cta_text,
                cta_url: settings.hero_cta_url,
                image_path: '',
                image_url: null,
            },
        },
        {
            id: 'b-features',
            type: 'features',
            variant: {},
            data: {
                title: settings.features_title,
                subtitle: settings.features_subtitle,
                items: settings.features,
            },
        },
        {
            id: 'b-testimonials',
            type: 'testimonials',
            variant: {},
            data: {
                title: settings.testimonials_title,
                subtitle: settings.testimonials_subtitle,
                items: settings.testimonials,
            },
        },
        {
            id: 'b-pricing',
            type: 'pricing',
            variant: {},
            data: {
                title: settings.pricing_title,
                subtitle: settings.pricing_subtitle,
                plans: settings.pricing_plans,
            },
        },
        {
            id: 'b-cta',
            type: 'cta',
            variant: {},
            data: {
                title: settings.cta_title,
                subtitle: settings.cta_subtitle,
                button_text: settings.cta_button_text,
                button_url: settings.cta_button_url,
            },
        },
    ];
}

describe('page sections — the block render path', () => {
    it('renders the converted page exactly the way the flat settings did', async () => {
        const legacy = mountHome({ template: 'classic', sectionOrder: fixture.sectionOrder });
        await settle(legacy);

        const built = mountHome({
            template: 'classic',
            sectionOrder: fixture.sectionOrder,
            blocks: blocksFromFixture(),
        });
        await settle(built);

        expect(built.text()).toBe(legacy.text());
    });

    it('falls through to the legacy renderer when the block list is empty', async () => {
        const w = mountHome({ template: 'classic', blocks: [] });
        await settle(w);

        expect(w.text()).toContain(settings.hero_title);
        expect(w.text()).toContain(settings.pricing_title);
    });

    it('renders a block list in the authored order, ignoring the section order', async () => {
        const blocks = blocksFromFixture();
        const w = mountHome({
            template: 'classic',
            sectionOrder: ['hero', 'features', 'testimonials', 'pricing', 'cta'],
            blocks: [blocks[4], blocks[0]],
        });
        await settle(w);

        const html = w.html();

        expect(html.indexOf(settings.cta_title)).toBeGreaterThan(-1);
        expect(html.indexOf(settings.cta_title)).toBeLessThan(html.indexOf(settings.hero_title));
        expect(html).not.toContain(settings.pricing_title);
    });

    it('renders repeats of the same section type', async () => {
        const [hero] = blocksFromFixture();
        const w = mountHome({
            template: 'classic',
            blocks: [
                hero,
                { ...hero, id: 'b-hero-2', data: { ...hero.data, title: 'A second hero' } },
            ],
        });
        await settle(w);

        expect(w.findAll('h1')).toHaveLength(2);
        expect(w.text()).toContain('A second hero');
    });

    it('skips an unknown type without disturbing its neighbours', async () => {
        const blocks = blocksFromFixture();
        const w = mountHome({
            template: 'classic',
            blocks: [blocks[0], { id: 'x', type: 'not_a_real_section', variant: {}, data: {} }, blocks[4]],
        });
        await settle(w);

        expect(w.text()).toContain(settings.hero_title);
        expect(w.text()).toContain(settings.cta_title);
        expect(w.text()).not.toBe('');
    });

    it('renders a row whose data is missing every field', async () => {
        const w = mountHome({
            template: 'classic',
            blocks: [
                { id: 'a', type: 'hero', variant: {}, data: {} },
                { id: 'b', type: 'features', variant: {}, data: {} },
                { id: 'c', type: 'pricing', variant: {}, data: {} },
                { id: 'd', type: 'testimonials', variant: {}, data: {} },
                { id: 'e', type: 'cta', variant: {}, data: {} },
            ],
        });
        await settle(w);

        expect(w.findAll('section').length).toBeGreaterThanOrEqual(5);
        expect(w.text()).toContain('Acme Corp');
    });

    it('survives a row whose data and variant are the wrong type entirely', async () => {
        const w = mountHome({
            template: 'classic',
            blocks: [
                { id: 'a', type: 'hero', variant: 'center', data: 'nope' },
                { id: 'b', type: 'pricing', variant: {}, data: { plans: 'not a list' } },
            ],
        });
        await settle(w);

        expect(w.html()).not.toBe('');
        expect(w.text()).toContain('Acme Corp');
    });

    it('uses the gradient branch when the hero image is missing', async () => {
        const [hero] = blocksFromFixture();
        const w = mountHome({
            template: 'classic',
            blocks: [{ ...hero, data: { ...hero.data, image_url: null } }],
        });
        await settle(w);

        expect(w.html()).toContain('bg-gradient-to-br');
        expect(w.html()).not.toContain('background-image');
    });

    it('lets a block variant override the template variant', async () => {
        const [hero] = blocksFromFixture();
        const w = mountHome({
            template: 'editorial',
            blocks: [{ ...hero, variant: { align: 'center' } }],
        });
        await settle(w);

        // Editorial declares align: 'split', which renders the decorative panel.
        expect(w.html()).not.toContain('aspect-[4/3]');
    });

    it('contains a throwing section instead of blanking the page', async () => {
        const blocks = blocksFromFixture();
        const w = mountHome({
            template: 'classic',
            blocks: [blocks[0], { id: 'boom', type: 'boom', variant: {}, data: {} }, blocks[4]],
        });
        await settle(w);

        expect(w.text()).toContain(settings.hero_title);
        expect(w.text()).toContain(settings.cta_title);
        expect(w.text()).toContain('Acme Corp');
    });

    it('falls back to the legacy path when no block type can be rendered', async () => {
        const w = mountHome({
            template: 'classic',
            sectionOrder: fixture.sectionOrder,
            blocks: [{ id: 'x', type: 'from_a_package_that_is_gone', variant: {}, data: {} }],
        });
        await settle(w);

        expect(w.text()).toContain(settings.hero_title);
    });

    // Minimal declares supports('hero', 'features', 'cta').
    it('does not render a legacy section the active template does not support', async () => {
        const blocks = blocksFromFixture();
        const w = mountHome({ template: 'minimal', blocks });
        await settle(w);

        expect(w.text()).toContain(settings.hero_title);
        expect(w.text()).toContain(settings.features_title);
        expect(w.text()).not.toContain(settings.pricing_title);
        expect(w.text()).not.toContain(settings.testimonials_title);
    });

    it('never hides a package-contributed type behind a template that predates it', async () => {
        const [hero] = blocksFromFixture();
        const w = mountHome({
            template: 'minimal',
            blocks: [hero, { id: 'boom', type: 'boom', variant: {}, data: {} }],
        });
        await settle(w);

        // `boom` is not one of the legacy five, so `supports` must not filter it;
        // SectionBoundary is what keeps it from taking the page down.
        expect(w.text()).toContain(settings.hero_title);
        expect(w.text()).toContain('Acme Corp');
    });

    it('falls back to the legacy renderer when the template supports none of the blocks', async () => {
        const blocks = blocksFromFixture();
        const w = mountHome({
            template: 'minimal',
            sectionOrder: fixture.sectionOrder,
            blocks: [blocks[2], blocks[3]],
        });
        await settle(w);

        expect(w.text()).not.toBe('');
        expect(w.text()).toContain(settings.hero_title);
    });
});
