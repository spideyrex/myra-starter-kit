import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href ?? \'#\'"><slot /></a>' },
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, put: vi.fn() }),
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
    default: { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' },
}));

// maplibre-gl needs a WebGL canvas; the demo's a11y contract is the text list.
vi.mock('@/components/ui/map', () => ({
    Map: { name: 'Map', template: '<div class="stub-map"><slot /></div>' },
    MapControls: { name: 'MapControls', template: '<div />' },
    MapMarker: { name: 'MapMarker', template: '<div><slot /></div>' },
    MapPopup: { name: 'MapPopup', template: '<div><slot /></div>' },
}));

// @unovis draws into an SVG it measures at runtime; jsdom has no layout.
vi.mock('@unovis/vue', () => ({
    VisXYContainer: { name: 'VisXYContainer', template: '<div class="stub-vis"><slot /></div>' },
    VisGroupedBar: { name: 'VisGroupedBar', template: '<div />' },
    VisLine: { name: 'VisLine', template: '<div />' },
    VisAxis: { name: 'VisAxis', template: '<div />' },
}));

const route = (name: string) => `/${name.replace(/\./g, '/')}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
// THE REAL PAYLOAD: written by tests/Feature/Demo/ComponentDemoFixtureTest from
// the props ComponentDemoController actually produced.
import fixture from './fixtures/component-demos.json';
import { auditA11y } from './helpers/a11yBaseline';

import EmptyAndItem from '@/Pages/Admin/Demo/EmptyAndItem.vue';
import ChartPrimitives from '@/Pages/Admin/Demo/ChartPrimitives.vue';
import OtpAndCombobox from '@/Pages/Admin/Demo/OtpAndCombobox.vue';
import Conversation from '@/Pages/Admin/Demo/Conversation.vue';
import QuestionnaireDemo from '@/Pages/Admin/Demo/QuestionnaireDemo.vue';
import MapMarkers from '@/Pages/Admin/Demo/MapMarkers.vue';
import LandingTemplates from '@/Pages/Admin/Demo/LandingTemplates.vue';

const PAGES: Array<[string, any]> = [
    ['emptyAndItem', EmptyAndItem],
    ['chartPrimitives', ChartPrimitives],
    ['otpAndCombobox', OtpAndCombobox],
    ['conversation', Conversation],
    ['questionnaire', QuestionnaireDemo],
    ['mapMarkers', MapMarkers],
    ['landingTemplates', LandingTemplates],
];

function mountPage(key: string, component: any, locale = 'en') {
    const i18n = createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages: { en, ms: en, zh: en },
    });

    return mount(component, {
        props: (fixture as Record<string, any>)[key],
        attachTo: document.body,
        global: { plugins: [i18n, ziggy] },
    });
}

describe('v2.6 component demos — a11y baseline', () => {
    it('covers every page the registry declares', () => {
        expect(PAGES.map(([key]) => key).sort()).toEqual(Object.keys(fixture).sort());
    });

    for (const [key, component] of PAGES) {
        it(`${key} clears R1–R5 with no waivers`, async () => {
            const w = mountPage(key, component);
            await flushPromises();

            const findings = auditA11y(w.element as Element);

            expect(findings, findings.map(f => `${f.rule}: ${f.detail}`).join('\n')).toEqual([]);

            w.unmount();
        });
    }

    it('gives the chart demo a screen-reader data equivalent for every chart', async () => {
        const w = mountPage('chartPrimitives', ChartPrimitives);
        await flushPromises();

        const tables = w.findAll('table.sr-only');

        expect(tables.length).toBe(w.findAll('.stub-vis').length);
        for (const table of tables) {
            expect(table.find('caption').exists()).toBe(true);
            expect(table.findAll('th[scope="col"]').length).toBeGreaterThan(1);
        }

        w.unmount();
    });

    it('makes the conversation thread a log live region', async () => {
        const w = mountPage('conversation', Conversation);
        await flushPromises();

        const log = w.find('[role="log"]');

        expect(log.exists()).toBe(true);
        expect(log.attributes('aria-live')).toBe('polite');
        expect(log.find('ul[role="list"]').exists()).toBe(true);
        expect(log.findAll('ul[role="list"] > li').length).toBe(fixture.conversation.thread.length);

        w.unmount();
    });

    it('announces a new message rather than appending it silently', async () => {
        const w = mountPage('conversation', Conversation);
        await flushPromises();

        const before = w.findAll('[role="log"] ul[role="list"] > li').length;

        await w.find('#conversation-draft').setValue('Queued.');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(w.findAll('[role="log"] ul[role="list"] > li').length).toBe(before + 1);
        expect(w.find('[role="log"]').text()).toContain('Queued.');

        w.unmount();
    });

    it('offers the map demo as a focusable text list, not only a canvas', async () => {
        const w = mountPage('mapMarkers', MapMarkers);
        await flushPromises();

        const rows = w.findAll('ul[role="list"] li button');

        expect(rows.length).toBe(fixture.mapMarkers.markers.length);

        await rows[1].trigger('click');

        expect(rows[1].attributes('aria-current')).toBe('true');
        expect(w.find('[role="status"]').text()).toContain(fixture.mapMarkers.markers[1].name);

        w.unmount();
    });

    it('labels the OTP group and reports progress politely', async () => {
        const w = mountPage('otpAndCombobox', OtpAndCombobox);
        await flushPromises();

        expect(w.find('#otp-label').exists()).toBe(true);
        expect(w.find('[role="status"]').attributes('aria-live')).toBe('polite');

        w.unmount();
    });

    it('degrades to an Empty landmark rather than a blank list', async () => {
        const w = mountPage('emptyAndItem', EmptyAndItem);
        await flushPromises();

        for (const button of w.findAll('button')) {
            if ((button.attributes('aria-label') ?? '').startsWith('Remove')) {
                await button.trigger('click');
            }
        }
        await flushPromises();

        expect(w.text()).toContain(en.gallery.componentDemos.emptyAndItem.emptyTitle);

        w.unmount();
    });

    it('ships no hardcoded English — the Chinese mount differs from the English one', async () => {
        const zhMessages = (await import('@/i18n/locales/zh.json')).default;
        const i18n = createI18n({ legacy: false, locale: 'zh', fallbackLocale: 'en', messages: { zh: zhMessages, en } });

        const w = mount(EmptyAndItem, {
            props: (fixture as Record<string, any>).emptyAndItem,
            global: { plugins: [i18n, ziggy] },
        });
        await flushPromises();

        expect(w.text()).toContain((zhMessages as any).gallery.demos.emptyAndItem.title);
        expect(w.text()).not.toContain(en.gallery.demos.emptyAndItem.title);

        w.unmount();
    });
});
