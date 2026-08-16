import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { Component } from 'vue';

const HOSTILE = 'javascript:alert(document.domain)';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: HOSTILE } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/composables/useThemeColors', () => ({ useThemeColors: () => ({}) }));

import { testI18n } from './helpers/i18n';
import fixture from './fixtures/homepage-settings.json';
import CtaBand from '@/Pages/Public/Templates/_shared/CtaBand.vue';
import HeroSection from '@/Pages/Public/Templates/_shared/HeroSection.vue';
import PricingTable from '@/Pages/Public/Templates/_shared/PricingTable.vue';
import SiteFooter from '@/Pages/Public/Templates/_shared/SiteFooter.vue';
import SiteNavbar from '@/Pages/Public/Templates/_shared/SiteNavbar.vue';

const DANGEROUS = /^(?:javascript|vbscript|data|file|blob):/i;

/**
 * Every authored URL the shared homepage chrome renders, poisoned at once.
 * These are the renderers the page-builder block adapters project onto, so an
 * unguarded binding here is a stored XSS on the public front door whichever
 * authoring path — legacy settings or blocks — produced the value.
 */
const poisoned = {
    ...(fixture.settings as Record<string, unknown>),
    hero_cta_url: HOSTILE,
    hero_image_url: HOSTILE,
    cta_button_url: HOSTILE,
    navbar_cta_url: HOSTILE,
    navbar_links: [{ label: 'Evil', url: HOSTILE }],
    footer_links: [{ label: 'Evil', url: HOSTILE }],
    pricing_plans: [
        {
            name: 'Free',
            price: '$0',
            period: '/mo',
            features: 'a,b',
            cta_text: 'Go',
            cta_url: HOSTILE,
            highlighted: false,
        },
    ],
};

const CASES: Array<[string, Component, Record<string, unknown>]> = [
    ['HeroSection (center)', HeroSection, {}],
    ['HeroSection (split)', HeroSection, { align: 'split' }],
    ['CtaBand', CtaBand, {}],
    ['PricingTable', PricingTable, {}],
    ['SiteFooter', SiteFooter, {}],
    ['SiteNavbar', SiteNavbar, { authenticated: false }],
];

describe('the shared homepage chrome never renders an executable URL', () => {
    it('drops a javascript: scheme out of every href, src and background', () => {
        for (const [label, component, extra] of CASES) {
            const wrapper = mount(component, {
                props: { settings: poisoned, ...extra } as never,
                global: { plugins: [testI18n()] },
            });

            const html = wrapper.html();

            for (const [, value] of html.matchAll(/\s(?:href|src)="([^"]*)"/gi)) {
                const bound = value.replace(/[\u0000-\u001f]/g, '').trim();

                expect(bound, `${label} bound a hostile scheme`).not.toMatch(DANGEROUS);
            }

            expect(html, `${label} leaked the hostile URL into markup`).not.toContain('javascript:');
            wrapper.unmount();
        }
    });

    it('keeps a legitimate relative CTA exactly as authored', () => {
        const wrapper = mount(HeroSection, {
            props: { settings: fixture.settings } as never,
            global: { plugins: [testI18n()] },
        });

        expect(wrapper.get('a').attributes('href')).toBe('/register');
        wrapper.unmount();
    });
});
