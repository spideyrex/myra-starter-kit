import type { Component, InjectionKey } from 'vue';
import type { StatCardColor } from '@/components/StatCard.vue';
import type { ChartType } from '@/components/admin/charts/ChartRegistry';
import type { Bucket, CompareMode, ReportRow } from '@/components/admin/charts/types';

export type WidgetType = 'stat' | 'chart' | 'table' | 'custom';
export type { ChartType } from '@/components/admin/charts/ChartRegistry';

/** What a segment click does. `popover` is D's lens; before D merges it no-ops. */
export type SegmentMode = 'popover' | 'drill' | 'filter' | 'none';

export type ColSpan = number | { sm?: number; lg?: number; xl?: number };

/**
 * Server binding. Replaces the dataFn closure for report-backed widgets: a
 * binding is serialisable and, unlike a closure, cannot reduce rows in the
 * browser. The reduction stays in SQL.
 */
export interface ReportBinding {
    report: string;
    dimension?: string;
    measures?: string[];
    bucket?: Bucket;
    compare?: CompareMode;
    limit?: number;
    mode?: 'series' | 'stat';
}

/**
 * Segment callback the grid provides and chart widgets call. Bundle D supplies
 * the implementation (cross-filter / drill-through); before it merges, the
 * page passes a no-op and clicking a segment does nothing.
 */
export type SegmentHandler = (widgetKey: string, row: ReportRow, measureKey: string) => void;

export const DASHBOARD_SEGMENT: InjectionKey<SegmentHandler> = Symbol('myra.dashboard.segment');

export interface EmptyStateKeys {
    headingKey: string;
    descriptionKey?: string;
}

export interface WidgetSchema {
    key: string;
    type: WidgetType;
    colSpan: ColSpan;
    rowSpan?: number;
    // Visibility / ordering
    permission?: string;
    sort?: number;
    visibleFn?: (props: any) => boolean;
    lazy?: boolean;
    pollSeconds?: number;
    // Server binding
    binding?: ReportBinding;
    source?: string;
    segmentMode?: SegmentMode;
    // Stat
    title?: string;
    valueFn?: (props: any) => string | number;
    descriptionFn?: (props: any) => string;
    icon?: Component;
    trendFn?: (props: any) => { value: number; isPositive: boolean } | null;
    color?: StatCardColor;
    measureKey?: string;
    sparklineFlag?: boolean;
    goalValue?: number;
    invertTrendFlag?: boolean;
    // Chart
    chartType?: ChartType;
    dataFn?: (props: any) => any;
    height?: number;
    collapsible?: boolean;
    measureKeys?: string[];
    stacked?: boolean;
    legend?: boolean;
    emptyStateKeys?: EmptyStateKeys;
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

/** The grid is 1 / 2 / 4 columns; a wider span silently broke the layout before. */
export const GRID_COLUMNS = { sm: 2, lg: 4 } as const;

const SM_SPAN: Record<number, string> = { 1: 'sm:col-span-1', 2: 'sm:col-span-2' };
const LG_SPAN: Record<number, string> = {
    1: 'lg:col-span-1', 2: 'lg:col-span-2', 3: 'lg:col-span-3', 4: 'lg:col-span-4',
};
const ROW_SPAN: Record<number, string> = {
    1: 'row-span-1', 2: 'row-span-2', 3: 'row-span-3', 4: 'row-span-4',
};

function clamp(n: number, max: number): number {
    if (!Number.isFinite(n)) return 1;
    return Math.max(1, Math.min(Math.trunc(n), max));
}

/**
 * Tailwind span classes, clamped to the grid. Emitted as classes rather than an
 * inline `grid-column` style so the utility stays inside the design system.
 */
export function spanClasses(colSpan: ColSpan | undefined, rowSpan?: number): string[] {
    const out: string[] = ['col-span-1'];

    const sm = typeof colSpan === 'number' ? colSpan : colSpan?.sm ?? 1;
    const lg = typeof colSpan === 'number' ? colSpan : colSpan?.lg ?? sm;

    out.push(SM_SPAN[clamp(sm, GRID_COLUMNS.sm)]);
    out.push(LG_SPAN[clamp(lg, GRID_COLUMNS.lg)]);

    if (rowSpan) out.push(ROW_SPAN[clamp(rowSpan, 4)]);

    return out;
}

abstract class BaseWidget {
    protected _key: string;
    protected _type: WidgetType = 'stat';
    protected _colSpan: ColSpan = 1;
    protected _rowSpan?: number;
    protected _title?: string;
    protected _permission?: string;
    protected _sort?: number;
    protected _visibleFn?: (props: any) => boolean;
    protected _lazy = true;
    protected _pollSeconds?: number;
    protected _binding?: ReportBinding;
    protected _dataFnSet = false;

