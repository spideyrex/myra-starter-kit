import type { Component } from 'vue';
import type { FilterSchema, QueryGroup, QueryRule } from '@/types/admin';
import type { ConstraintType, OperatorArity, OperatorKey, QueryConstraintSchema, QueryRuleValue } from '@/types/query-builder';

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

export type FilterType = 'select' | 'ternary' | 'checkbox' | 'date-range' | 'query-builder';

export abstract class BaseFilter {
    protected _name: string;
    protected _label?: string;
    protected _type: FilterType = 'checkbox';

    constructor(name: string) {
        this._name = name;
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            name: this._name,
            label: this._label ?? humanize(this._name),
            type: this._type,
        } as FilterSchema;
    }
}

export class SelectFilter extends BaseFilter {
    protected _type: FilterType = 'select';
    private _options: Array<{ label: string; value: string }> = [];
    private _placeholder?: string;
    private _multiple = false;

    static make(name: string): SelectFilter {
        return new SelectFilter(name);
    }

    options(opts: Array<{ label: string; value: string }> | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    multiple(value = true): this {
        this._multiple = value;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            ...super.toSchema(),
            type: 'select',
            options: this._options,
            placeholder: this._placeholder,
            multiple: this._multiple,
        };
    }
}

/** Matches the `trashed` query contract every *Service::list() implements. */
export function trashedFilter(): SelectFilter {
    return SelectFilter.make('trashed').label('Records').options({
        '': 'Active only',
        only: 'Trashed only',
        with: 'All (include trashed)',
    });
}

export class TernaryFilter extends BaseFilter {
    protected _type: FilterType = 'ternary';
    private _trueLabel = 'Yes';
    private _falseLabel = 'No';

    static make(name: string): TernaryFilter {
        return new TernaryFilter(name);
    }

    trueLabel(text: string): this {
        this._trueLabel = text;
        return this;
    }

    falseLabel(text: string): this {
        this._falseLabel = text;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            ...super.toSchema(),
            type: 'ternary',
            trueLabel: this._trueLabel,
            falseLabel: this._falseLabel,
        };
    }
}

export class Filter extends BaseFilter {
    protected _type: FilterType = 'checkbox';
    private _query?: string;

    static make(name: string): Filter {
        return new Filter(name);
    }

    query(paramName: string): this {
        this._query = paramName;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            ...super.toSchema(),
            type: 'checkbox',
            query: this._query ?? this._name,
        };
    }
}

export class DateRangeFilter extends BaseFilter {
    protected _type: FilterType = 'date-range';
    private _minDate?: string;
    private _maxDate?: string;

    static make(name: string): DateRangeFilter {
        return new DateRangeFilter(name);
    }

    minDate(d: string): this {
        this._minDate = d;
        return this;
    }

    maxDate(d: string): this {
        this._maxDate = d;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            ...super.toSchema(),
            type: 'date-range',
            minDate: this._minDate,
            maxDate: this._maxDate,
        } as FilterSchema;
    }
}

/** @deprecated Superseded by the Constraint classes below. */
export interface QueryBuilderField {
    name: string;
    label: string;
    operators: string[];
}

// --- Query builder constraints ------------------------------------------------

/** Mirrors Operator::forType() on the server. Kept in sync by OperatorTest. */
export const OPERATORS_BY_TYPE: Record<ConstraintType, OperatorKey[]> = {
    text: ['eq', 'neq', 'starts_with', 'ends_with', 'is_filled', 'is_blank'],
    number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_filled', 'is_blank'],
    boolean: ['is_true', 'is_false'],
    date: ['date_is', 'date_before', 'date_after', 'date_between', 'in_month', 'in_year', 'is_filled', 'is_blank'],
    select: ['in', 'not_in', 'is_filled', 'is_blank'],
    relation: ['related_to', 'not_related_to', 'count_gte', 'count_lte', 'count_eq'],
};

/** Mirrors Operator::arity(). */
export function operatorArity(op: OperatorKey | string): OperatorArity {
    if (['is_filled', 'is_blank', 'is_true', 'is_false'].includes(op)) return 0;
    if (['between', 'date_between'].includes(op)) return 2;
    if (['in', 'not_in', 'related_to', 'not_related_to'].includes(op)) return -1;
    return 1;
}

export function emptyValueForOperator(op: string): QueryRuleValue {
    const arity = operatorArity(op);
    if (arity === 0) return null;
    if (arity === 2) return ['', ''];
    if (arity === -1) return [];
    return '';
}

