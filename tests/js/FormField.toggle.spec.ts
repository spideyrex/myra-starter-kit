import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FormField from '@/components/admin/FormField.vue';
import { ToggleButtons } from '@/composables/useFormSchema';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
    Link: { name: 'Link', render: () => null },
}));

function toggleProps(field: ToggleButtons, extra: Record<string, any> = {}) {
    return { ...field.toProps(), ...extra };
}

describe('FormField toggle-group branch', () => {
    it('renders one item per option', () => {
        const wrapper = mount(FormField, {
            props: toggleProps(ToggleButtons.make('status').options({ draft: 'Draft', live: 'Live' })),
        });
        expect(wrapper.findAll('button[data-slot="toggle-group-item"]').length).toBe(2);
    });

    it('accepts a scalar model for a multiple toggle and emits an array', async () => {
        const wrapper = mount(FormField, {
            props: toggleProps(
                ToggleButtons.make('channels').options({ email: 'Email', sms: 'SMS' }).multiple(),
                { modelValue: 'email' },
            ),
        });

        const items = wrapper.findAll('button[data-slot="toggle-group-item"]');
        await items[1].trigger('click');

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(Array.isArray(emitted![emitted!.length - 1][0])).toBe(true);
    });

    it('hideLabels() gives every item a non-empty accessible name', () => {
        const wrapper = mount(FormField, {
            props: toggleProps(
                ToggleButtons.make('theme').options({ light: 'Light', dark: 'Dark' }).hideLabels(),
            ),
        });

        const items = wrapper.findAll('button[data-slot="toggle-group-item"]');
        expect(items.length).toBe(2);
        for (const item of items) {
            expect(item.attributes('aria-label')).toBeTruthy();
        }
    });

    it('disables an unselected option once toggleMax is reached', () => {
        const wrapper = mount(FormField, {
            props: toggleProps(
                ToggleButtons.make('channels')
                    .options({ email: 'Email', sms: 'SMS', push: 'Push' })
                    .multiple()
                    .max(2),
                { modelValue: ['email', 'sms'] },
            ),
        });

        const items = wrapper.findAll('button[data-slot="toggle-group-item"]');
        expect(items[2].attributes('disabled')).toBeDefined();
        expect(items[0].attributes('disabled')).toBeUndefined();
    });

    it('renders option descriptions alongside the label', () => {
        const wrapper = mount(FormField, {
            props: toggleProps(
                ToggleButtons.make('status').options([
                    { value: 'draft', label: 'Draft', description: 'Only you can see it' },
                ]),
            ),
        });
        expect(wrapper.text()).toContain('Only you can see it');
    });
});
