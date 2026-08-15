import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FormField from '@/components/admin/FormField.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
    Link: { name: 'Link', render: () => null },
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key }),
}));

describe('FormField accessibility wiring', () => {
    it('points aria-describedby at both the hint and the error, and marks the control invalid', () => {
        const wrapper = mount(FormField, {
            props: {
                label: 'Slug',
                name: 'slug',
                type: 'text',
                hint: 'Lowercase, hyphen-separated.',
                error: 'The slug is already taken.',
            },
        });

        const input = wrapper.get('input');
        const describedBy = input.attributes('aria-describedby') ?? '';
        expect(describedBy).toContain('field-slug-hint');
        expect(describedBy).toContain('field-slug-error');
        expect(input.attributes('aria-invalid')).toBe('true');

        expect(wrapper.get('#field-slug-hint').text()).toBe('Lowercase, hyphen-separated.');
        const error = wrapper.get('#field-slug-error');
        expect(error.attributes('role')).toBe('alert');
        expect(error.text()).toBe('The slug is already taken.');
    });

    it('renders hint AND error together — the error no longer suppresses the hint', () => {
        const wrapper = mount(FormField, {
            props: { label: 'Slug', name: 'slug', hint: 'Helper', error: 'Boom' },
        });
        expect(wrapper.find('#field-slug-hint').exists()).toBe(true);
        expect(wrapper.find('#field-slug-error').exists()).toBe(true);
    });

    it('omits aria-describedby when there is neither hint nor error', () => {
        const wrapper = mount(FormField, { props: { label: 'Slug', name: 'slug' } });
        expect(wrapper.get('input').attributes('aria-describedby')).toBeUndefined();
        expect(wrapper.get('input').attributes('aria-invalid')).toBe('false');
    });

    it('renders the error for a checkbox field (regression guard for the v-else move)', () => {
        const wrapper = mount(FormField, {
            props: { label: 'Accept terms', name: 'accept', type: 'checkbox', error: 'Required.', hint: 'Please read them.' },
        });
        const error = wrapper.get('#field-accept-error');
        expect(error.attributes('role')).toBe('alert');
        expect(wrapper.find('#field-accept-hint').exists()).toBe(true);
    });

    it('renders the error for a switch field', () => {
        const wrapper = mount(FormField, {
            props: { label: 'Newsletter', name: 'newsletter', type: 'switch', error: 'Nope.' },
        });
        expect(wrapper.find('#field-newsletter-error').exists()).toBe(true);
    });

    it('gives the label a stable id for controls that cannot be labelled natively', () => {
        const wrapper = mount(FormField, { props: { label: 'Payload', name: 'payload' } });
        expect(wrapper.get('label').attributes('id')).toBe('field-payload-label');
    });

    it('runs a hintAction against the owning form', async () => {
        const form: Record<string, any> = { title: 'Hello World', slug: '' };
        const wrapper = mount(FormField, {
            props: {
                label: 'Slug',
                name: 'slug',
                hint: 'Derived from the title.',
                hintAction: { label: 'Regenerate', onClick: (f: Record<string, any>) => { f.slug = 'hello-world'; } },
                form,
            },
        });

        await wrapper.get('button').trigger('click');
        expect(form.slug).toBe('hello-world');
    });
});
