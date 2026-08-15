import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FormField from '@/components/admin/FormField.vue';
import { CheckboxList, ToggleButtons } from '@/composables/useFormSchema';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
    Link: { name: 'Link', render: () => null },
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, args?: Record<string, any>) => (args ? `${key}:${args.min}-${args.max}` : key),
    }),
}));

function mountToggle(modelValue: any) {
    return mount(FormField, {
        props: {
            ...ToggleButtons.make('channels')
                .options({ email: 'Email', sms: 'SMS', push: 'Push' })
                .multiple()
                .min(2)
                .max(3)
                .toProps(),
            modelValue,
        },
    });
}

describe('toggle min/max advisory message', () => {
    it('marks the group invalid and describes it when the selection is short', () => {
        const wrapper = mountToggle(['email']);

        const group = wrapper.get('[data-slot="toggle-group"]');
        expect(group.attributes('aria-invalid')).toBe('true');

        const message = wrapper.get('#field-channels-selection');
        expect(message.attributes('aria-live')).toBe('polite');
        expect(message.text()).toBe('validation.selectBetween:2-3');
        expect(group.attributes('aria-describedby')).toContain('field-channels-selection');
    });

    it('says nothing once the selection is in range', () => {
        const wrapper = mountToggle(['email', 'sms']);

        expect(wrapper.find('#field-channels-selection').exists()).toBe(false);
        expect(wrapper.get('[data-slot="toggle-group"]').attributes('aria-invalid')).toBe('false');
    });

    it('keeps the hint in aria-describedby alongside the advisory', () => {
        const wrapper = mount(FormField, {
            props: {
                ...ToggleButtons.make('channels')
                    .options({ email: 'Email', sms: 'SMS' })
                    .multiple()
                    .min(1)
                    .toProps(),
                hint: 'Pick at least one.',
                modelValue: [],
            },
        });

        const describedBy = wrapper.get('[data-slot="toggle-group"]').attributes('aria-describedby') ?? '';
        expect(describedBy).toContain('field-channels-hint');
        expect(describedBy).toContain('field-channels-selection');
    });

    it('applies the same wiring to a checkbox list', () => {
        const wrapper = mount(FormField, {
            props: {
                ...CheckboxList.make('tags')
                    .options({ a: 'A', b: 'B', c: 'C' })
                    .min(2)
                    .max(3)
                    .toProps(),
                modelValue: ['a'],
            },
        });

        const list = wrapper.get('[aria-describedby="field-tags-selection"]');
        expect(list.attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#field-tags-selection').text()).toBe('validation.selectBetween:2-3');
    });
});