    constructor(key: string) {
        this._key = key;
    }

    colSpan(n: ColSpan): this {
        this._colSpan = n;
        return this;
    }

    rowSpan(n: number): this {
        this._rowSpan = n;
        return this;
    }

    title(text: string): this {
        this._title = text;
        return this;
    }

    permission(ability: string): this {
        this._permission = ability;
        return this;
    }

    sort(n: number): this {
        this._sort = n;
        return this;
    }

    visible(fn: (props: any) => boolean): this {
        this._visibleFn = fn;
        return this;
    }

    /** IntersectionObserver mount. Default true. */
    lazy(v = true): this {
        this._lazy = v;
        return this;
    }

    poll(seconds: number): this {
        this._pollSeconds = seconds;
        return this;
    }

    /** Declaring both a report binding and a data closure is a mis-declaration. */
    protected assertNoDataFn(): void {
        if (this._dataFnSet) {
            throw new Error(
                `Widget [${this._key}] declares both .report() and .data(). Pick one: .report() keeps the reduction in SQL.`,
            );
        }
    }

    protected assertNoBinding(): void {
        if (this._binding?.report) {
            throw new Error(
                `Widget [${this._key}] declares both .report() and .data(). Pick one: .report() keeps the reduction in SQL.`,
            );
        }
    }

    toSchema(): WidgetSchema {
        return {
            key: this._key,
            type: this._type,
            colSpan: this._colSpan,
            rowSpan: this._rowSpan,
            title: this._title ?? humanize(this._key),
            permission: this._permission,
            sort: this._sort,
            visibleFn: this._visibleFn,
            lazy: this._lazy,
            pollSeconds: this._pollSeconds,
            binding: this._binding,
        };
    }
}

export class StatWidget extends BaseWidget {
    protected _type: WidgetType = 'stat';
    private _valueFn?: (props: any) => string | number;
    private _descriptionFn?: (props: any) => string;
    private _icon?: Component;
    private _trendFn?: (props: any) => { value: number; isPositive: boolean } | null;
    private _color?: StatCardColor;
    private _measureKey?: string;
    private _sparkline = false;
    private _goal?: number;
    private _invertTrend = false;
    private _segmentMode: SegmentMode = 'none';

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

    color(c: StatCardColor): this {
        this._color = c;
        return this;
    }

    /** Bind to a server report. The value is aggregated in SQL, not in the browser. */
    report(key: string): this {
        this.assertNoDataFn();
        this._binding = { ...(this._binding ?? {}), report: key, mode: 'stat' };
        return this;
    }

    measure(key: string): this {
        this._measureKey = key;
        this._binding = { ...(this._binding ?? { report: '' }), measures: [key] };
        return this;
    }

    compare(mode: CompareMode): this {
        this._binding = { ...(this._binding ?? { report: '' }), compare: mode };
        return this;
    }

    sparkline(v = true): this {
        this._sparkline = v;
        return this;
    }

    goal(target: number): this {
        this._goal = target;
        return this;
    }

    invertTrend(v = true): this {
        this._invertTrend = v;
        return this;
    }

    onSegment(mode: SegmentMode): this {
        this._segmentMode = mode;
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
            color: this._color,
            measureKey: this._measureKey,
            sparklineFlag: this._sparkline,
            goalValue: this._goal,
            invertTrendFlag: this._invertTrend,
            segmentMode: this._segmentMode,
        };
    }
}

export class ChartWidget extends BaseWidget {
    protected _type: WidgetType = 'chart';
    private _chartType: ChartType = 'bar';
    private _dataFn?: (props: any) => any;
    private _height = 260;
    private _collapsible = false;
    private _measureKeys?: string[];
    private _stacked = false;
    private _legend?: boolean;
    private _source?: string;
    private _segmentMode: SegmentMode = 'popover';
    private _emptyState?: EmptyStateKeys;

