import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import BrandMark from '@/components/brand/BrandMark.vue';
import tokens from './fixtures/brand-tokens.json';

const page = { props: {} as Record<string, unknown> };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function mountMark(brand: Record<string, unknown>, props: Record<string, unknown> = {}) {
    page.props = { brand };

    return mount(BrandMark, { props });
}

describe('BrandMark', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
        document.documentElement.classList.remove('dark');
    });

    it('gives the logo image the brand name as its accessible name', () => {
        const wrapper = mountMark({ ...tokens.brand, logo_url: '/storage/brand/abc/logo.png' });
        const img = wrapper.find('img');

        expect(img.exists()).toBe(true);
        expect(img.attributes('alt')).toBe(tokens.brand.name);
        expect(img.attributes('aria-hidden')).toBeUndefined();
    });

    it('hides the initial fallback and lets the name text carry the label', () => {
        const wrapper = mountMark({ ...tokens.brand, logo_url: null });

        expect(wrapper.find('img').exists()).toBe(false);

        const mark = wrapper.find('[aria-hidden="true"]');

        expect(mark.exists()).toBe(true);
        expect(mark.text()).toBe(tokens.brand.initial);
        expect(wrapper.text()).toContain(tokens.brand.name);
    });

    it('never labels the mark and the text at the same time', () => {
        const wrapper = mountMark({ ...tokens.brand, logo_url: '/x.png' });

        expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(0);
    });

    it('square variant still exposes the name to assistive tech', () => {
        const wrapper = mountMark({ ...tokens.brand, logo_url: null }, { variant: 'square' });

        expect(wrapper.find('.sr-only').text()).toBe(tokens.brand.name);
    });

    it('prefers the dark logo when the document is dark', () => {
        document.documentElement.classList.add('dark');

        const wrapper = mountMark({
            ...tokens.brand,
            logo_url: '/light.png',
            logo_dark_url: '/dark.png',
        });

        expect(wrapper.find('img').attributes('src')).toBe('/dark.png');
    });

    it('falls back to the site name when no brand prop is shared', () => {
        page.props = { siteSettings: { site_name: 'Legacy Co' } };

        const wrapper = mount(BrandMark);

        expect(wrapper.text()).toContain('Legacy Co');
        expect(wrapper.find('[aria-hidden="true"]').text()).toBe('L');
    });

    it('renders a subtitle only when one is given', () => {
        expect(mountMark(tokens.brand).text()).not.toContain('Dashboard');
        expect(mountMark(tokens.brand, { subtitle: 'Dashboard' }).text()).toContain('Dashboard');
    });
});
