import type { Component } from 'vue';
import type { ColumnSchema } from '@/types/admin';

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

export type ColumnType = 'text' | 'badge' | 'date' | 'boolean' | 'image' | 'icon' | 'toggle' | 'select' | 'textinput' | 'color' | 'checkbox';

export type SummaryType = 'sum' | 'average' | 'count' | 'min' | 'max' | 'median' | 'range' | 'custom';

export interface SummaryConfig {
    type: SummaryType;
    label?: string;
    prefix?: string;
    suffix?: string;
    decimals?: number;
    currency?: string;
    locale?: string;
    excludeNull?: boolean;
    /** `range` only. */
    separator?: string;
    /** `'all'` expects the value to arrive in the server `summaries` prop. */
    scope?: 'page' | 'all';
    fn?: (values: any[]) => string | number;
}

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
    protected _summarize?: SummaryType;
    protected _summaryFn?: (values: any[]) => string | number;
    protected _summaryConfig?: SummaryConfig;

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

    summarize(type: SummaryType, fn?: (values: any[]) => string | number): this {
        this._summarize = type;
        if (fn) this._summaryFn = fn;
        return this;
    }

    /** Rich form of summarize() — label, formatting and scope. */
    summary(config: SummaryConfig): this {
        this._summarize = config.type;
        this._summaryConfig = config;
        if (config.fn) this._summaryFn = config.fn;
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
            summaryConfig: this._summaryConfig,
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

/**
 * Shared base for cells the user edits in place. When `updateRoute()` is set the
 * table owns the request, the optimistic write and the rollback; `onUpdate()`
 * stays as the escape hatch for pages that want to own it themselves.
 */
export abstract class InlineEditableColumn<V = any> extends BaseColumn {
    protected _updateRoute?: string;
    protected _updateField?: string;
    protected _optimistic = true;
    protected _permission?: string;
    protected _disabledFn?: (row: any) => boolean;
    protected _confirmFn?: (row: any, value: V) => string | false;
    protected _rowLabelFn?: (row: any) => string;
    protected _debounceMs?: number;
    protected _onUpdateFn?: (row: any, value: V) => void;

    updateRoute(routeName: string): this {
        this._updateRoute = routeName;
        return this;
    }

    /** Column written server-side. Defaults to the column key. */
    field(name: string): this {
        this._updateField = name;
        return this;
    }

    /** false = wait for the server before showing the new value. */
    optimistic(value = true): this {
        this._optimistic = value;
        return this;
    }

    /** Client-side gate only — the server check stays authoritative. */
    permission(perm: string): this {
        this._permission = perm;
        return this;
    }

    disabledWhen(fn: (row: any) => boolean): this {
        this._disabledFn = fn;
        return this;
    }

    /** Return a message to require confirmation for that value only. */
    confirmWhen(fn: (row: any, value: V) => string | false): this {
        this._confirmFn = fn;
        return this;
    }

    /** Per-row accessible name. Required for checkbox/toggle columns. */
    rowLabel(fn: (row: any) => string): this {
        this._rowLabelFn = fn;
        return this;
    }

    debounce(ms: number): this {
        this._debounceMs = ms;
        return this;
    }

    /** Escape hatch — ignored when updateRoute() is set. */
    onUpdate(fn: (row: any, value: V) => void): this {
        this._onUpdateFn = fn;
        return this;
    }

    protected inlineSchema() {
        return {
            updateRoute: this._updateRoute,
            updateField: this._updateField ?? this._key,
            optimistic: this._optimistic,
            permission: this._permission,
            disabledFn: this._disabledFn,
            confirmFn: this._confirmFn,
            rowLabelFn: this._rowLabelFn,
            debounceMs: this._debounceMs ?? 500,
            onUpdateFn: this._onUpdateFn,
        };
    }
}

export class ToggleColumn extends InlineEditableColumn<boolean> {
    protected _type: ColumnType = 'toggle';

    static make(key: string): ToggleColumn {
        return new ToggleColumn(key);
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'toggle',
            ...this.inlineSchema(),
        } as ColumnSchema;
    }
}

export class CheckboxColumn extends InlineEditableColumn<boolean> {
    protected _type: ColumnType = 'checkbox';
    private _indeterminateFn?: (row: any) => boolean;

    static make(key: string): CheckboxColumn {
        return new CheckboxColumn(key);
    }

    indeterminateWhen(fn: (row: any) => boolean): this {
        this._indeterminateFn = fn;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'checkbox',
            ...this.inlineSchema(),
            indeterminateFn: this._indeterminateFn,
        } as ColumnSchema;
    }
}

export class SelectColumn extends InlineEditableColumn<string> {
    protected _type: ColumnType = 'select';
    private _options: Array<{ label: string; value: string }> = [];
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

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'select',
            options: this._options,
            placeholder: this._placeholder,
            ...this.inlineSchema(),
        } as ColumnSchema;
    }
}

export class TextInputColumn extends InlineEditableColumn<string> {
    protected _type: ColumnType = 'textinput';
    private _placeholder?: string;

    static make(key: string): TextInputColumn {
        return new TextInputColumn(key);
    }

    placeholder(text: string): this {
        this._placeholder = text;
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'textinput',
            placeholder: this._placeholder,
            ...this.inlineSchema(),
        } as ColumnSchema;
    }
}

export class ColorColumn extends BaseColumn {
    protected _type: ColumnType = 'color';
    private _copyable = true;
    /** Undefined resolves to `common.copiedToClipboard` at render time. */
    private _copyMessage?: string;
    private _showValue = true;
    private _swatchSize = 16;
    private _swatchShape: 'square' | 'circle' = 'square';

    static make(key: string): ColorColumn {
        return new ColorColumn(key);
    }

    copyable(value = true): this {
        this._copyable = value;
        return this;
    }

    copyMessage(text: string): this {
        this._copyMessage = text;
        return this;
    }

    /** false hides the literal value — the swatch then carries an aria-label. */
    showValue(value = true): this {
        this._showValue = value;
        return this;
    }

    swatchOnly(): this {
        return this.showValue(false);
    }

    swatchSize(px: number): this {
        this._swatchSize = px;
        return this;
    }

    circular(value = true): this {
        this._swatchShape = value ? 'circle' : 'square';
        return this;
    }

    toSchema(): ColumnSchema {
        return {
            ...super.toSchema(),
            type: 'color',
            copyable: this._copyable,
            copyMessage: this._copyMessage,
            swatchShowValue: this._showValue,
            swatchSize: this._swatchSize,
            swatchShape: this._swatchShape,
        } as ColumnSchema;
    }
}
