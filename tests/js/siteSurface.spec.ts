import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: null } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

import SiteSurface from '@/Pages/Public/Templates/_shared/SiteSurface.vue';

/** The payload HomepageController emits, with only the fields under test moved. */
function payload(over: Record<string, unknown> = {}) {
    return {
        type: 'none',
        recipe: null,
        scrim: 'medium',
        image_url: null,
        base: '',
        foreground: '',
        contrast: 0,
        css_vars: {},
        ...over,
    };
}

function paintedVars(over: Record<string, string> = {}) {
    return {
        '--myra-page-bg': 'oklch(0.21 0.03 264)',
        '--myra-page-fg': 'oklch(1 0 0)',
        ...over,
    };
}

function mountSurface(surface: unknown, extra: Record<string, unknown> = {}) {
    return mount(SiteSurface, {
        props: { surface, ...extra },
        slots: { default: '<p class="body">content</p>' },
    });
}

describe('SiteSurface — the default is a no-op', () => {
    it('renders exactly one div with exactly the three original classes', () => {
        const w = mountSurface(payload());
        const root = w.element as HTMLElement;

        expect(root.tagName).toBe('DIV');
        expect(root.getAttribute('class')).toBe('min-h-screen bg-background text-foreground');
        expect(root.getAttribute('style')).toBeNull();
        expect(root.children).toHaveLength(1);
        expect(root.children[0].tagName).toBe('P');
    });

    it('treats a missing, malformed or unknown surface as none', () => {
        for (const junk of [undefined, null, 'gradient', 42, [], { type: 'video' }, { type: null }]) {
            const w = mountSurface(junk);

            expect((w.element as HTMLElement).getAttribute('class')).toBe(
                'min-h-screen bg-background text-foreground',
            );
        }
    });

    it('never asks the navbar to go translucent while there is no surface', () => {
        const w = mount(SiteSurface, {
            props: { surface: payload(), navbarTranslucent: true },
            slots: { default: '<i>{{ params.translucent }}</i>' },
        });

        expect(w.html()).toContain('<i>false</i>');
    });
});

describe('SiteSurface — a painted surface', () => {
    it('paints the base colour and the measured foreground from the server vars', () => {
        const w = mountSurface(payload({ type: 'solid', css_vars: paintedVars() }));
        const style = (w.element as HTMLElement).getAttribute('style') ?? '';

        expect(style).toContain('--myra-page-bg: oklch(0.21 0.03 264)');
        expect(style).toContain('background-color: var(--myra-page-bg)');
        expect(style).toContain('color: var(--myra-page-fg)');
        expect((w.element as HTMLElement).className).toContain('min-h-screen');
        expect((w.element as HTMLElement).className).not.toContain('bg-background');
    });

    it('keeps the theme tokens, and paints no media, when the server sent no base colour', () => {
        const w = mountSurface(
            payload({ type: 'image', image_url: '/storage/backgrounds/hero.jpg', css_vars: {} }),
        );

        expect((w.element as HTMLElement).className).toContain('bg-background');
        expect((w.element as HTMLElement).getAttribute('style') ?? '').not.toContain('background-color');
        expect(w.find('img').exists()).toBe(false);
    });

    it('renders the recipe layer only from the server-emitted custom property', () => {
        const w = mountSurface(
            payload({
                type: 'pattern',
                recipe: 'dots',
                css_vars: paintedVars({
                    '--myra-page-image': 'radial-gradient(currentColor 1px,transparent 1px)',
                    '--myra-page-image-size': '16px 16px',
                }),
            }),
        );

        const html = w.html();

        // The layer references the property; it never interpolates a value.
        expect(html).toContain('var(--myra-page-image)');
        expect(html).toContain('var(--myra-page-image-size, auto)');
        expect(html).toContain('opacity-[0.12]');
    });

    it('marks the image decorative and the scrim hidden from assistive tech', () => {
        const w = mountSurface(
            payload({
                type: 'image',
                image_url: '/storage/backgrounds/hero.jpg',
                scrim: 'strong',
                css_vars: paintedVars({ '--myra-page-scrim': '0.7' }),
            }),
        );

        const img = w.find('img');

        expect(img.exists()).toBe(true);
        expect(img.attributes('alt')).toBe('');
        expect(img.attributes('src')).toBe('/storage/backgrounds/hero.jpg');
        expect(w.find('[aria-hidden="true"]').exists()).toBe(true);
        expect(w.html()).toContain('opacity: 0.7');
    });

    it('hides a background image that fails to load while the base colour survives', async () => {
        const w = mountSurface(
            payload({
                type: 'image',
                image_url: '/storage/backgrounds/deleted.jpg',
                css_vars: paintedVars(),
            }),
        );

        await w.find('img').trigger('error');

        expect(w.find('img').exists()).toBe(false);
        expect((w.element as HTMLElement).getAttribute('style') ?? '').toContain(
            'background-color: var(--myra-page-bg)',
        );
        expect(w.text()).toContain('content');
    });

    it('renders the slot content above the decorative layer, and only once', () => {
        const w = mountSurface(payload({ type: 'gradient', recipe: 'dusk', css_vars: paintedVars() }));

        expect(w.findAll('.body')).toHaveLength(1);
        expect(w.html()).toContain('pointer-events-none absolute inset-0 overflow-hidden');
    });

    it('hands the navbar the translucent flag only once a surface is behind it', () => {
        const w = mount(SiteSurface, {
            props: {
                surface: payload({ type: 'solid', css_vars: paintedVars() }),
                navbarTranslucent: true,
            },
            slots: { default: '<i>{{ params.translucent }}-{{ params.active }}</i>' },
        });

        expect(w.html()).toContain('true-true');
    });
});

