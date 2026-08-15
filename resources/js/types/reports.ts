import type { QueryGroup } from '@/types/admin';
import type { QueryConstraintSchema } from '@/types/query-builder';

/** Mirrors App\Admin\Report\Bucket. The server enum is the authority. */
export type Bucket = 'hour' | 'day' | 'week' | 'month' | 'quarter' | 'year';

export type CompareMode = 'none' | 'previous' | 'year';

export type MeasureFormat = 'number' | 'currency' | 'percent' | 'duration' | 'bytes';

export type Direction = 'up' | 'down' | 'flat';

export type DimensionType = 'field' | 'date' | 'relation';

export type PeriodPreset =
    | 'today' | 'yesterday'
    | 'last_7_days' | 'last_30_days' | 'last_90_days'
    | 'this_week' | 'this_month' | 'this_quarter' | 'this_year'
    | 'last_week' | 'last_month' | 'last_quarter' | 'last_year'
    | 'custom';

export type PeriodSelection =
    | { preset: Exclude<PeriodPreset, 'custom'>; tz?: string }
    | { preset: 'custom'; from: string; to: string; tz?: string };

export interface Delta {
    absolute: number;
    /** null means "n/a" — the previous window was zero. Render an em dash. */
    percent: number | null;
    direction: Direction;
    good: boolean;
}

export interface ReportRow {
    key: string;
    label: string;
    values: Record<string, number | null>;
    previous: Record<string, number | null> | null;
    deltas: Record<string, Delta | null>;
    isOther: boolean;
    /** null until the report-delivery bundle binds a DrillResolver. */
    drill: { url: string; params: Record<string, string> } | null;
}

export interface MeasureSchema {
    key: string;
    labelKey: string;
    format: MeasureFormat;
    decimals: number;
    goal: number | null;
    invertTrend: boolean;
    additive: boolean;
}

export interface DimensionSchema {
    key: string;
    labelKey: string;
    type: DimensionType;
    drillable: boolean;
    allowedBuckets: Bucket[];
    limit?: number;
}

export interface ReportState {
    period: PeriodSelection;
    compare: CompareMode;
    dimension: string;
    bucket?: Bucket | null;
    measures: string[];
    limit?: number;
    chart?: string;
    mode?: 'series' | 'stat';
    query?: QueryGroup | null;
    cross?: Record<string, QueryGroup>;
    // Inertia's router requires an index signature to accept this as a payload.
    [key: string]: any;
}

export interface ReportResultPayload {
    report: string;
    state: ReportState;
    period: { preset: string; from: string; to: string; tz: string; bucket: Bucket | null };
    comparison: { mode: CompareMode; from: string; to: string } | null;
    dimension: DimensionSchema;
    measures: MeasureSchema[];
    rows: ReportRow[];
    totals: Record<string, number | null>;
    previousTotals: Record<string, number | null> | null;
    deltas: Record<string, Delta | null>;
    truncated: boolean;
    groupCount: number;
}

export interface StatResultPayload {
    report: string;
    measure: string;
    value: number | null;
    previous: number | null;
    delta: Delta | null;
    spark: Array<number | null>;
    format: MeasureFormat;
    decimals: number;
    goal: number | null;
    drill: { url: string; params: Record<string, string> } | null;
}

export interface ReportSchema {
    key: string;
    titleKey: string;
    dimensions: DimensionSchema[];
    measures: MeasureSchema[];
    comparisons: CompareMode[];
    periods: PeriodPreset[];
    defaults: { dimension: string; measures: string[]; period: PeriodPreset; chart: string };
    fields: { maxRules: number; maxDepth: number; fields: QueryConstraintSchema[] };
    formats: string[];
    maxGroups: number;
    maxMeasures: number;
    schedulable: boolean;
    drillable: boolean;
}
