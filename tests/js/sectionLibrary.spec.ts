import { describe, expect, it, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: null } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/composables/useThemeColors', () => ({ useThemeColors: () => ({}) }));

import { testI18n } from './helpers/i18n';
import fixture from './fixtures/homepage-settings.json';

const SECTION_DIR = resolve(__dirname, '../../resources/js/Pages/Public/Templates/_shared/sections');

/** Eager, exactly like PageSections.vue: the public front door paints in one pass. */
const modules = import.meta.glob<{ default: Component }>(
    '@/Pages/Public/Templates/_shared/sections/*.vue',
    { eager: true },
);

const registry: Record<string, Component> = Object.fromEntries(
    Object.entries(modules).map(([path, mod]) => [
        (path.split('/').pop() ?? '').replace(/\.vue$/, ''),
        mod.default,
    ]),
);

/** The five this bundle owns. The rest arrive with bundle A and are covered generically. */
const OWNED = ['rich_text', 'image', 'stats', 'faq', 'divider'];

const settings = fixture.settings as Record<string, unknown>;

function mountSection(type: string, block: Record<string, unknown> = {}, variant: Record<string, unknown> = {}) {
    return mount(registry[type], {
        props: { block, settings, variant } as never,
        global: { plugins: [testI18n()] },
    });
}

function sourceOf(type: string): string {
    return readFileSync(resolve(SECTION_DIR, `${type}.vue`), 'utf8');
}

afterEach(() => {
    document.documentElement.classList.remove('dark');
});

describe('section library — every type is mountable', () => {
    it('ships the five starter types this bundle owns', () => {
        for (const type of OWNED) {
            expect(existsSync(resolve(SECTION_DIR, `${type}.vue`)), `${type}.vue must exist`).toBe(true);
            expect(registry[type], `${type} must resolve through the glob`).toBeTruthy();
        }
    });

    it('renders a <section> for every registered type from an empty block, light and dark', () => {
        for (const type of Object.keys(registry)) {
            for (const dark of [false, true]) {
                document.documentElement.classList.toggle('dark', dark);

                const wrapper = mountSection(type);

                expect(wrapper.html(), `${type} (dark=${dark}) must render a section`).toContain('<section');
                wrapper.unmount();
            }
        }
    });

    it('survives block data of entirely the wrong shape', () => {
        const garbage: Record<string, unknown> = {
            title: 42,
            heading: null,
            subtitle: { nested: true },
            body: 7,
            items: 'not a list',
            plans: 'not a list',
            image_url: 12,
            alt: [],
            style: 99,
            size: {},
        };

        for (const type of Object.keys(registry)) {
            const wrapper = mountSection(type, garbage, { width: 9, tinted: 'yes' });

            expect(wrapper.html(), `${type} must still render`).toContain('<section');
            wrapper.unmount();
        }
    });

    it('survives list rows that are not objects', () => {
        const wrapper = mountSection('stats', { title: 'Numbers', items: [1, 'two', null, ['x']] });

        expect(wrapper.html()).toContain('Numbers');
        expect(wrapper.findAll('dd')).toHaveLength(0);
        wrapper.unmount();
    });
});

