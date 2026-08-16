import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Error403 from '@/Pages/Errors/403.vue';
import Error404 from '@/Pages/Errors/404.vue';
import Error419 from '@/Pages/Errors/419.vue';
import Error500 from '@/Pages/Errors/500.vue';
import Error503 from '@/Pages/Errors/503.vue';
import { testI18n } from './helpers/i18n';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';

const page = { props: { brand: { name: 'Acme Corp', initial: 'A', logo_url: null, logo_dark_url: null, logo_position: 'header' } } };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Head: { props: ['title'], template: '<span />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const pages = [
    ['403', Error403],
    ['404', Error404],
    ['419', Error419],
    ['500', Error500],
    ['503', Error503],
] as const;

function mountPage(component: any, props: Record<string, unknown> = {}) {
    return mount(component, { props, global: { plugins: [testI18n()] } });
}

describe('branded error pages', () => {
    it.each(pages)('%s exposes exactly one <h1>, a <main> landmark and a link home', (_code, component) => {
        const wrapper = mountPage(component);

        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('main#content').exists()).toBe(true);

        const home = wrapper.findAll('a').filter((a) => a.attributes('href') === '/');
        expect(home.length).toBeGreaterThan(0);
    });

    it.each(pages)('%s renders the brand, never a hardcoded product name', (_code, component) => {
        const text = mountPage(component).text();

        expect(text).toContain('Acme Corp');
        expect(text).not.toContain('Myra');
        expect(text).not.toContain('Laravel');
    });

    it.each(pages)('%s keeps the numeric code decorative so the h1 reads as prose', (code, component) => {
        const wrapper = mountPage(component);

        expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(true);
        expect(wrapper.find('h1').text()).not.toBe(code);
    });

    it('503 renders the configured maintenance message when one is supplied', () => {
        expect(mountPage(Error503).text()).not.toContain('Back at 06:00 UTC');
        expect(mountPage(Error503, { maintenanceMessage: 'Back at 06:00 UTC' }).text()).toContain('Back at 06:00 UTC');
    });

    it('every error string exists in all three locales', () => {
        for (const [code] of pages) {
            for (const locale of [en, ms, zh] as any[]) {
                expect(locale.brand.errors[code].title).toBeTruthy();
                expect(locale.brand.errors[code].body).toBeTruthy();
            }
            expect((ms as any).brand.errors[code].title).not.toBe((en as any).brand.errors[code].title);
            expect((zh as any).brand.errors[code].title).not.toBe((en as any).brand.errors[code].title);
        }
    });
});