/** Switching field drops an operator the new field does not allow. */
export function retargetRuleField(
    rule: QueryRule,
    field: string,
    constraints: QueryConstraintSchema[],
): QueryRule {
    const next = constraints.find(c => c.name === field);
    const operator = next?.operators.includes(rule.operator as OperatorKey)
        ? rule.operator
        : (next?.operators[0] ?? '');

    return {
        field,
        operator,
        value: operator === rule.operator ? rule.value : emptyValueForOperator(operator),
    };
}

/** Switching operator keeps the value only when the operand shape is unchanged. */
export function retargetRuleOperator(rule: QueryRule, operator: string): QueryRule {
    return {
        ...rule,
        operator,
        value: operatorArity(operator) === operatorArity(rule.operator)
            ? rule.value
            : emptyValueForOperator(operator),
    };
}

/** Total rules across a whole tree — the cap is global, not per group. */
export function countQueryRules(group?: QueryGroup | null): number {
    if (!group) return 0;
    return (group.rules?.length ?? 0) + (group.groups ?? []).reduce((n, g) => n + countQueryRules(g), 0);
}

export abstract class Constraint {
    protected _name: string;
    protected _type: ConstraintType = 'text';
    protected _label?: string;
    protected _labelKey?: string;
    protected _icon?: Component;
    protected _nullable = false;
    protected _operators?: OperatorKey[];
    protected _extraOperators: OperatorKey[] = [];
    protected _permission?: string;

    constructor(name: string) {
        this._name = name;
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    labelKey(key: string): this {
        this._labelKey = key;
        return this;
    }

    icon(icon: Component): this {
        this._icon = icon;
        return this;
    }

    nullable(value = true): this {
        this._nullable = value;
        return this;
    }

    /** Narrow the type's default set. */
    operators(ops: OperatorKey[]): this {
        this._operators = ops;
        return this;
    }

    pushOperators(ops: OperatorKey[]): this {
        this._extraOperators = [...this._extraOperators, ...ops];
        return this;
    }

    permission(ability: string): this {
        this._permission = ability;
        return this;
    }

    resolvedOperators(): OperatorKey[] {
        const base = this._operators ?? OPERATORS_BY_TYPE[this._type];
        return Array.from(new Set([...base, ...this._extraOperators]));
    }

    toSchema(): QueryConstraintSchema {
        return {
            name: this._name,
            type: this._type,
            label: this._label ?? humanize(this._name),
            labelKey: this._labelKey,
            icon: this._icon,
            operators: this.resolvedOperators(),
            nullable: this._nullable,
            permission: this._permission,
        };
    }
}

export class TextConstraint extends Constraint {
    protected _type: ConstraintType = 'text';
    private _contains = false;

    static make(n: string): TextConstraint {
        return new TextConstraint(n);
    }

    /** Opt in to `%term%` — non-indexed, so it is off by default (server mirrors this). */
    contains(v = true): this {
        this._contains = v;
        return this;
    }

    resolvedOperators(): OperatorKey[] {
        const ops = super.resolvedOperators();
        if (!this._contains) return ops;
        const extra: OperatorKey[] = ['contains', 'not_contains'];
        return Array.from(new Set<OperatorKey>([...ops, ...extra]));
    }
}

export class NumberConstraint extends Constraint {
    protected _type: ConstraintType = 'number';
    private _integer = false;

    static make(n: string): NumberConstraint {
        return new NumberConstraint(n);
    }

    integer(v = true): this {
        this._integer = v;
        return this;
    }

    toSchema(): QueryConstraintSchema {
        return { ...super.toSchema(), integer: this._integer };
    }
}

export class BooleanConstraint extends Constraint {
    protected _type: ConstraintType = 'boolean';

    static make(n: string): BooleanConstraint {
        return new BooleanConstraint(n);
    }
}

export class DateConstraint extends Constraint {
    protected _type: ConstraintType = 'date';
    private _withTime = false;

    static make(n: string): DateConstraint {
        return new DateConstraint(n);
    }

    withTime(v = true): this {
        this._withTime = v;
        return this;
    }

    toSchema(): QueryConstraintSchema {
        return { ...super.toSchema(), withTime: this._withTime };
    }
}

export class SelectConstraint extends Constraint {
    protected _type: ConstraintType = 'select';
    private _options: Array<{ label: string; value: string }> = [];
    private _multiple = true;
    private _searchable = false;

    static make(n: string): SelectConstraint {
        return new SelectConstraint(n);
    }

    options(o: Record<string, string> | Array<{ label: string; value: string }>): this {
        this._options = Array.isArray(o)
            ? o
            : Object.entries(o).map(([value, label]) => ({ label, value }));
        return this;
    }

