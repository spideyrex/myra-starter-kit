import type { Component } from 'vue';
import type { ColumnSchema } from '@/types/admin';

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

export type ColumnType = 'text' | 'badge' | 'date' | 'boolean' | 'image' | 'icon' | 'toggle' | 'select' | 'textinput';

export abstract class BaseColumn {
    protected _key: string;
    protected _label?: string;
    protected _type: ColumnType = 'text';
    protected _sortable = false;
    protected _searchable = false;
    protected _hidden = false;
    protected _alignRight = false;
    protected _class?: string;
    protected _tooltip?: string;
    protected _toggleable = false;
    protected _grow = false;
    protected _summarize?: 'sum' | 'average' | 'count' | 'range' | 'custom';
    protected _summaryFn?: (values: any[]) => string | number;

    constructor(key: string) {
        this._key = key;
    }

    get key(): string {
        return this._key;
    }

    label(text: string): this {
        this._label = text;
        return this;
    }

    sortable(value = true): this {
        this._sortable = value;
        return this;
    }

    searchable(value = true): this {
        this._searchable = value;
        return this;
    }

    hidden(value = true): this {
        this._hidden = value;
        return this;
    }

    visible(value = true): this {
        this._hidden = !value;
        return this;
    }

    alignEnd(): this {
        this._alignRight = true;
        return this;
    }

    extraClass(cls: string): this {
        this._class = cls;
        return this;
    }

    tooltip(text: string): this {
        this._tooltip = text;
        return this;
    }

    toggleable(value = true): this {
        this._toggleable = value;
        return this;
    }

    grow(value = true): this {
        this._grow = value;
        return this;
    }

    summarize(type: 'sum' | 'average' | 'count' | 'range' | 'custom', fn?: (values: any[]) => string | number): this {
        this._summarize = type;
        if (fn) this._summaryFn = fn;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            key: this._key,
            label: this._label ?? humanize(this._key),
            type: this._type as ColumnSchema['type'],
            sortable: this._sortable,
            searchable: this._searchable,
            hidden: this._hidden,
            alignRight: this._alignRight,
            class: this._class,
            tooltip: this._tooltip,
            toggleable: this._toggleable,
            grow: this._grow,
            summarize: this._summarize,
            summaryFn: this._summaryFn,
        } as ColumnSchema;
    }
}

export class TextColumn extends BaseColumn {
    protected _type: ColumnType = 'text';
    private _limit?: number;
    private _urlFn?: (row: any) => string;
    private _copyable = false;
    private _formatFn?: (value: any, row: any) => string;
    private _descriptionFn?: (row: any) => string;
    private _default?: string;
    private _prefix?: string;
    private _suffix?: string;
    private _currency?: string;
    private _decimals?: number;
    private _wrap = false;

    static make(key: string): TextColumn {
        return new TextColumn(key);
    }

    limit(n: number): this {
        this._limit = n;
        return this;
    }

    url(fn: (row: any) => string): this {
        this._urlFn = fn;
        return this;
    }

    copyable(value = true): this {
        this._copyable = value;
        return this;
    }

    formatStateUsing(fn: (value: any, row: any) => string): this {
        this._formatFn = fn;
        return this;
    }

    description(fn: (row: any) => string): this {
        this._descriptionFn = fn;
        return this;
    }

    default(val: string): this {
        this._default = val;
        return this;
    }

    prefix(str: string): this {
        this._prefix = str;
        return this;
    }

    suffix(str: string): this {
        this._suffix = str;
        return this;
    }

    money(currency = 'USD'): this {
        this._currency = currency;
        return this;
    }

    numeric(decimals = 0): this {
        this._decimals = decimals;
        return this;
    }

    wrap(value = true): this {
        this._wrap = value;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'text',
            limit: this._limit,
            urlFn: this._urlFn,
            copyable: this._copyable,
            formatFn: this._formatFn,
            descriptionFn: this._descriptionFn,
            defaultValue: this._default,
            prefix: this._prefix,
            suffix: this._suffix,
            currency: this._currency,
            decimals: this._decimals,
            wrap: this._wrap,
        };
    }
}

export class BadgeColumn extends BaseColumn {
    protected _type: ColumnType = 'badge';
    private _colors: Record<string, string> = {};

    static make(key: string): BadgeColumn {
        return new BadgeColumn(key);
    }

