import type { Component } from 'vue';

export type { FieldSchema, LayoutSchema, SchemaItem } from '@/composables/useFormSchema';
export type { SummaryType, SummaryConfig } from '@/composables/useTableSchema';
import type { SummaryType, SummaryConfig } from '@/composables/useTableSchema';
import type { ModalConfig } from '@/composables/useTableActions';

export interface FormFieldProps {
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
}

export interface SelectOption {
    label: string;
    value: string;
}

export interface RowAction {
    /** 'action' (default), 'divider', 'section' heading, or a nested 'group' submenu. */
    kind?: 'action' | 'divider' | 'section' | 'group';
    label: string;
    icon?: Component;
    permission?: string;
    href?: string;
    onClick?: () => void;
    destructive?: boolean;
    separator?: boolean;
    show?: boolean;
    color?: SemanticColor | string;
    /** Renders an <a target="_blank"> instead of an Inertia <Link>. */
    external?: boolean;
    badge?: string | number | null;
    tooltip?: string;
    /** Only for kind: 'group'. */
    items?: RowAction[];
    /** Trigger configuration for kind: 'group'. */
    group?: RowActionsConfig;
}

/** Trigger + menu configuration produced by ActionGroup. */
export interface RowActionsConfig {
    label: string;
    icon?: Component;
    color?: SemanticColor | string;
    size: 'sm' | 'default' | 'lg';
    asButton: boolean;
    buttonGroup: boolean;
    tooltip?: string;
    badge?: string | number | null;
    placement: 'bottom-start' | 'bottom-end' | 'top-start' | 'top-end';
    width: 'sm' | 'md' | 'lg';
    maxHeight: string;
    collapseAfter?: number;
}

export interface SimpleTableColumn {
    key: string;
    label: string;
    class?: string;
}

// --- Table Column Schema ---

export interface ColumnSchemaBase {
    key: string;
    label: string;
    type: string;
    sortable: boolean;
    searchable: boolean;
    hidden: boolean;
    alignRight: boolean;
    class?: string;
    tooltip?: string;
    toggleable: boolean;
    grow: boolean;
    summarize?: SummaryType;
    summaryFn?: (values: any[]) => string | number;
    summaryConfig?: SummaryConfig;
}

/** Keys every inline-editable column carries (toggle, checkbox, select, textinput). */
export interface InlineEditableSchema<V = any> {
    updateRoute?: string;
    updateField?: string;
    optimistic?: boolean;
    permission?: string;
    disabledFn?: (row: any) => boolean;
    confirmFn?: (row: any, value: V) => string | false;
    rowLabelFn?: (row: any) => string;
    debounceMs?: number;
    onUpdateFn?: (row: any, value: V) => void;
}

export interface TextColumnSchema extends ColumnSchemaBase {
    type: 'text';
    limit?: number;
    urlFn?: (row: any) => string;
    copyable?: boolean;
    formatFn?: (value: any, row: any) => string;
    descriptionFn?: (row: any) => string;
    defaultValue?: string;
    prefix?: string;
    suffix?: string;
    currency?: string;
    decimals?: number;
    wrap?: boolean;
}

export interface BadgeColumnSchema extends ColumnSchemaBase {
    type: 'badge';
    colors: Record<string, string>;
}

export interface DateColumnSchema extends ColumnSchemaBase {
    type: 'date';
    dateFormat: 'date' | 'datetime' | 'relative';
}

export interface BooleanColumnSchema extends ColumnSchemaBase {
    type: 'boolean';
    trueIcon?: Component;
    falseIcon?: Component;
    trueColor: string;
    falseColor: string;
}

export interface ImageColumnSchema extends ColumnSchemaBase {
    type: 'image';
    circular: boolean;
    imageSize: number;
    defaultUrl?: string;
}

export interface IconColumnSchema extends ColumnSchemaBase {
    type: 'icon';
    iconFn?: (value: any, row: any) => Component;
    colorFn?: (value: any, row: any) => string;
}

export interface ToggleColumnSchema extends ColumnSchemaBase, InlineEditableSchema<boolean> {
    type: 'toggle';
}

export interface CheckboxColumnSchema extends ColumnSchemaBase, InlineEditableSchema<boolean> {
    type: 'checkbox';
    indeterminateFn?: (row: any) => boolean;
}

export interface SelectColumnSchema extends ColumnSchemaBase, InlineEditableSchema<string> {
    type: 'select';
    options: Array<{ label: string; value: string }>;
    placeholder?: string;
}

