import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import QueryBuilderGroup from '@/components/admin/QueryBuilderGroup.vue';
import {
    OPERATORS_BY_TYPE,
    countQueryRules,
    emptyValueForOperator,
    operatorArity,
    retargetRuleField,
    retargetRuleOperator,
} from '@/composables/useTableFilters';
import type { QueryConstraintSchema } from '@/types/query-builder';
import { testI18n, uiStubs } from './helpers/i18n';

const constraints: QueryConstraintSchema[] = [
    { name: 'name', type: 'text', label: 'Name', operators: OPERATORS_BY_TYPE.text },
    { name: 'age', type: 'number', label: 'Age', operators: OPERATORS_BY_TYPE.number },
    { name: 'status', type: 'select', label: 'Status', operators: OPERATORS_BY_TYPE.select, options: [{ label: 'Active', value: 'active' }] },
];

function mountGroup(props: Record<string, any> = {}) {
    return mount(QueryBuilderGroup, {
        props: {
            group: { conjunction: 'and', rules: [], groups: [] },
            constraints,
            depth: 0,
            ...props,
        },
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

describe('QueryBuilderGroup nesting caps', () => {
    it('disables Add Group once maxDepth is reached', () => {
        const atLimit = mountGroup({ depth: 2, maxDepth: 3 });
        expect(atLimit.get('[data-testid="qb-add-group"]').attributes('disabled')).toBeDefined();

        const below = mountGroup({ depth: 1, maxDepth: 3 });
        expect(below.get('[data-testid="qb-add-group"]').attributes('disabled')).toBeUndefined();
    });

    it('disables Add Rule once the global rule cap is reached', () => {
        const wrapper = mountGroup({ maxRules: 2, ruleCount: 2 });
        expect(wrapper.get('[data-testid="qb-add-rule"]').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('At most 2 conditions.');
    });

    it('counts rules across the whole tree, not just the current group', () => {
        const tree = {
            conjunction: 'and' as const,
            rules: [{ field: 'name', operator: 'eq', value: 'a' }],
            groups: [
                {
                    conjunction: 'or' as const,
                    rules: [
                        { field: 'name', operator: 'eq', value: 'b' },
                        { field: 'name', operator: 'eq', value: 'c' },
                    ],
                    groups: [],
                },
            ],
        };

        expect(countQueryRules(tree)).toBe(3);
        expect(countQueryRules(null)).toBe(0);
    });
});

describe('QueryBuilderGroup rendering', () => {
    it('takes operator labels from i18n, never the raw enum value', () => {
        const wrapper = mountGroup({
            group: { conjunction: 'and', rules: [{ field: 'name', operator: 'starts_with', value: 'a' }], groups: [] },
        });

        expect(wrapper.text()).toContain('starts with');
        expect(wrapper.text()).not.toContain('starts_with');
    });

    it('renders the match-mode copy from i18n', () => {
        expect(mountGroup().text()).toContain('Match all conditions');
        expect(mountGroup({ group: { conjunction: 'or', rules: [], groups: [] } }).text())
            .toContain('Match any condition');
    });

    it('renders no value input for a zero-arity operator', () => {
        const wrapper = mountGroup({
            group: { conjunction: 'and', rules: [{ field: 'name', operator: 'is_filled', value: null }], groups: [] },
        });

        expect(wrapper.findAll('input')).toHaveLength(0);
    });

    it('renders two value inputs for a two-operand operator', () => {
        const wrapper = mountGroup({
            group: { conjunction: 'and', rules: [{ field: 'age', operator: 'between', value: ['1', '5'] }], groups: [] },
        });

        expect(wrapper.findAll('input')).toHaveLength(2);
    });

    it('emits a new rule with the first operator of the first constraint', async () => {
        const wrapper = mountGroup();
        await wrapper.get('[data-testid="qb-add-rule"]').trigger('click');

        const emitted = wrapper.emitted('update:group')![0][0] as any;
        expect(emitted.rules).toHaveLength(1);
        expect(emitted.rules[0]).toEqual({ field: 'name', operator: 'eq', value: '' });
    });

    it('toggles the conjunction', async () => {
        const wrapper = mountGroup();
        await wrapper.get('button[aria-pressed]').trigger('click');

        expect((wrapper.emitted('update:group')![0][0] as any).conjunction).toBe('or');
    });
});

describe('rule retargeting', () => {
    it('resets an operator the new field does not allow', () => {
        const rule = { field: 'age', operator: 'between' as const, value: ['1', '5'] as [string, string] };
        const next = retargetRuleField(rule, 'status', constraints);

        expect(next.field).toBe('status');
        expect(next.operator).toBe('in');
        expect(next.value).toEqual([]);
    });

    it('keeps a compatible operator and its value when the field changes', () => {
        const rule = { field: 'name', operator: 'is_blank', value: null };
        const next = retargetRuleField(rule, 'age', constraints);

        expect(next.operator).toBe('is_blank');
        expect(next.value).toBeNull();
    });

    it('clears the value when the operand shape changes', () => {
        const rule = { field: 'age', operator: 'eq', value: '5' };
        expect(retargetRuleOperator(rule, 'between').value).toEqual(['', '']);
        expect(retargetRuleOperator(rule, 'neq').value).toBe('5');
    });
});

describe('operator metadata mirrors the server enum', () => {
    it('maps arity the same way Operator::arity() does', () => {
        expect(operatorArity('is_filled')).toBe(0);
        expect(operatorArity('between')).toBe(2);
        expect(operatorArity('in')).toBe(-1);
        expect(operatorArity('eq')).toBe(1);
    });

    it('produces the right empty value per arity', () => {
        expect(emptyValueForOperator('is_true')).toBeNull();
        expect(emptyValueForOperator('date_between')).toEqual(['', '']);
        expect(emptyValueForOperator('not_in')).toEqual([]);
        expect(emptyValueForOperator('lte')).toBe('');
    });

    it('keeps contains out of the default text set', () => {
        expect(OPERATORS_BY_TYPE.text).not.toContain('contains');
        expect(OPERATORS_BY_TYPE.boolean).toEqual(['is_true', 'is_false']);
    });
});