    static make(key: string): ChartWidget {
        return new ChartWidget(key);
    }

    type(ct: ChartType): this {
        this._chartType = ct;
        return this;
    }

    /**
     * The client-side path for data already on the page. Use `.report()` for
     * anything aggregated — it keeps the reduction in SQL.
     */
    data(fn: (props: any) => any): this {
        this.assertNoBinding();
        this._dataFn = fn;
        this._dataFnSet = true;
        return this;
    }

    report(key: string): this {
        this.assertNoDataFn();
        this._binding = { ...(this._binding ?? {}), report: key, mode: 'series' };
        return this;
    }

    dimension(key: string): this {
        this._binding = { ...(this._binding ?? { report: '' }), dimension: key };
        return this;
    }

    measures(keys: string[]): this {
        this._measureKeys = keys;
        this._binding = { ...(this._binding ?? { report: '' }), measures: keys };
        return this;
    }

    bucket(b: Bucket): this {
        this._binding = { ...(this._binding ?? { report: '' }), bucket: b };
        return this;
    }

    compare(mode: CompareMode): this {
        this._binding = { ...(this._binding ?? { report: '' }), compare: mode };
        return this;
    }

    limit(n: number): this {
        this._binding = { ...(this._binding ?? { report: '' }), limit: n };
        return this;
    }

    stacked(v = true): this {
        this._stacked = v;
        return this;
    }

    legend(v = true): this {
        this._legend = v;
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

    /** Cross-filter channel. Siblings sharing it react; the source never filters itself. */
    source(key: string): this {
        this._source = key;
        return this;
    }

    onSegment(mode: SegmentMode): this {
        this._segmentMode = mode;
        return this;
    }

    emptyState(keys: EmptyStateKeys): this {
        this._emptyState = keys;
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
            measureKeys: this._measureKeys,
            stacked: this._stacked,
            legend: this._legend,
            source: this._source,
            segmentMode: this._segmentMode,
            emptyStateKeys: this._emptyState,
        };
    }
}

export class TableWidget extends BaseWidget {
    protected _type: WidgetType = 'table';
    private _columnsFn?: (props: any) => Array<{ key: string; label: string; class?: string }>;
    private _rowsFn?: (props: any) => any[];
    private _footerLinkFn?: (props: any) => { label: string; href: string } | null;
    private _measureKeys?: string[];

    static make(key: string): TableWidget {
        return new TableWidget(key);
    }

    columns(fn: (props: any) => Array<{ key: string; label: string; class?: string }>): this {
        this._columnsFn = fn;
        return this;
    }

    /**
     * The client-side path for data already on the page. Use `.report()` for
     * anything aggregated — it keeps the reduction in SQL.
     */
    data(fn: (props: any) => any[]): this {
        this.assertNoBinding();
        this._rowsFn = fn;
        this._dataFnSet = true;
        return this;
    }

    report(key: string): this {
        this.assertNoDataFn();
        this._binding = { ...(this._binding ?? {}), report: key, mode: 'series' };
        return this;
    }

    dimension(key: string): this {
        this._binding = { ...(this._binding ?? { report: '' }), dimension: key };
        return this;
    }

