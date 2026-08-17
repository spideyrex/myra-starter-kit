import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import type { Component } from 'vue';

const page = { props: {} as Record<string, unknown> };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const route = (name: string) => `/${name.replace(/\./g, '/')}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
import tokens from './fixtures/brand-tokens.json';
import BrandMark from '@/components/brand/BrandMark.vue';
import CenteredLayout from '@/Layouts/Guest/CenteredLayout.vue';
import CoverLayout from '@/Layouts/Guest/CoverLayout.vue';
import CardLayout from '@/Layouts/Guest/CardLayout.vue';
import {
    normalizeSurface,
    STOCK_AUTH,
    STOCK_SURFACE,
    surfaceStyle,
    type AuthAppearancePayload,
    type SurfacePayload,
} from '@/Layouts/Guest/authTypes';

/**
 * The three shells bundle D owns. Nothing here imports the engine: the payload
 * is built from the same structural type the shells declare, which is exactly
 * the contract the server promises.
 */
const SHELLS: Array<{ name: string; component: Component; media: boolean }> = [
    { name: 'CenteredLayout', component: CenteredLayout, media: false },
    { name: 'CoverLayout', component: CoverLayout, media: true },
    { name: 'CardLayout', component: CardLayout, media: true },
];

function imageSurface(): SurfacePayload {
    return {
        type: 'image',
        recipe: null,
        scrim: 'strong',
        image_url: '/storage/appearance/hero.jpg',
        base: 'oklch(0.21 0.03 264)',
        foreground: 'oklch(1 0 0)',
        contrast: 15.2,
        css_vars: {
            '--myra-auth-bg': 'oklch(0.21 0.03 264)',
            '--myra-auth-fg': 'oklch(1 0 0)',
            '--myra-auth-scrim': '0.7',
        },
    };
}

function mountShell(component: Component, auth: Partial<AuthAppearancePayload> = {}): VueWrapper {
    page.props = { brand: tokens.brand };

    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en, ms, zh } });

    return mount(component, {
        props: { auth: { ...STOCK_AUTH, ...auth } },
        slots: { default: '<p data-test="slot">THE FORM</p>' },
        global: { plugins: [i18n, ziggy] },
    }) as VueWrapper;
}

/** True when at least one mark has no `hidden` ancestor, i.e. it survives on mobile. */
function markVisibleOnMobile(wrapper: VueWrapper): boolean {
    return wrapper.findAllComponents(BrandMark).some((mark) => {
        let node: HTMLElement | null = mark.element as HTMLElement;

        while (node) {
            if (node.classList && node.classList.contains('hidden')) {
                return false;
            }
            node = node.parentElement;
        }

        return true;
    });
}

describe('guest shells', () => {
    beforeEach(() => {
        document.documentElement.classList.remove('dark');
    });

    for (const shell of SHELLS) {
        describe(shell.name, () => {
            it('mounts on a stock payload and renders the slot', () => {
                const w = mountShell(shell.component);

                expect(w.find('[data-test="slot"]').exists()).toBe(true);
                expect(w.text()).toContain('THE FORM');
            });

            it('mounts even when the payload is absent or junk', () => {
                page.props = { brand: tokens.brand };

                const i18n = createI18n({ legacy: false, locale: 'en', messages: { en, ms, zh } });

                for (const auth of [undefined, null, 'nope', 42, [], { surface: 'nope' }]) {
                    const w = mount(shell.component, {
                        props: { auth } as any,
                        slots: { default: '<p data-test="slot">THE FORM</p>' },
                        global: { plugins: [i18n, ziggy] },
                    });

                    expect(w.find('[data-test="slot"]').exists()).toBe(true);
                }
            });

            it('grows with the page instead of trapping it in the viewport', () => {
                const root = mountShell(shell.component).element as HTMLElement;
                const classes = Array.from(root.classList);

                expect(classes).toContain('min-h-screen');
                expect(classes).not.toContain('h-screen');
                expect(classes).not.toContain('overflow-hidden');
            });

            it('keeps the form card at max-w-md or wider', () => {
                const card = mountShell(shell.component).find('[data-slot="card"]');

                expect(card.exists()).toBe(true);
                expect(card.classes().join(' ')).toMatch(/(?:^|\s)max-w-(?:md|lg|xl|\dxl)(?:\s|$)/);
            });

            it('keeps the form inside the card surface', () => {
                const w = mountShell(shell.component);
                const card = w.find('[data-slot="card"]');

                expect(card.classes()).toContain('bg-card');
                expect(card.classes()).toContain('text-card-foreground');
                expect(card.find('[data-test="slot"]').exists()).toBe(true);
            });

            it('still shows the brand below the media breakpoint', () => {
                expect(markVisibleOnMobile(mountShell(shell.component))).toBe(true);
            });

            it('suppresses the tagline when the payload says so', () => {
                const shown = mountShell(shell.component, { show_tagline: true });

                expect(shown.text()).toContain(tokens.brand.tagline);

                const hidden = mountShell(shell.component, { show_tagline: false });

                expect(hidden.text()).not.toContain(tokens.brand.tagline);
            });

            it('falls back to the shipped tagline when the brand has none', () => {
                const i18n = createI18n({ legacy: false, locale: 'en', messages: { en, ms, zh } });

                page.props = { brand: { ...tokens.brand, tagline: '' } };

                const w = mount(shell.component, {
                    props: { auth: STOCK_AUTH } as any,
                    slots: { default: '<p data-test="slot">THE FORM</p>' },
                    global: { plugins: [i18n, ziggy] },
                });

                expect(w.text()).toContain(en.auth.defaultTagline);
            });

            it('leaves the brand literals alone for a brand surface', () => {
                const surface = mountShell(shell.component).find('[data-slot="auth-surface"]');

                expect(surface.exists()).toBe(true);
                expect(surface.classes()).toContain('bg-primary');
                expect(surface.classes()).toContain('text-primary-foreground');
            });

            it('never lets an authored value reach the markup as a url', () => {
                const html = mountShell(shell.component, { surface: imageSurface() }).html();

                expect(html).not.toContain('url(');
                expect(html).not.toContain('javascript:');
            });

            if (shell.media) {
                it('marks the decorative image and the scrim as presentational', () => {
                    const w = mountShell(shell.component, { surface: imageSurface() });
                    const img = w.find('img[src="/storage/appearance/hero.jpg"]');

                    expect(img.exists()).toBe(true);
                    expect(img.attributes('alt')).toBe('');

                    const scrim = w.find('.bg-black');

                    expect(scrim.exists()).toBe(true);
                    expect(w.find('[aria-hidden="true"]').exists()).toBe(true);
                });

                it('drops a 404ing image but keeps the base colour underneath', async () => {
                    const w = mountShell(shell.component, { surface: imageSurface() });

                    await w.find('img[src="/storage/appearance/hero.jpg"]').trigger('error');

                    expect(w.find('img[src="/storage/appearance/hero.jpg"]').exists()).toBe(false);
                    expect(w.find('.bg-black').exists()).toBe(false);
                    expect(w.find('[data-slot="auth-surface"]').exists()).toBe(true);
                    expect(w.find('[data-test="slot"]').exists()).toBe(true);
                });

                it('refuses media when the resolved layout does not support it', () => {
                    const w = mountShell(shell.component, { surface: imageSurface(), supports_media: false });

                    expect(w.find('img[src="/storage/appearance/hero.jpg"]').exists()).toBe(false);
                    expect(w.find('[data-slot="auth-surface"]').exists()).toBe(true);
                });
            } else {
                it('never renders a background image at all', () => {
                    const w = mountShell(shell.component, { surface: imageSurface() });

                    expect(w.find('img[src="/storage/appearance/hero.jpg"]').exists()).toBe(false);
                    expect(w.find('[data-slot="auth-surface"]').exists()).toBe(true);
                });
            }
        });
    }

    it('renders a pattern recipe at reduced opacity and a gradient at full', () => {
        const recipe = (type: 'gradient' | 'pattern'): SurfacePayload => ({
            ...imageSurface(),
            type,
            image_url: null,
            recipe: type === 'pattern' ? 'dots' : 'dusk',
            css_vars: {
                '--myra-auth-bg': 'oklch(0.21 0.03 264)',
                '--myra-auth-fg': 'oklch(1 0 0)',
                '--myra-auth-image': 'linear-gradient(160deg,var(--primary) 0%,oklch(0.25 0.05 280) 100%)',
            },
        });

        const pattern = mountShell(CoverLayout, { surface: recipe('pattern') });
        const gradient = mountShell(CoverLayout, { surface: recipe('gradient') });

        expect(pattern.html()).toContain('opacity-[0.12]');
        expect(gradient.html()).not.toContain('opacity-[0.12]');
        expect(gradient.html()).toContain('var(--myra-auth-image)');
    });

    it('emits nothing at all for the two surfaces that must stay a no-op', () => {
        expect(surfaceStyle(STOCK_SURFACE)).toEqual({});
        expect(surfaceStyle({ ...STOCK_SURFACE, type: 'none' })).toEqual({});
    });

    it('emits only custom properties and two references to them', () => {
        const style = surfaceStyle(imageSurface());

        expect(style).toEqual({
            '--myra-auth-bg': 'oklch(0.21 0.03 264)',
            '--myra-auth-fg': 'oklch(1 0 0)',
            '--myra-auth-scrim': '0.7',
            backgroundColor: 'var(--myra-auth-bg)',
            color: 'var(--myra-auth-fg)',
        });
    });

    it('drops anything in css_vars that is not a server-shaped custom property', () => {
        const hostile = {
            ...imageSurface(),
            css_vars: {
                '--myra-auth-bg': 'oklch(0.2 0 0)',
                'background-image': 'url(https://evil.test/x.png)',
                onload: 'alert(1)',
                '--myra-auth-fg': { toString: () => 'oklch(1 0 0)' },
            } as unknown as Record<string, string>,
        };

        expect(Object.keys(surfaceStyle(normalizeSurface(hostile)))).toEqual([
            '--myra-auth-bg',
            'backgroundColor',
            'color',
        ]);
    });

    it('never introduces a focusable element beyond the one the split shell already had', () => {
        const focusable = (w: VueWrapper) =>
            w.findAll('a, button, input, select, textarea, [tabindex]')
                .filter(node => node.element.closest('[data-test="slot"]') === null).length;

        expect(focusable(mountShell(CenteredLayout))).toBe(0);
        expect(focusable(mountShell(CoverLayout))).toBe(0);
        expect(focusable(mountShell(CardLayout))).toBe(1);
    });
});