export interface TextInputColumnSchema extends ColumnSchemaBase, InlineEditableSchema<string> {
    type: 'textinput';
    placeholder?: string;
}

export interface ColorColumnSchema extends ColumnSchemaBase {
    type: 'color';
    copyable: boolean;
    /** Undefined falls back to the `common.copiedToClipboard` translation. */
    copyMessage?: string;
    swatchShowValue: boolean;
    swatchSize: number;
    swatchShape: 'square' | 'circle';
}

export type ColumnSchema =
    | TextColumnSchema
    | BadgeColumnSchema
    | DateColumnSchema
    | BooleanColumnSchema
    | ImageColumnSchema
    | IconColumnSchema
    | ToggleColumnSchema
    | CheckboxColumnSchema
    | SelectColumnSchema
    | TextInputColumnSchema
    | ColorColumnSchema;

// --- Table Filter Schema ---

export interface FilterSchemaBase {
    name: string;
    label: string;
    type: string;
}

export interface SelectFilterSchema extends FilterSchemaBase {
    type: 'select';
    options?: Array<{ label: string; value: string }>;
    placeholder?: string;
    multiple?: boolean;
}

export interface TernaryFilterSchema extends FilterSchemaBase {
    type: 'ternary';
    trueLabel?: string;
    falseLabel?: string;
}

export interface CheckboxFilterSchema extends FilterSchemaBase {
    type: 'checkbox';
    query?: string;
}

export interface DateRangeFilterSchema extends FilterSchemaBase {
    type: 'date-range';
    minDate?: string;
    maxDate?: string;
}

export interface QueryBuilderFieldDef {
    name: string;
    label: string;
    operators: string[];
}

export interface QueryBuilderFilterSchema extends FilterSchemaBase {
    type: 'query-builder';
    fields: QueryBuilderFieldDef[];
}

export interface QueryRule {
    field: string;
    operator: string;
    value: string | string[] | [string, string] | null;
}

export interface QueryGroup {
    conjunction: 'and' | 'or';
    rules: QueryRule[];
    groups: QueryGroup[];
}

export type FilterSchema = SelectFilterSchema | TernaryFilterSchema | CheckboxFilterSchema | DateRangeFilterSchema | QueryBuilderFilterSchema;

// --- Table Action Schema ---

/** Semantic colour tokens. Mirrors SemanticColor in useFormSchema. */
export type SemanticColor = 'muted' | 'primary' | 'info' | 'success' | 'warning' | 'danger';

export interface ActionSchema {
    /** Defaults to 'action'. */
    kind?: 'action' | 'divider' | 'section';
    label: string;
    icon?: Component;
    color?: SemanticColor | string;
    urlFn?: (row: any) => string;
    actionFn?: (row: any) => void;
    requiresConfirmation: boolean;
    confirmTitle?: string;
    confirmDescription?: string;
    permission?: string;
    destructive: boolean;
    hiddenFn?: (row: any) => boolean;
    visibleFn?: (row: any) => boolean;
    separator: boolean;
    external?: boolean;
    tooltip?: string;
    badgeFn?: (row: any) => string | number | null;
    // Generic route path — supersedes deleteRouteName.
    routeName?: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    routeParamsFn?: (row: any) => any;
    payloadFn?: (row: any) => Record<string, any>;
    successMessage?: string;
    /** BC alias for { routeName, method: 'delete' }. */
    deleteRouteName?: string;
    modalConfig?: ModalConfig;
}

export interface ActionGroupSchema {
    kind: 'group';
    label: string;
    icon?: Component;
    color?: SemanticColor | string;
    size: 'sm' | 'default' | 'lg';
    asButton: boolean;
    buttonGroup: boolean;
    tooltip?: string;
    badgeFn?: (row: any) => string | number | null;
    placement: 'bottom-start' | 'bottom-end' | 'top-start' | 'top-end';
    width: 'sm' | 'md' | 'lg';
    maxHeight: string;
    collapseAfter?: number;
    permission?: string;
    visibleFn?: (row: any) => boolean;
    hiddenFn?: (row: any) => boolean;
    items: Array<ActionSchema | ActionGroupSchema>;
}

export interface BulkActionSchema {
    label: string;
    actionFn?: (ids: number[]) => void;
    requiresConfirmation: boolean;
    confirmTitle?: string;
    confirmDescription?: string;
    deselectAfter: boolean;
    icon?: Component;
    permission?: string;
    destructive: boolean;
}
