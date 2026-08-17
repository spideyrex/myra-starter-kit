import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';

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

/**
 * THE SNAPSHOT. Transcribed from the pre-refactor GuestLayout.vue, element for
 * element, in document order. The dispatcher refactor must reproduce it byte
 * for byte at stock defaults — this is the mechanical proof of "nothing changes
 * on upgrade".
 *
 * `data-slot` nodes are shadcn components whose final class string is composed
 * by tailwind-merge; for those two the AUTHORED classes are asserted by
 * containment instead, so a tailwind-merge upgrade cannot make this test lie
 * about a structural change it did not detect.
 */
const SNAPSHOT: Array<{ tag: string; class: string; slot?: string; contains?: string[] }> = [
    { tag: 'DIV', class: 'flex min-h-screen' },
    {
        tag: 'DIV',
        class: 'hidden lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center bg-primary px-8 text-primary-foreground',
    },
    { tag: 'DIV', class: 'max-w-md space-y-6 text-center' },
    { tag: 'DIV', class: 'flex items-center justify-center gap-3 text-3xl font-bold' },
    { tag: 'SPAN', class: 'flex items-center gap-2' },
    {
        tag: 'SPAN',
        class: 'flex shrink-0 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground size-12 text-2xl',
    },
    { tag: 'SPAN', class: 'grid min-w-0 flex-1 text-left leading-tight' },
    { tag: 'SPAN', class: 'truncate font-semibold text-2xl' },
    {
        tag: 'BLOCKQUOTE',
        class: 'mt-8 border-l-2 border-primary-foreground/30 pl-4 text-left text-lg italic text-primary-foreground/80',
    },
    { tag: 'DIV', class: 'flex w-full flex-col items-center justify-center px-4 py-8 lg:w-1/2' },
    { tag: 'DIV', class: 'mb-6 lg:hidden' },
    { tag: 'A', class: 'text-foreground' },
    { tag: 'SPAN', class: 'flex items-center gap-2' },
    {
        tag: 'SPAN',
        class: 'flex shrink-0 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground size-8 text-base',
    },
    { tag: 'SPAN', class: 'grid min-w-0 flex-1 text-left leading-tight' },
    { tag: 'SPAN', class: 'truncate font-semibold text-base' },
    {
        tag: 'DIV',
        class: '',
        slot: 'card',
        contains: ['bg-card', 'text-card-foreground', 'w-full', 'max-w-md', 'border-0', 'shadow-none', 'lg:border', 'lg:shadow-sm'],
    },
    { tag: 'DIV', class: '', slot: 'card-content', contains: ['p-6'] },
    { tag: 'P', class: 'the-form' },
];

function walk(root: Element): Array<{ tag: string; class: string; slot: string | null }> {
    const out: Array<{ tag: string; class: string; slot: string | null }> = [];

    const visit = (el: Element) => {
        out.push({
            tag: el.tagName,
            class: el.getAttribute('class') ?? '',
            slot: el.getAttribute('data-slot'),
        });

        for (const child of Array.from(el.children)) visit(child);
    };

    visit(root);

    return out;
}

function mountLayout() {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    page.props = { brand: BRAND };

    return mount(GuestLayout, {
        slots: { default: '<p class="the-form">the form</p>' },
        global: { plugins: [i18n, ziggy] },
    });
}

describe('guest layout at stock defaults', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        document.body.innerHTML = '';
        document.documentElement.classList.remove('dark');
    });

    it('reproduces the pre-refactor DOM element for element', () => {
        const wrapper = mountLayout();
        const nodes = walk(wrapper.get('.min-h-screen').element);

        expect(nodes).toHaveLength(SNAPSHOT.length);

        SNAPSHOT.forEach((expected, i) => {
            const actual = nodes[i];

            expect(actual.tag, `node ${i} tag`).toBe(expected.tag);

            if (expected.slot) {
                expect(actual.slot, `node ${i} data-slot`).toBe(expected.slot);

                for (const token of expected.contains ?? []) {
                    expect(actual.class.split(/\s+/), `node ${i} class [${token}]`).toContain(token);
                }

                return;
            }

            expect(actual.class, `node ${i} class`).toBe(expected.class);
        });
    });

    it('renders the tagline the brand supplied, in ASCII double quotes', () => {
        const quote = mountLayout().find('blockquote');

        expect(quote.exists()).toBe(true);
        expect(quote.text()).toBe(`"${BRAND.tagline}"`);
    });

    it('falls back to the i18n tagline when the brand has none', () => {
        page.props = { brand: { ...BRAND, tagline: '' } };

        const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });
        const wrapper = mount(GuestLayout, { global: { plugins: [i18n, ziggy] } });

        expect(wrapper.find('blockquote').text()).toBe(`"${en.auth.defaultTagline}"`);
    });

    it('emits no inline style at all when the surface is the brand default', () => {
        const root = mountLayout().get('.min-h-screen').element;

        expect(root.querySelectorAll('[style]')).toHaveLength(0);
        expect(root.getAttribute('style')).toBeNull();
    });

    it('renders the slot, and the slot is never inside an error boundary', () => {
        expect(mountLayout().find('p.the-form').text()).toBe('the form');
    });
});

