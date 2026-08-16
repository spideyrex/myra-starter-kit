import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, put: vi.fn() }),
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

const route = (name: string) => `/${name.replace(/\./g, '/')}`;
(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import zh from '@/i18n/locales/zh.json';
// THE REAL PAYLOAD: the template schemas the server ships, written by
// tests/Feature/Demo/ComponentDemoFixtureTest from TemplateRegistry.
import fixture from './fixtures/component-demos.json';
import TemplatePicker from '@/components/admin/TemplatePicker.vue';
import { auditA11y } from './helpers/a11yBaseline';

const templates = fixture.landingTemplates.templates;

function mountPicker(modelValue = 'classic', locale = 'en') {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, zh } });

    return mount(TemplatePicker, {
        props: { templates, modelValue } as any,
        attachTo: document.body,
        global: { plugins: [i18n, ziggy] },
    });
}

describe('TemplatePicker', () => {
    it('is a real radio group, not a grid of clickable cards', async () => {
        const w = mountPicker();
        await flushPromises();

        const group = w.find('[role="radiogroup"]');

        expect(group.exists()).toBe(true);
        expect(group.attributes('aria-label')).toBe(en.landing.picker.label);
        expect(w.findAll('[role="radio"]').length).toBe(templates.length);
    });

    it('associates a label and a description with every option', async () => {
        const w = mountPicker();
        await flushPromises();

        for (const template of templates) {
            const radio = w.find(`#template-${template.key}`);

            expect(radio.exists(), `no radio for ${template.key}`).toBe(true);
            expect(radio.attributes('aria-describedby')).toBe(`template-${template.key}-description`);

            const label = w.find(`label[for="template-${template.key}"]`);

            expect(label.exists()).toBe(true);
            expect(label.text()).toBe(en.landing.templates[template.key as keyof typeof en.landing.templates].title);
            expect(w.find(`#template-${template.key}-description`).text()).not.toBe('');
        }
    });

    it('marks exactly one option checked and moves it with the model', async () => {
        const w = mountPicker('docs');
        await flushPromises();

        const checked = w.findAll('[role="radio"]').filter(r => r.attributes('aria-checked') === 'true');

        expect(checked).toHaveLength(1);
        expect(checked[0].attributes('id')).toBe('template-docs');
    });

    it('emits the picked key rather than mutating the prop', async () => {
        const w = mountPicker();
        await flushPromises();

        await w.find('#template-minimal').trigger('click');

        expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['minimal']);
    });

    it('treats thumbnails as decorative — the label carries the name', async () => {
        const w = mountPicker();
        await flushPromises();

        const images = w.findAll('img');

        expect(images.length).toBeGreaterThan(0);
        for (const img of images) {
            expect(img.attributes('alt')).toBe('');
        }
    });

    it('clears the R1–R5 baseline', async () => {
        const w = mountPicker();
        await flushPromises();

        const findings = auditA11y(w.element as Element);

        expect(findings, findings.map(f => `${f.rule}: ${f.detail}`).join('\n')).toEqual([]);
    });

    it('ships no hardcoded English', async () => {
        const w = mountPicker('classic', 'zh');
        await flushPromises();

        expect(w.text()).toContain(zh.landing.templates.classic.title);
        expect(w.text()).not.toContain(en.landing.templates.classic.description);
    });
});
