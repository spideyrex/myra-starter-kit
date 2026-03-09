import type { FilterSchema } from '@/types/admin';

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

export interface QueryBuilderField {
    name: string;
    label: string;
    operators: string[];
}

export class QueryBuilderFilter extends BaseFilter {
    protected _type: FilterType = 'query-builder';
    private _fields: QueryBuilderField[] = [];

    static make(name: string): QueryBuilderFilter {
        return new QueryBuilderFilter(name);
    }

    fields(f: QueryBuilderField[]): this {
        this._fields = f;
        return this;
    }

    toSchema(): FilterSchema {
        return {
            ...super.toSchema(),
            type: 'query-builder',
            fields: this._fields,
        } as FilterSchema;
    }
}
