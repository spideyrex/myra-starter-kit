import type { Component } from 'vue';

/** Mirrors App\Admin\QueryBuilder\Operator. The server enum is the authority. */
export type OperatorKey =
    | 'eq' | 'neq'
    | 'contains' | 'not_contains'
    | 'starts_with' | 'ends_with'
    | 'gt' | 'gte' | 'lt' | 'lte'
    | 'between'
    | 'in' | 'not_in'
    | 'is_filled' | 'is_blank'
    | 'is_true' | 'is_false'
    | 'date_is' | 'date_before' | 'date_after' | 'date_between'
    | 'in_month' | 'in_year'
    | 'related_to' | 'not_related_to'
    | 'count_gte' | 'count_lte' | 'count_eq';

export type ConstraintType = 'text' | 'number' | 'boolean' | 'date' | 'select' | 'relation';

export type QueryRuleValue = string | string[] | [string, string] | null;

export interface QueryConstraintSchema {
    name: string;
    type: ConstraintType;
    label: string;
    labelKey?: string;
    icon?: Component;
    operators: OperatorKey[];
    options?: Array<{ label: string; value: string }>;
    nullable?: boolean;
    integer?: boolean;
    multiple?: boolean;
    searchable?: boolean;
    searchRoute?: string;
    titleAttribute?: string;
    withTime?: boolean;
    permission?: string;
}

/** Operand count per operator: 0 none, 1 single, 2 a pair, -1 a list. */
export type OperatorArity = 0 | 1 | 2 | -1;