    measures(keys: string[]): this {
        this._measureKeys = keys;
        this._binding = { ...(this._binding ?? { report: '' }), measures: keys };
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
            measureKeys: this._measureKeys,
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

export type WidgetInput = WidgetSchema | { toSchema(): WidgetSchema };

export function toWidgetSchema(widget: WidgetInput): WidgetSchema {
    if ('toSchema' in widget && typeof widget.toSchema === 'function') {
        return widget.toSchema();
    }
    return widget as WidgetSchema;
}

/**
 * Normalise -> permission filter -> visibility filter -> sort. Declaration
 * order is the tiebreak, so an undeclared `sort` never reshuffles a dashboard.
 */
export function resolveWidgets(
    widgets: WidgetInput[],
    pageProps: any,
    can: (ability: string) => boolean,
): WidgetSchema[] {
    return widgets
        .map(toWidgetSchema)
        .map((schema, index) => ({ schema, index }))
        .filter(({ schema }) => !schema.permission || can(schema.permission))
        .filter(({ schema }) => !schema.visibleFn || schema.visibleFn(pageProps))
        .sort((a, b) => ((a.schema.sort ?? 0) - (b.schema.sort ?? 0)) || (a.index - b.index))
        .map(({ schema }) => schema);
}

// >>> MYRA v2.5 [A] START

export interface LayoutEntry {
    key: string;
    order: number;
    colSpan?: ColSpan;
    rowSpan?: number;
    hidden?: boolean;
}

export interface DashboardLayoutPayload {
    version: 1;
    entries: LayoutEntry[];
    /** SERVER-RESOLVED. Never read out of a locally-held saved blob. */
    instances: WidgetSchema[];
}

/** Clamp a stored span so a hostile value can never emit a class outside the grid. */
function clampSpan(colSpan: ColSpan): ColSpan {
    if (typeof colSpan === 'number') return clamp(colSpan, GRID_COLUMNS.lg);

    const out: { sm?: number; lg?: number; xl?: number } = {};
    if (colSpan?.sm !== undefined) out.sm = clamp(colSpan.sm, GRID_COLUMNS.sm);
    if (colSpan?.lg !== undefined) out.lg = clamp(colSpan.lg, GRID_COLUMNS.lg);
    if (colSpan?.xl !== undefined) out.xl = clamp(colSpan.xl, GRID_COLUMNS.lg);

    return out;
}

function entryMap(layout: DashboardLayoutPayload): Map<string, LayoutEntry> {
    const map = new Map<string, LayoutEntry>();

    for (const entry of layout.entries ?? []) {
        if (entry && typeof entry.key === 'string') map.set(entry.key, entry);
    }

    return map;
}

/**
 * Presentation overlay. Runs AFTER resolveWidgets(), so permission and
 * visibility have already decided membership — this can only reorder, resize or
 * hide what survived. An entry naming an unknown key is dropped; a widget with
 * no entry keeps its declared position, appended after the entry-ordered ones so
 * a newly shipped widget appears rather than vanishes. Total by construction:
 * any throw returns `schemas` unchanged.
 */
export function applyLayout(
    schemas: WidgetSchema[],
    layout: DashboardLayoutPayload | null | undefined,
): WidgetSchema[] {
    if (!layout) return schemas;

    try {
        const entries = entryMap(layout);
        const ordered: Array<{ schema: WidgetSchema; order: number; index: number }> = [];
        const trailing: WidgetSchema[] = [];

        schemas.forEach((schema, index) => {
            const entry = entries.get(schema.key);

            if (!entry) {
                trailing.push(schema);

                return;
            }

            if (entry.hidden === true) return;

            const next: WidgetSchema = { ...schema };
            if (entry.colSpan !== undefined) next.colSpan = clampSpan(entry.colSpan);
            if (entry.rowSpan !== undefined) next.rowSpan = clamp(entry.rowSpan, 4);

            ordered.push({ schema: next, order: Number(entry.order) || 0, index });
        });

        ordered.sort((a, b) => (a.order - b.order) || (a.index - b.index));

        return [...ordered.map(o => o.schema), ...trailing];
    } catch {
        return schemas;
    }
}

/** Widgets an overlay hides. The inverse membership of applyLayout(). */
export function hiddenByLayout(
    schemas: WidgetSchema[],
    layout: DashboardLayoutPayload | null | undefined,
): WidgetSchema[] {
    if (!layout) return [];

    try {
        const entries = entryMap(layout);

        return schemas.filter(schema => entries.get(schema.key)?.hidden === true);
    } catch {
        return [];
    }
}

/** Inverse of applyLayout — what the editor PUTs. NEVER includes instances. */
export function toLayoutEntries(schemas: WidgetSchema[]): LayoutEntry[] {
    return schemas.map((schema, index) => ({
        key: schema.key,
        order: clamp(index + 1, 255) - 1,
        colSpan: clampSpan(schema.colSpan ?? 1),
        rowSpan: clamp(schema.rowSpan ?? 1, 4),
        hidden: false,
    }));
}

// <<< MYRA v2.5 [A] END