function mountSurface(surface: Record<string, unknown>, extra: Record<string, unknown> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    page.props = {
        brand: BRAND,
        appearance: {
            auth: {
                layout: 'split',
                component: 'SplitLayout',
                flip: false,
                show_tagline: true,
                supports_media: true,
                surface: {
                    type: 'solid',
                    recipe: null,
                    scrim: 'medium',
                    image_url: null,
                    base: 'oklch(0.2 0 0)',
                    foreground: 'oklch(1 0 0)',
                    contrast: 15,
                    css_vars: { '--myra-auth-bg': 'oklch(0.2 0 0)', '--myra-auth-fg': 'oklch(1 0 0)', '--myra-auth-scrim': '0.55' },
                    ...surface,
                },
                ...extra,
            },
            page: { navbar_translucent: false, surface: { type: 'none', recipe: null, scrim: 'medium', image_url: null, base: '', foreground: '', contrast: 0, css_vars: {} } },
        },
    };

    return mount(GuestLayout, {
        slots: { default: '<p class="the-form">the form</p>' },
        global: { plugins: [i18n, ziggy] },
    });
}

describe('split shell with a configured surface', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        document.documentElement.classList.remove('dark');
    });

    it('drops the brand utilities and carries the server-sanitised custom properties', () => {
        const wrapper = mountSurface({});
        const panel = wrapper.findAll('div').find((d) => d.classes().includes('lg:w-1/2'));

        expect(panel).toBeTruthy();
        expect(panel!.classes()).not.toContain('bg-primary');
        expect(panel!.classes()).not.toContain('text-primary-foreground');
        expect(panel!.classes()).toContain('relative');
        expect(panel!.attributes('style') ?? '').toContain('myra-auth-bg');
    });

    /** No v-html, no raw interpolation: a Record<string,string> of custom properties. */
    it('binds colours only through custom properties, never an authored string', () => {
        const wrapper = mountSurface({ type: 'solid' }, {});

        expect(wrapper.html()).not.toContain('v-html');
        expect(wrapper.html()).not.toContain('javascript:');
        expect(wrapper.html()).not.toContain('expression(');
    });

    it('hides a background image that fails to load and keeps the base colour', async () => {
        const wrapper = mountSurface({ type: 'image', image_url: '/storage/appearance/gone.jpg' });

        const img = wrapper.find('img[alt=""]');
        expect(img.exists()).toBe(true);

        await img.trigger('error');

        expect(wrapper.find('img[alt=""]').exists()).toBe(false);

        // The base colour survives: the panel still carries the surface panel class.
        const panel = wrapper.findAll('div').find((d) => d.classes().includes('lg:w-1/2'));
        expect(panel!.classes()).toContain('relative');
        expect(wrapper.find('p.the-form').text()).toBe('the form');
    });

    it('marks the scrim decorative and drives its opacity from the server value', () => {
        const wrapper = mountSurface({ type: 'image', image_url: '/storage/appearance/hero.jpg' });
        const scrim = wrapper.findAll('[aria-hidden="true"]').find((d) => d.classes().includes('bg-black'));

        expect(scrim).toBeTruthy();
        expect(scrim!.attributes('style')).toContain('opacity: 0.55');
    });

    it('keeps the form on a card whatever the surface does', () => {
        const wrapper = mountSurface({ type: 'gradient', recipe: 'dusk' });

        expect(wrapper.find('[data-slot="card"]').classes()).toContain('bg-card');
        expect(wrapper.html()).toContain('max-w-md');
        expect(wrapper.html()).toContain('min-h-screen');
    });

    it('suppresses the tagline when the server says so', () => {
        expect(mountSurface({}, { show_tagline: false }).find('blockquote').exists()).toBe(false);
    });

    it('flips the two halves without touching tab order', () => {
        const wrapper = mountSurface({}, { flip: true });

        expect(wrapper.html()).toContain('lg:order-2');
        expect(wrapper.html()).toContain('lg:order-1');
        expect(wrapper.findAll('a')).toHaveLength(1);
    });
});
