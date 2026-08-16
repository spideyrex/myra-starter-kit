import { describe, expect, it } from 'vitest';
import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { Form, FormControl, FormField, FormItem, FormLabel } from '@/components/ui/form';
import FormNativeSelect from '@/examples/forms/components/FormNativeSelect.vue';
import { LANGUAGES } from '@/examples/forms/data/options';
import { exampleI18n } from './helpers/exampleEnv';

const flush = async () => {
    for (let i = 0; i < 6; i++) await nextTick();
    await new Promise(resolve => setTimeout(resolve, 0));
    for (let i = 0; i < 6; i++) await nextTick();
};

// THE REAL FIELD: a vee-validate Form, not a hand-rolled stub, because the seam
// this component exists for is exactly the one between a field handler and the
// registry's NativeSelect.
const Harness = defineComponent({
    components: { Form, FormControl, FormField, FormItem, FormLabel, FormNativeSelect },
    setup() {
        const submitted = ref<Record<string, unknown> | null>(null);

        return {
            submitted,
            languages: LANGUAGES,
            onSubmit: (values: Record<string, unknown>) => { submitted.value = values; },
        };
    },
    template: `
        <Form :initial-values="{ language: 'en' }" @submit="onSubmit">
            <FormField v-slot="{ value, handleChange }" name="language">
                <FormItem>
                    <FormLabel>Language</FormLabel>
                    <FormControl>
                        <FormNativeSelect :model-value="value" @update:model-value="handleChange">
                            <option v-for="code in languages" :key="code" :value="code">{{ code }}</option>
                        </FormNativeSelect>
                    </FormControl>
                </FormItem>
            </FormField>
            <button type="submit">go</button>
        </Form>
    `,
});

describe('FormNativeSelect', () => {
    it('renders the bound value and emits the chosen one', async () => {
        const w = mount(FormNativeSelect, {
            props: { modelValue: 'ms' },
            slots: { default: LANGUAGES.map(code => `<option value="${code}">${code}</option>`).join('') },
        });

        const select = w.get('select');
        expect((select.element as HTMLSelectElement).value).toBe('ms');

        await select.setValue('de');

        expect(w.emitted('update:modelValue')?.at(-1)).toEqual(['de']);
    });

    it('round-trips a choice into the field the form actually submits', async () => {
        const w = mount(Harness, { global: { plugins: [exampleI18n()] } });

        const select = w.get('select');
        expect((select.element as HTMLSelectElement).value).toBe('en');

        await select.setValue('de');
        await w.get('form').trigger('submit');
        await flush();

        expect(w.vm.submitted).toEqual({ language: 'de' });
    });

    it('keeps the label pointed at the select the form primitive named', () => {
        const w = mount(Harness, { global: { plugins: [exampleI18n()] } });

        const id = w.get('select').attributes('id');

        expect(id).toBeTruthy();
        expect(w.find(`label[for="${id}"]`).exists()).toBe(true);
    });
});
