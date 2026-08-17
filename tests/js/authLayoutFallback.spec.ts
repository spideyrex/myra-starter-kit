import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import type { Component } from 'vue';
import en from '@/i18n/locales/en.json';
// THE REAL PAYLOAD: written by tests/Feature/Appearance/AuthLayoutSchemaFixtureTest
// from AuthLayoutRegistry::toClientSchema(). Never hand-authored.
import layouts from '../fixtures/appearance/layouts.json';

const page = { props: {} as Record<string, unknown> };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useFlashToasts', () => ({ useFlashToasts: () => undefined }));

vi.mock('@/components/ui/sonner', () => ({
    Toaster: { name: 'Toaster', template: '<div data-toaster />' },
}));

const route = (name: string) => `/${name.replace(/\./g, '/')}`;
(globalThis as unknown as { route: typeof route }).route = route;
const ziggy = {
    install: (app: { config: { globalProperties: Record<string, unknown> } }) => {
        app.config.globalProperties.route = route;
    },
};

import GuestLayout from '@/Layouts/GuestLayout.vue';
import { DEFAULT_APPEARANCE, normaliseAppearance, readAppearanceMeta } from '@/composables/useAppearance';

/** The shells that really ship in this build — the same list the dispatcher globs. */
const SHIPPED = Object.keys(
    import.meta.glob<{ default: Component }>('../../resources/js/Layouts/Guest/*Layout.vue', { eager: true }),
).map((path) => path.split('/').pop()!.replace('.vue', ''));

const BRAND = {
    enabled: true,
    name: 'Acme Corp',
    short_name: 'Acme',
    tagline: 'Everything, delivered.',
    description: '',
    logo_url: null,
    logo_dark_url: null,
    mark_url: null,
    favicon_url: null,
    og_image_url: null,
    logo_position: 'header',
    initial: 'A',
    palette: { primary: '#18181b', accent: null, sidebar_background: null, sidebar_foreground: null, sidebar_accent: null, preset: 'zinc' },
    typography: { sans: 'figtree', mono: 'ui-monospace' },
    radius: '0.625rem',
    dark_default: false,
    hash: 'abcd1234',
};

function payloadFor(component: string) {
    return {
        auth: { ...DEFAULT_APPEARANCE.auth, component },
        page: DEFAULT_APPEARANCE.page,
    };
}

function mountWith(appearance: unknown) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    page.props = { brand: BRAND, appearance };

    return mount(GuestLayout, {
        slots: { default: '<p class="the-form">the form</p>' },
        global: { plugins: [i18n, ziggy] },
    });
}

/** Split is the only shell carrying the 50/50 branding panel. */
const isSplit = (html: string) => html.includes('lg:w-1/2');

describe('guest shell resolution', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        document.documentElement.classList.remove('dark');
        page.props = {};
    });

    it('the fixture is the real registry payload and always contains the fallback', () => {
        expect(layouts.length).toBeGreaterThan(0);
        expect(layouts.map((l) => l.key)).toContain('split');
        expect(layouts.find((l) => l.key === 'split')!.component).toBe('SplitLayout');
        expect(SHIPPED).toContain('SplitLayout');
    });

    it.each(layouts.map((l) => [l.key, l.component] as const))(
        'mounts a working login page for the registered layout %s',
        (key, component) => {
            const wrapper = mountWith(payloadFor(component));
            const html = wrapper.html();

            // A working form, whichever shell resolved.
            expect(wrapper.find('p.the-form').text()).toBe('the form');
            expect(wrapper.find('[data-slot="card"]').exists()).toBe(true);
            expect(html).toContain('max-w-md');
            expect(html).toContain('min-h-screen');

            // The client guard: a registered layout with no .vue falls to Split.
            if (!SHIPPED.includes(component)) {
                expect(isSplit(html), `[${key}] should have fallen back to Split`).toBe(true);
            }
        },
    );

    it.each([
        ['an unknown component', 'NotAShell'],
        ['a traversal-shaped component', '../../etc/passwd'],
        ['an empty component', ''],
    ])('falls back to Split for %s', (_label, component) => {
        const html = mountWith(payloadFor(component)).html();

        expect(isSplit(html)).toBe(true);
        expect(html).toContain('the form');
    });

    it('falls back to Split when the shared prop is missing entirely', () => {
        const wrapper = mountWith(undefined);

        expect(isSplit(wrapper.html())).toBe(true);
        expect(wrapper.find('p.the-form').exists()).toBe(true);
    });

    it('falls back to Split for every shape of malformed payload', () => {
        const hostile: unknown[] = [null, 123, 'nope', [], {}, { auth: 'nope' }, { auth: null, page: 7 }];

        for (const appearance of hostile) {
            const wrapper = mountWith(appearance);

            expect(isSplit(wrapper.html()), JSON.stringify(appearance)).toBe(true);
            expect(wrapper.find('p.the-form').exists()).toBe(true);
        }
    });

    it('reads the meta tag when Inertia never shared the prop', () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'myra-appearance');
        meta.setAttribute('content', JSON.stringify(payloadFor('SplitLayout')));
        document.head.appendChild(meta);

        expect(readAppearanceMeta()?.auth.component).toBe('SplitLayout');
    });

    it('ignores a malformed meta tag rather than throwing', () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'myra-appearance');
        meta.setAttribute('content', '{not json');
        document.head.appendChild(meta);

        expect(readAppearanceMeta()).toBeNull();
    });
});

describe('payload normalisation', () => {
    it('drops css custom properties that are not ours', () => {
        const normalised = normaliseAppearance({
            auth: {
                component: 'SplitLayout',
                surface: {
                    type: 'solid',
                    css_vars: { '--myra-auth-bg': 'oklch(0.2 0 0)', '--evil': 'red', background: 'url(x)' },
                },
            },
            page: {},
        });

        expect(normalised!.auth.surface.css_vars).toEqual({ '--myra-auth-bg': 'oklch(0.2 0 0)' });
    });

    it('never carries css vars for the two zero-css types', () => {
        for (const type of ['brand', 'none']) {
            const normalised = normaliseAppearance({
                auth: { surface: { type, css_vars: { '--myra-auth-bg': 'oklch(0.2 0 0)' } } },
                page: {},
            });

            expect(normalised!.auth.surface.css_vars).toEqual({});
        }
    });

    it('raises an unknown background type to the stock one', () => {
        const normalised = normaliseAppearance({ auth: { surface: { type: 'video' } }, page: {} });

        expect(normalised!.auth.surface.type).toBe('brand');
        expect(normalised!.page.surface.type).toBe('none');
    });
});