    multiple(v = true): this {
        this._multiple = v;
        return this;
    }

    searchable(v = true): this {
        this._searchable = v;
        return this;
    }

    toSchema(): QueryConstraintSchema {
        return {
            ...super.toSchema(),
            options: this._options,
            multiple: this._multiple,
            searchable: this._searchable,
        };
    }
}

export class RelationConstraint extends Constraint {
    protected _type: ConstraintType = 'relation';
    private _titleAttribute = 'name';
    private _searchRoute?: string;
    private _options: Array<{ label: string; value: string }> = [];

    static make(n: string): RelationConstraint {
        return new RelationConstraint(n);
    }

    titleAttribute(a: string): this {
        this._titleAttribute = a;
        return this;
    }

    searchRoute(routeName: string): this {
        this._searchRoute = routeName;
        return this;
    }

    options(o: Record<string, string> | Array<{ label: string; value: string }>): this {
        this._options = Array.isArray(o)
            ? o
            : Object.entries(o).map(([value, label]) => ({ label, value }));
        return this;
    }

    toSchema(): QueryConstraintSchema {
        return {
            ...super.toSchema(),
            titleAttribute: this._titleAttribute,
            searchRoute: this._searchRoute,
            options: this._options,
            searchable: !!this._searchRoute,
        };
    }
}

/**
 * The client declaration carries NO server authority — App\Admin\QueryBuilder\FieldSet
 * is the whitelist. This shapes the UI only.
 */
export class QueryBuilderFilter extends BaseFilter {
    protected _type: FilterType = 'query-builder';
    private _constraints: Constraint[] = [];
    private _legacyFields: QueryBuilderField[] = [];
    private _maxRules = 25;
    private _maxDepth = 3;
    private _deferred = true;

    static make(name: string): QueryBuilderFilter {
        return new QueryBuilderFilter(name);
    }

    constraints(c: Constraint[]): this {
        this._constraints = c;
        return this;
    }

    /** Derives constraints from the columns you already wrote. */
    fromColumns(cols: any[]): this {
        const derived: Constraint[] = [];

        for (const raw of cols ?? []) {
            const col: any = typeof raw?.toSchema === 'function' ? raw.toSchema() : raw;
            if (!col?.key) continue;
            // No DB backing behind a pure formatter — nothing to compile server-side.
            if (col.formatFn && !col.sortable) continue;

            const label = col.label ?? humanize(col.key);
            const options = col.options ?? (col.colors ? Object.keys(col.colors).map((v: string) => ({ label: v, value: v })) : null);

            if (col.type === 'date') {
                derived.push(DateConstraint.make(col.key).label(label));
            } else if (col.type === 'boolean' || col.type === 'toggle' || col.type === 'checkbox') {
                derived.push(BooleanConstraint.make(col.key).label(label));
            } else if ((col.type === 'badge' || col.type === 'select') && options?.length) {
                derived.push(SelectConstraint.make(col.key).label(label).options(options));
            } else if (col.type === 'text' && (col.currency !== undefined || col.decimals !== undefined)) {
                derived.push(NumberConstraint.make(col.key).label(label));
            } else if (col.type === 'text' || col.type === 'textinput') {
                derived.push(TextConstraint.make(col.key).label(label).contains());
            }
        }

        this._constraints = [...this._constraints, ...derived];
        return this;
    }

    maxRules(n: number): this {
        this._maxRules = n;
        return this;
    }

    maxDepth(n: number): this {
        this._maxDepth = n;
        return this;
    }

    /** Already the behaviour — the tree is only submitted on Apply. Now explicit. */
    deferred(v = true): this {
        this._deferred = v;
        return this;
    }

    /** @deprecated Legacy shim — use constraints()/fromColumns(). */
    fields(f: QueryBuilderField[]): this {
        this._legacyFields = f;
        return this;
    }

    toSchema(): FilterSchema {
        const constraints: QueryConstraintSchema[] = this._constraints.length
            ? this._constraints.map(c => c.toSchema())
            : this._legacyFields.map(f => ({
                name: f.name,
                type: 'text' as ConstraintType,
                label: f.label,
                operators: (f.operators ?? []) as OperatorKey[],
            }));

        return {
            ...super.toSchema(),
            type: 'query-builder',
            constraints,
            maxRules: this._maxRules,
            maxDepth: this._maxDepth,
            deferred: this._deferred,
            // Kept so pages still reading `fields` keep compiling.
            fields: this._legacyFields,
        } as unknown as FilterSchema;
    }
}
