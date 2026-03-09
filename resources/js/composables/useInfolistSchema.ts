import type { Component } from 'vue';

// Re-export layout classes from form schema for infolist reuse
export { Section, Grid, Tabs, Tab, Fieldset, Flex, isLayoutItem, resolveLayout } from './useFormSchema';
export type { LayoutSchema, LayoutType } from './useFormSchema';

export type EntryType = 'text' | 'badge' | 'date' | 'boolean' | 'image' | 'icon' | 'repeatable' | 'key-value';

export interface EntrySchema {
    key: string;
    label: string;
    type: EntryType;
    colSpan?: number;
    hidden?: boolean;
    tooltip?: string;
    // Text
    copyable?: boolean;
    isBadge?: boolean;
    badgeColors?: Record<string, string>;
    formatFn?: (value: any, record: any) => string;
    descriptionFn?: (record: any) => string;
    defaultValue?: string;
    prefix?: string;
    suffix?: string;
    currency?: string;
    decimals?: number;
    urlFn?: (record: any) => string;
    limit?: number;
    // Date
    dateFormat?: 'date' | 'datetime' | 'relative';
    // Boolean
    trueIcon?: Component;
    falseIcon?: Component;
    trueColor?: string;
    falseColor?: string;
    // Image
    circular?: boolean;
    imageSize?: number;
    // Icon
    iconFn?: (value: any, record: any) => Component;
    colorFn?: (value: any, record: any) => string;
    // Repeatable
    subSchema?: EntrySchema[];
    // Visibility
    visibleFn?: (record: any) => boolean;
    hiddenFn?: (record: any) => boolean;
}

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

export abstract class BaseEntry {
    protected _key: string;
    protected _label?: string;
    protected _type: EntryType = 'text';
    protected _colSpan?: number;
    protected _hidden = false;
    protected _tooltip?: string;
    protected _visibleFn?: (record: any) => boolean;
    protected _hiddenFn?: (record: any) => boolean;

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

    colSpan(n: number): this {
        this._colSpan = n;
        return this;
    }

    hidden(fn?: (record: any) => boolean): this {
        if (fn) {
            this._hiddenFn = fn;
        } else {
            this._hidden = true;
        }
        return this;
    }

    visible(fn: (record: any) => boolean): this {
        this._visibleFn = fn;
        return this;
    }

    tooltip(text: string): this {
        this._tooltip = text;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            key: this._key,
            label: this._label ?? humanize(this._key),
            type: this._type,
            colSpan: this._colSpan,
            hidden: this._hidden,
            tooltip: this._tooltip,
            visibleFn: this._visibleFn,
            hiddenFn: this._hiddenFn,
        };
    }
}

export class TextEntry extends BaseEntry {
    protected _type: EntryType = 'text';
    private _copyable = false;
    private _isBadge = false;
    private _badgeColors?: Record<string, string>;
    private _formatFn?: (value: any, record: any) => string;
    private _descriptionFn?: (record: any) => string;
    private _defaultValue?: string;
    private _prefix?: string;
    private _suffix?: string;
    private _currency?: string;
    private _decimals?: number;
    private _urlFn?: (record: any) => string;
    private _limit?: number;

    static make(key: string): TextEntry {
        return new TextEntry(key);
    }

    copyable(value = true): this {
        this._copyable = value;
        return this;
    }

    badge(value = true): this {
        this._isBadge = value;
        return this;
    }

    badgeColors(map: Record<string, string>): this {
        this._isBadge = true;
        this._badgeColors = map;
        return this;
    }

    formatStateUsing(fn: (value: any, record: any) => string): this {
        this._formatFn = fn;
        return this;
    }

    description(fn: (record: any) => string): this {
        this._descriptionFn = fn;
        return this;
    }

    default(val: string): this {
        this._defaultValue = val;
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

    url(fn: (record: any) => string): this {
        this._urlFn = fn;
        return this;
    }

    limit(n: number): this {
        this._limit = n;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'text',
            copyable: this._copyable,
            isBadge: this._isBadge,
            badgeColors: this._badgeColors,
            formatFn: this._formatFn,
            descriptionFn: this._descriptionFn,
            defaultValue: this._defaultValue,
            prefix: this._prefix,
            suffix: this._suffix,
            currency: this._currency,
            decimals: this._decimals,
            urlFn: this._urlFn,
            limit: this._limit,
        };
    }
}

export class BadgeEntry extends BaseEntry {
    protected _type: EntryType = 'badge';
    private _colors: Record<string, string> = {};

    static make(key: string): BadgeEntry {
        return new BadgeEntry(key);
    }

    colors(map: Record<string, string>): this {
        this._colors = map;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'badge',
            badgeColors: this._colors,
        };
    }
}

export class DateEntry extends BaseEntry {
    protected _type: EntryType = 'date';
    private _format: 'date' | 'datetime' | 'relative' = 'date';

    static make(key: string): DateEntry {
        return new DateEntry(key);
    }

    format(fmt: 'date' | 'datetime' | 'relative'): this {
        this._format = fmt;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'date',
            dateFormat: this._format,
        };
    }
}

export class BooleanEntry extends BaseEntry {
    protected _type: EntryType = 'boolean';
    private _trueIcon?: Component;
    private _falseIcon?: Component;
    private _trueColor = 'text-success';
    private _falseColor = 'text-muted-foreground';

    static make(key: string): BooleanEntry {
        return new BooleanEntry(key);
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

    toSchema(): EntrySchema {
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

export class ImageEntry extends BaseEntry {
    protected _type: EntryType = 'image';
    private _circular = false;
    private _imageSize = 64;

    static make(key: string): ImageEntry {
        return new ImageEntry(key);
    }

    circular(value = true): this {
        this._circular = value;
        return this;
    }

    size(n: number): this {
        this._imageSize = n;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'image',
            circular: this._circular,
            imageSize: this._imageSize,
        };
    }
}

export class IconEntry extends BaseEntry {
    protected _type: EntryType = 'icon';
    private _iconFn?: (value: any, record: any) => Component;
    private _colorFn?: (value: any, record: any) => string;

    static make(key: string): IconEntry {
        return new IconEntry(key);
    }

    icon(fn: (value: any, record: any) => Component): this {
        this._iconFn = fn;
        return this;
    }

    color(fn: (value: any, record: any) => string): this {
        this._colorFn = fn;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'icon',
            iconFn: this._iconFn,
            colorFn: this._colorFn,
        };
    }
}

export class RepeatableEntry extends BaseEntry {
    protected _type: EntryType = 'repeatable';
    private _subSchema: BaseEntry[] = [];

    static make(key: string): RepeatableEntry {
        return new RepeatableEntry(key);
    }

    schema(entries: BaseEntry[]): this {
        this._subSchema = entries;
        return this;
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'repeatable',
            subSchema: this._subSchema.map(e => e.toSchema()),
        };
    }
}

export class KeyValueEntry extends BaseEntry {
    protected _type: EntryType = 'key-value';

    static make(key: string): KeyValueEntry {
        return new KeyValueEntry(key);
    }

    toSchema(): EntrySchema {
        return {
            ...super.toSchema(),
            type: 'key-value',
        };
    }
}

// Union type for schema items (entries + layouts)
import type { LayoutSchema } from './useFormSchema';
import { Section, Grid, Tabs, Fieldset, Flex } from './useFormSchema';

export type InfolistSchemaItem = EntrySchema | BaseEntry | LayoutSchema | Section | Grid | Tabs | Fieldset | Flex;
