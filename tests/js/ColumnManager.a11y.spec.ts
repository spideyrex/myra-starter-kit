import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ColumnManager from '@/components/admin/ColumnManager.vue';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, unknown>) =>
            params ? `${key}:${JSON.stringify(params)}` : key,
    }),
}));

const entries = [
    { key: 'id', label: 'ID', visible: true },
    { key: 'name', label: 'Name', visible: true },
    { key: 'email', label: 'Email', visible: false },
];

function mountManager() {
    return mount(ColumnManager, {
        props: { entries, isDefault: false, defaultOpen: true },
        global: {
            stubs: {
                Popover: { template: '<div><slot /></div>' },
                PopoverTrigger: { template: '<div><slot /></div>' },
                PopoverContent: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('ColumnManager accessibility', () => {
    it('renders the list as a fieldset with an sr-only legend', () => {
        const wrapper = mountManager();

        const legend = wrapper.get('fieldset legend');
        expect(legend.classes()).toContain('sr-only');
        expect(legend.text()).toBe('columns.a11y.list');
    });

    it('gives every row checkbox an accessible name through a matching label', () => {
        const wrapper = mountManager();

        for (const entry of entries) {
            const label = wrapper.get(`label[for="cm-${entry.key}"]`);
            expect(label.text()).toBe(entry.label);
            expect(wrapper.find(`#cm-${entry.key}`).exists()).toBe(true);
        }
    });

    it('names the icon-only drag handle', () => {
        const wrapper = mountManager();

        const labels = wrapper.findAll('button[aria-label]').map(b => b.attributes('aria-label') ?? '');
        for (const entry of entries) {
            expect(labels.some(l => l.startsWith(`${entry.label}:`))).toBe(true);
        }
    });

    it('moves a row on Alt+ArrowDown and writes to the polite live region', async () => {
        const wrapper = mountManager();

        await wrapper.findAll('li')[0].trigger('keydown', { key: 'ArrowDown', altKey: true });

        expect(wrapper.emitted('move')?.[0]).toEqual(['id', 1]);

        const live = wrapper.get('[aria-live="polite"]');
        expect(live.attributes('aria-atomic')).toBe('true');
        expect(live.text()).toContain('columns.a11y.moved');
    });

    it('moves a row up on Alt+ArrowUp and ignores a bare arrow key', async () => {
        const wrapper = mountManager();

        await wrapper.findAll('li')[1].trigger('keydown', { key: 'ArrowUp', altKey: true });
        expect(wrapper.emitted('move')?.[0]).toEqual(['name', -1]);

        await wrapper.findAll('li')[1].trigger('keydown', { key: 'ArrowUp' });
        expect(wrapper.emitted('move')).toHaveLength(1);
    });

    it('filters the list by the search box without losing labels', async () => {
        const wrapper = mountManager();

        await wrapper.get('input[type="search"]').setValue('ema');

        expect(wrapper.findAll('li')).toHaveLength(1);
        expect(wrapper.get('label[for="cm-email"]').text()).toBe('Email');
    });
});
