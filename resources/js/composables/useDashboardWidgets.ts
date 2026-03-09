import type { Component } from 'vue';

export type WidgetType = 'stat' | 'chart' | 'table' | 'custom';
export type ChartType = 'bar' | 'line' | 'pie' | 'doughnut';

export interface WidgetSchema {
    key: string;
    type: WidgetType;
    colSpan: number;
    // Stat
    title?: string;
    valueFn?: (props: any) => string | number;
    descriptionFn?: (props: any) => string;
    icon?: Component;
    trendFn?: (props: any) => { value: number; isPositive: boolean } | null;
    // Chart
    chartType?: ChartType;
    dataFn?: (props: any) => any;
    height?: number;
    collapsible?: boolean;
    // Table
    columnsFn?: (props: any) => Array<{ key: string; label: string; class?: string }>;
    rowsFn?: (props: any) => any[];
    footerLinkFn?: (props: any) => { label: string; href: string } | null;
    // Custom
    component?: Component;
    propsFn?: (props: any) => Record<string, any>;
}

function humanize(name: string): string {
    return name
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

abstract class BaseWidget {
    protected _key: string;
    protected _type: WidgetType = 'stat';
    protected _colSpan = 1;
    protected _title?: string;

    constructor(key: string) {
        this._key = key;
    }

    colSpan(n: number): this {
        this._colSpan = n;
        return this;
    }

    title(text: string): this {
        this._title = text;
        return this;
    }

    toSchema(): WidgetSchema {
        return {
            key: this._key,
            type: this._type,
            colSpan: this._colSpan,
            title: this._title ?? humanize(this._key),
        };
    }
}

export class StatWidget extends BaseWidget {
    protected _type: WidgetType = 'stat';
    private _valueFn?: (props: any) => string | number;
    private _descriptionFn?: (props: any) => string;
    private _icon?: Component;
    private _trendFn?: (props: any) => { value: number; isPositive: boolean } | null;

    static make(key: string): StatWidget {
        return new StatWidget(key);
    }

    value(fn: (props: any) => string | number): this {
        this._valueFn = fn;
        return this;
    }

    description(fn: (props: any) => string): this {
        this._descriptionFn = fn;
        return this;
    }

    icon(component: Component): this {
        this._icon = component;
        return this;
    }

    trend(fn: (props: any) => { value: number; isPositive: boolean } | null): this {
        this._trendFn = fn;
        return this;
    }

    toSchema(): WidgetSchema {
        return {
            ...super.toSchema(),
            type: 'stat',
            valueFn: this._valueFn,
            descriptionFn: this._descriptionFn,
            icon: this._icon,
            trendFn: this._trendFn,
        };
    }
}

export class ChartWidget extends BaseWidget {
    protected _type: WidgetType = 'chart';
    private _chartType: ChartType = 'bar';
    private _dataFn?: (props: any) => any;
    private _height = 260;
    private _collapsible = false;

    static make(key: string): ChartWidget {
        return new ChartWidget(key);
    }

    type(ct: ChartType): this {
        this._chartType = ct;
        return this;
    }

    data(fn: (props: any) => any): this {
        this._dataFn = fn;
        return this;
    }

    height(n: number): this {
        this._height = n;
        return this;
    }

    collapsible(value = true): this {
        this._collapsible = value;
        return this;
    }

    toSchema(): WidgetSchema {
        return {
            ...super.toSchema(),
            type: 'chart',
            chartType: this._chartType,
            dataFn: this._dataFn,
            height: this._height,
            collapsible: this._collapsible,
        };
    }
}

export class TableWidget extends BaseWidget {
    protected _type: WidgetType = 'table';
    private _columnsFn?: (props: any) => Array<{ key: string; label: string; class?: string }>;
    private _rowsFn?: (props: any) => any[];
    private _footerLinkFn?: (props: any) => { label: string; href: string } | null;

    static make(key: string): TableWidget {
        return new TableWidget(key);
    }

    columns(fn: (props: any) => Array<{ key: string; label: string; class?: string }>): this {
        this._columnsFn = fn;
        return this;
    }

    data(fn: (props: any) => any[]): this {
        this._rowsFn = fn;
        return this;
    }

    footerLink(fn: (props: any) => { label: string; href: string } | null): this {
        this._footerLinkFn = fn;
        return this;
    }

    toSchema(): WidgetSchema {
        return {
            ...super.toSchema(),
            type: 'table',
            columnsFn: this._columnsFn,
            rowsFn: this._rowsFn,
            footerLinkFn: this._footerLinkFn,
        };
    }
}

export class CustomWidget extends BaseWidget {
    protected _type: WidgetType = 'custom';
    private _component?: Component;
    private _propsFn?: (props: any) => Record<string, any>;

    constructor(key: string, component?: Component) {
        super(key);
        this._component = component;
    }

    static make(key: string, component?: Component): CustomWidget {
        return new CustomWidget(key, component);
    }

    props(fn: (props: any) => Record<string, any>): this {
        this._propsFn = fn;
        return this;
    }

    toSchema(): WidgetSchema {
        return {
            ...super.toSchema(),
            type: 'custom',
            component: this._component,
            propsFn: this._propsFn,
        };
    }
}