    colors(map: Record<string, string>): this {
        this._colors = map;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'badge',
            colors: this._colors,
        };
    }
}

export class DateColumn extends BaseColumn {
    protected _type: ColumnType = 'date';
    private _format: 'date' | 'datetime' | 'relative' = 'date';

    static make(key: string): DateColumn {
        return new DateColumn(key);
    }

    format(fmt: 'date' | 'datetime' | 'relative'): this {
        this._format = fmt;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'date',
            dateFormat: this._format,
        };
    }
}

export class BooleanColumn extends BaseColumn {
    protected _type: ColumnType = 'boolean';
    private _trueIcon?: Component;
    private _falseIcon?: Component;
    private _trueColor = 'text-success';
    private _falseColor = 'text-muted-foreground';

    static make(key: string): BooleanColumn {
        return new BooleanColumn(key);
    }

    trueIcon(icon: Component): this {
        this._trueIcon = icon;
        return this;
    }

    falseIcon(icon: Component): this {
        this._falseIcon = icon;
        return this;
    }

    trueColor(color: string): this {
        this._trueColor = color;
        return this;
    }

    falseColor(color: string): this {
        this._falseColor = color;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'boolean',
            trueIcon: this._trueIcon,
            falseIcon: this._falseIcon,
            trueColor: this._trueColor,
            falseColor: this._falseColor,
        };
    }
}

export class ImageColumn extends BaseColumn {
    protected _type: ColumnType = 'image';
    private _circular = false;
    private _size = 40;
    private _defaultUrl?: string;

    static make(key: string): ImageColumn {
        return new ImageColumn(key);
    }

    circular(value = true): this {
        this._circular = value;
        return this;
    }

    size(n: number): this {
        this._size = n;
        return this;
    }

    defaultUrl(url: string): this {
        this._defaultUrl = url;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'image',
            circular: this._circular,
            imageSize: this._size,
            defaultUrl: this._defaultUrl,
        };
    }
}

export class IconColumn extends BaseColumn {
    protected _type: ColumnType = 'icon';
    private _iconFn?: (value: any, row: any) => Component;
    private _colorFn?: (value: any, row: any) => string;

    static make(key: string): IconColumn {
        return new IconColumn(key);
    }

    icon(fn: (value: any, row: any) => Component): this {
        this._iconFn = fn;
        return this;
    }

    color(fn: (value: any, row: any) => string): this {
        this._colorFn = fn;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'icon',
            iconFn: this._iconFn,
            colorFn: this._colorFn,
        };
    }
}

export class ToggleColumn extends BaseColumn {
    protected _type: ColumnType = 'toggle';
    private _onUpdateFn?: (row: any, value: boolean) => void;

    static make(key: string): ToggleColumn {
        return new ToggleColumn(key);
    }

    onUpdate(fn: (row: any, value: boolean) => void): this {
        this._onUpdateFn = fn;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'toggle',
            onUpdateFn: this._onUpdateFn,
        };
    }
}

export class SelectColumn extends BaseColumn {
    protected _type: ColumnType = 'select';
    private _options: Array<{ label: string; value: string }> = [];
    private _onUpdateFn?: (row: any, value: string) => void;
    private _placeholder?: string;

    static make(key: string): SelectColumn {
        return new SelectColumn(key);
    }

    options(opts: Array<{ label: string; value: string }> | Record<string, string>): this {
        if (Array.isArray(opts)) {
            this._options = opts;
        } else {
            this._options = Object.entries(opts).map(([value, label]) => ({ label, value }));
        }
        return this;
    }

    onUpdate(fn: (row: any, value: string) => void): this {
        this._onUpdateFn = fn;
        return this;
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'select',
            options: this._options,
            onUpdateFn: this._onUpdateFn,
            placeholder: this._placeholder,
        };
    }
}

export class TextInputColumn extends BaseColumn {
    protected _type: ColumnType = 'textinput';
    private _onUpdateFn?: (row: any, value: string) => void;
    private _placeholder?: string;
    private _debounceMs = 500;

    static make(key: string): TextInputColumn {
        return new TextInputColumn(key);
    }

    onUpdate(fn: (row: any, value: string) => void): this {
        this._onUpdateFn = fn;
        return this;
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    debounce(ms: number): this {
        this._debounceMs = ms;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'textinput',
            onUpdateFn: this._onUpdateFn,
            placeholder: this._placeholder,
            debounceMs: this._debounceMs,
        };
    }
}
