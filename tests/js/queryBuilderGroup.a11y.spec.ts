import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import QueryBuilderGroup from '@/components/admin/QueryBuilderGroup.vue';
import { OPERATORS_BY_TYPE } from '@/composables/useTableFilters';
import type { QueryConstraintSchema } from '@/types/query-builder';
import { testI18n, uiStubs } from './helpers/i18n';

const constraints: QueryConstraintSchema[] = [
    { name: 'name', type: 'text', label: 'Name', operators: OPERATORS_BY_TYPE.text },
    { name: 'status', type: 'select', label: 'Status', operators: OPERATORS_BY_TYPE.select, options: [{ label: 'Active', value: 'active' }] },
];

function mountGroup(rules: any[] = []) {
    return mount(QueryBuilderGroup, {
        props: {
            group: { conjunction: 'and', rules, groups: [] },
            constraints,
            depth: 0,
        },
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

describe('QueryBuilderGroup accessibility', () => {
    it('renders the conjunction control as a real button with aria-pressed', () => {
        const wrapper = mountGroup();
        const toggle = wrapper.get('button[aria-pressed]');

        expect(toggle.element.tagName).toBe('BUTTON');
        expect(toggle.attributes('type')).toBe('button');
        expect(toggle.attributes('aria-pressed')).toBe('true');
        expect(toggle.attributes('aria-label')).toBe('Toggle match mode');
        expect(toggle.classes().join(' ')).toContain('focus-visible:ring-2');
    });

    it('flips aria-pressed with the conjunction', () => {
        const wrapper = mount(QueryBuilderGroup, {
            props: {
                group: { conjunction: 'or', rules: [], groups: [] },
                constraints,
                depth: 0,
            },
            global: { plugins: [testI18n()], stubs: uiStubs },
        });

        expect(wrapper.get('button[aria-pressed]').attributes('aria-pressed')).toBe('false');
    });

    it('wraps every rule row in a labelled role="group"', () => {
        const wrapper = mountGroup([
            { field: 'name', operator: 'eq', value: 'a' },
            { field: 'name', operator: 'eq', value: 'b' },
        ]);

        const groups = wrapper.findAll('[role="group"]');
        expect(groups).toHaveLength(2);
        expect(groups[0].attributes('aria-label')).toBe('Condition 1');
        expect(groups[1].attributes('aria-label')).toBe('Condition 2');
    });

    it('gives the icon-only delete button an accessible name', () => {
        const wrapper = mountGroup([{ field: 'name', operator: 'eq', value: 'a' }]);

        const remove = wrapper.get('[role="group"] button[aria-label]');
        expect(remove.attributes('aria-label')).toBe('Remove condition');
    });

    it('labels the list-value fieldset for a multi-value operator', () => {
        const wrapper = mountGroup([{ field: 'status', operator: 'in', value: [] }]);

        const legend = wrapper.get('fieldset legend');
        expect(legend.classes()).toContain('sr-only');
        expect(legend.text()).toBe('Values');
    });
});