describe('section library — theme awareness', () => {
    it('uses theme tokens rather than literal colours', () => {
        for (const type of OWNED) {
            const source = sourceOf(type);

            expect(source, `${type} must not hardcode a hex colour`).not.toMatch(/#[0-9a-fA-F]{3,8}\b/);
            expect(source, `${type} must not hardcode bg-white`).not.toContain('bg-white');
            expect(source, `${type} must not hardcode text-black`).not.toContain('text-black');
        }
    });

    it('gives every owned type exactly one <section> root', () => {
        for (const type of OWNED) {
            const opens = sourceOf(type).match(/<section\b/g) ?? [];

            expect(opens, `${type} must have exactly one <section>`).toHaveLength(1);
        }
    });

    it('renders authored content only, so there is no English to translate', () => {
        for (const type of OWNED) {
            const source = sourceOf(type);

            expect(source, `${type} must not pull in the message catalogue`).not.toContain('useI18n');
        }
    });
});

describe('rich_text — authored HTML is untrusted at render', () => {
    it('drops a script tag while keeping the prose', () => {
        const wrapper = mountSection('rich_text', {
            heading: 'Docs',
            body: '<p>ok</p><script>alert(1)</script>',
        });

        const html = wrapper.html();

        expect(html).toContain('Docs');
        expect(html).toContain('ok');
        expect(html).not.toContain('<script');
        expect(wrapper.element.querySelectorAll('script')).toHaveLength(0);
        wrapper.unmount();
    });

    it('drops an event handler attribute', () => {
        const wrapper = mountSection('rich_text', { body: '<img src=x onerror=alert(1)><p>after</p>' });

        expect(wrapper.html()).not.toContain('onerror');
        expect(wrapper.html()).toContain('after');
        wrapper.unmount();
    });

    it('removes an iframe entirely', () => {
        const wrapper = mountSection('rich_text', { body: '<iframe src="https://evil.test"></iframe><p>kept</p>' });

        expect(wrapper.element.querySelectorAll('iframe')).toHaveLength(0);
        expect(wrapper.html()).toContain('kept');
        wrapper.unmount();
    });

    it('neutralises a javascript: href', () => {
        const wrapper = mountSection('rich_text', { body: '<a href="javascript:alert(1)">x</a>' });

        expect(wrapper.html()).not.toContain('javascript:');
        wrapper.unmount();
    });

    it('honours the width variant', () => {
        const prose = mountSection('rich_text', { body: '<p>a</p>' }, { width: 'prose' });
        const wide = mountSection('rich_text', { body: '<p>a</p>' }, { width: 'wide' });

        expect(prose.html()).toContain('max-w-3xl');
        expect(wide.html()).toContain('max-w-5xl');
        prose.unmount();
        wide.unmount();
    });
});

describe('image — a missing file never renders a broken image', () => {
    it('renders no <img> when the normaliser resolved no url', () => {
        const wrapper = mountSection('image', { image_path: 'homepage/gone.webp', image_url: null, alt: 'Gone' });

        expect(wrapper.findAll('img')).toHaveLength(0);
        expect(wrapper.html()).toContain('<section');
        wrapper.unmount();
    });

    it('still shows the caption when the file is missing', () => {
        const wrapper = mountSection('image', { image_url: null, caption: 'Chart of uptime' });

        expect(wrapper.text()).toContain('Chart of uptime');
        wrapper.unmount();
    });

    it('renders the figure with the authored alt text when the url resolves', () => {
        const wrapper = mountSection('image', {
            image_url: '/storage/homepage/x.webp',
            alt: 'A dashboard',
            caption: 'Uptime',
        });

        const img = wrapper.get('img');

        expect(img.attributes('src')).toBe('/storage/homepage/x.webp');
        expect(img.attributes('alt')).toBe('A dashboard');
        expect(img.attributes('loading')).toBe('lazy');
        wrapper.unmount();
    });

    it('wraps the image in a link only when one was authored', () => {
        const plain = mountSection('image', { image_url: '/storage/a.webp', alt: 'a' });
        const linked = mountSection('image', { image_url: '/storage/a.webp', alt: 'a', link_url: 'https://myra.test' });

        expect(plain.findAll('a')).toHaveLength(0);
        expect(linked.get('a').attributes('href')).toBe('https://myra.test');
        expect(linked.get('a').attributes('rel')).toBe('noopener noreferrer');
        plain.unmount();
        linked.unmount();
    });
});

describe('stats, faq and divider', () => {
    it('caps stats at four items and renders value and label', () => {
        const items = Array.from({ length: 9 }, (_, i) => ({ value: `${i}x`, label: `Label ${i}` }));
        const wrapper = mountSection('stats', { title: 'By the numbers', items });

        expect(wrapper.findAll('dd')).toHaveLength(4);
        expect(wrapper.text()).toContain('0x');
        expect(wrapper.text()).toContain('Label 0');
        wrapper.unmount();
    });

    it('renders faq questions as accordion triggers and drops rows with no question', () => {
        const wrapper = mountSection('faq', {
            title: 'Questions',
            items: [
                { question: 'Can I cancel?', answer: 'Any time.' },
                { question: '', answer: 'orphan' },
            ],
        });

        const triggers = wrapper.findAll('button');

        expect(triggers).toHaveLength(1);
        expect(triggers[0].text()).toContain('Can I cancel?');
        expect(wrapper.text()).not.toContain('orphan');
        wrapper.unmount();
    });

    it('renders a rule for the rule style and nothing but space otherwise', () => {
        const rule = mountSection('divider', { style: 'rule', size: 'md' });
        const space = mountSection('divider', { style: 'space', size: 'lg' });

        expect(rule.findAll('hr')).toHaveLength(1);
        expect(space.findAll('hr')).toHaveLength(0);
        expect(space.html()).toContain('py-20');
        rule.unmount();
        space.unmount();
    });

    it('falls back to a known spacing for an unknown size', () => {
        const wrapper = mountSection('divider', { style: 'rule', size: 'enormous' });

        expect(wrapper.html()).toContain('py-12');
        wrapper.unmount();
    });
});

describe('section library — filenames are the type keys', () => {
    it('names every file in lower snake case so the stored `type` maps straight to it', () => {
        for (const name of readdirSync(SECTION_DIR).filter(f => f.endsWith('.vue'))) {
            expect(name.replace(/\.vue$/, '')).toMatch(/^[a-z][a-z0-9_]*$/);
        }
    });
});
