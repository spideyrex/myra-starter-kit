import { beforeAll, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { exampleI18n, installExampleEnv } from './helpers/exampleEnv';
import en from '@/i18n/locales/en.json';
import FormsPage from '@/examples/forms/Page.vue';
import ProfileForm from '@/examples/forms/components/ProfileForm.vue';

beforeAll(() => installExampleEnv());

const flush = async () => {
    for (let i = 0; i < 6; i++) await nextTick();
    await new Promise(resolve => setTimeout(resolve, 0));
    for (let i = 0; i < 6; i++) await nextTick();
};

describe('forms example accessibility', () => {
    it('wires every control through the form primitive, so the label owns the id', () => {
        const w = mount(ProfileForm, { global: { plugins: [exampleI18n()] } });

        const controls = w.findAll('input, textarea, select');
        expect(controls.length).toBeGreaterThan(0);

        for (const control of controls) {
            const id = control.attributes('id');
            expect(id, 'a form control rendered without an id the label can point at').toBeTruthy();
            expect(w.find(`label[for="${id}"]`).exists(), `no label for #${id}`).toBe(true);
            expect(control.attributes('aria-describedby')).toBeTruthy();
        }
    });

    it('marks a control invalid and describes the error through the primitive', async () => {
        const w = mount(ProfileForm, { global: { plugins: [exampleI18n()] } });

        await w.find('form').trigger('submit');
        await flush();

        const invalid = w.findAll('[aria-invalid="true"]');
        expect(invalid.length).toBeGreaterThan(0);

        const describedBy = invalid[0].attributes('aria-describedby') ?? '';
        expect(describedBy.split(/\s+/).length).toBeGreaterThan(1);
    });

    it('moves focus to the first invalid control when submit fails', async () => {
        const w = mount(ProfileForm, {
            attachTo: document.body,
            global: { plugins: [exampleI18n()] },
        });

        await w.find('form').trigger('submit');
        await flush();

        const first = w.findAll('[aria-invalid="true"]')[0];

        expect(first).toBeTruthy();
        expect(document.activeElement).toBe(first.element);

        w.unmount();
    });

    it('navigates the five panes with real buttons and announces the current one', async () => {
        const w = mount(FormsPage, { global: { plugins: [exampleI18n()] } });

        const items = w.findAll('nav ul[role="list"] > li button');
        expect(items).toHaveLength(5);

        expect(w.findAll('nav button[aria-current="true"]')).toHaveLength(1);

        await items[2].trigger('click');

        expect(w.find('nav button[aria-current="true"]').text()).toBe(en.examples.forms.panes.appearance.title);
        expect(w.text()).toContain(en.examples.forms.appearance.font);
    });

    it('ships no hardcoded English — the chrome comes from t()', () => {
        const chinese = mount(FormsPage, { global: { plugins: [exampleI18n('zh')] } });

        expect(chinese.find('h1').text()).not.toBe(en.examples.forms.title);
        expect(chinese.text()).not.toContain(en.examples.forms.panes.profile.title);
    });
});
