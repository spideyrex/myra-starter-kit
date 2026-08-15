import { describe, expect, it } from 'vitest';
import {
    BooleanConstraint,
    DateConstraint,
    NumberConstraint,
    QueryBuilderFilter,
    RelationConstraint,
    SelectConstraint,
    TextConstraint,
} from '@/composables/useTableFilters';
import { BadgeColumn, BooleanColumn, DateColumn, TextColumn } from '@/composables/useTableSchema';

describe('Constraint schemas', () => {
    it('keeps contains opt-in on text', () => {
        expect(TextConstraint.make('name').toSchema().operators).not.toContain('contains');
        expect(TextConstraint.make('name').contains().toSchema().operators).toContain('contains');
        expect(TextConstraint.make('name').contains().toSchema().operators).toContain('not_contains');
    });

    it('narrows the operator set with operators() and widens it with pushOperators()', () => {
        expect(NumberConstraint.make('price').operators(['eq', 'between']).toSchema().operators)
            .toEqual(['eq', 'between']);
        expect(BooleanConstraint.make('active').pushOperators(['is_filled']).toSchema().operators)
            .toEqual(['is_true', 'is_false', 'is_filled']);
    });

    it('carries the type-specific extras', () => {
        expect(NumberConstraint.make('qty').integer().toSchema().integer).toBe(true);
        expect(DateConstraint.make('at').withTime().toSchema().withTime).toBe(true);
        expect(SelectConstraint.make('s').options({ a: 'A' }).toSchema().options)
            .toEqual([{ label: 'A', value: 'a' }]);
        expect(RelationConstraint.make('roles').searchRoute('admin.roles.index').toSchema().searchable)
            .toBe(true);
    });

    it('prefers labelKey over a humanised name', () => {
        const schema = TextConstraint.make('first_name').labelKey('filters.field.name').toSchema();
        expect(schema.labelKey).toBe('filters.field.name');
        expect(schema.label).toBe('First Name');
    });
});

describe('QueryBuilderFilter', () => {
    it('defaults the caps to 25 rules and 3 levels', () => {
        const schema = QueryBuilderFilter.make('q').constraints([TextConstraint.make('name')]).toSchema() as any;
        expect(schema.maxRules).toBe(25);
        expect(schema.maxDepth).toBe(3);
        expect(schema.deferred).toBe(true);
    });

    it('derives constraints from column schemas', () => {
        const schema = QueryBuilderFilter.make('q').fromColumns([
            TextColumn.make('name').label('Name'),
            TextColumn.make('price').label('Price').money(),
            DateColumn.make('created_at').label('Created'),
            BooleanColumn.make('is_active').label('Active'),
            BadgeColumn.make('status').label('Status').colors({ active: 'default', draft: 'secondary' }),
        ]).toSchema() as any;

        const byName = Object.fromEntries(schema.constraints.map((c: any) => [c.name, c]));

        expect(byName.name.type).toBe('text');
        expect(byName.name.operators).toContain('contains');
        expect(byName.price.type).toBe('number');
        expect(byName.created_at.type).toBe('date');
        expect(byName.is_active.type).toBe('boolean');
        expect(byName.status.type).toBe('select');
        expect(byName.status.options.map((o: any) => o.value)).toEqual(['active', 'draft']);
    });

    it('still compiles the deprecated fields() shim', () => {
        const schema = QueryBuilderFilter.make('q')
            .fields([{ name: 'name', label: 'Name', operators: ['eq'] }])
            .toSchema() as any;

        expect(schema.fields).toHaveLength(1);
        expect(schema.constraints[0]).toMatchObject({ name: 'name', type: 'text', operators: ['eq'] });
    });
});