describe('every landing template routes through the surface', () => {
    const TEMPLATE_DIR = resolve(__dirname, '../../resources/js/Pages/Public/Templates');

    const templates = readdirSync(TEMPLATE_DIR).filter(name => name.endsWith('.vue'));

    it('ships the six templates and wraps every one of them', () => {
        expect(templates.length).toBeGreaterThanOrEqual(6);

        for (const file of templates) {
            const source = readFileSync(resolve(TEMPLATE_DIR, file), 'utf8');

            expect(source, `${file} must import _shared/SiteSurface.vue`).toContain('./_shared/SiteSurface.vue');
            expect(source, `${file} must wrap its chrome in SiteSurface`).toContain('<SiteSurface');
            expect(source, `${file} must hand the navbar the translucent flag`).toContain(':translucent="translucent"');
        }
    });

    it('leaves no template painting its own root background', () => {
        for (const file of templates) {
            const source = readFileSync(resolve(TEMPLATE_DIR, file), 'utf8');

            expect(source, `${file} must let SiteSurface own the root`).not.toContain(
                '<div class="min-h-screen bg-background text-foreground">',
            );
            expect(source, `${file} must be able to grow and scroll`).not.toMatch(/class="[^"]*\bh-screen\b/);
        }
    });
});

describe('SiteSurface — authored values are untrusted', () => {
    it('drops a custom property that is not one of ours', () => {
        const w = mountSurface(
            payload({
                type: 'solid',
                css_vars: paintedVars({ '--tw-ring-color': 'red', background: 'url(x)' }),
            }),
        );

        const style = (w.element as HTMLElement).getAttribute('style') ?? '';

        expect(style).not.toContain('--tw-ring-color');
        expect(style).not.toContain('url(x)');
    });

    it('strips characters a CSS value may never carry out of our own properties', () => {
        const w = mountSurface(
            payload({
                type: 'solid',
                css_vars: {
                    '--myra-page-bg': 'oklch(0.2 0 0);}body{opacity:0',
                    '--myra-page-fg': 'oklch(1 0 0)',
                },
            }),
        );

        const style = (w.element as HTMLElement).getAttribute('style') ?? '';

        expect(style).not.toContain('{');
        expect(style).not.toContain('}');
        expect(style).toContain('background-color: var(--myra-page-bg)');
    });

    it('refuses an executable scheme in the image url', () => {
        const w = mountSurface(
            payload({
                type: 'image',
                // eslint-disable-next-line no-script-url
                image_url: 'javascript:alert(1)',
                css_vars: paintedVars(),
            }),
        );

        expect(w.find('img').exists()).toBe(false);
        expect(w.html()).not.toContain('javascript:');
    });
});
