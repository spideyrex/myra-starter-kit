import type { Component } from 'vue';

export type { FieldSchema, LayoutSchema, SchemaItem } from '@/composables/useFormSchema';

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
    label: string;
    icon?: Component;
    permission?: string;
    href?: string;
    onClick?: () => void;
    destructive?: boolean;
    separator?: boolean;
    show?: boolean;
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

export interface ToggleColumnSchema extends ColumnSchemaBase {
    type: 'toggle';
    onUpdateFn?: (row: any, value: boolean) => void;
}

export type ColumnSchema =
    | TextColumnSchema
    | BadgeColumnSchema
    | DateColumnSchema
    | BooleanColumnSchema
    | ImageColumnSchema
    | IconColumnSchema
    | ToggleColumnSchema;

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

export type FilterSchema = SelectFilterSchema | TernaryFilterSchema | CheckboxFilterSchema;

// --- Table Action Schema ---

export interface ActionSchema {
    label: string;
    icon?: Component;
    color?: string;
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
    deleteRouteName?: string;
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
