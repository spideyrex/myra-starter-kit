/**
 * STRUCTURAL COPY of the frozen report wire shape (spec §2.11).
 *
 * Bundle B owns the canonical `resources/js/types/reports.ts`. That file does
 * not exist in this worktree, so importing it would break the build. These
 * declarations are byte-compatible with it.
 *
 * POST-MERGE: delete this file and re-export from '@/types/reports'.
 */
import type { QueryGroup } from '@/types/admin';

/** B calls this `RuleGroup`; the shipped query-builder type is `QueryGroup`. */
export type RuleGroup = QueryGroup;

export type Bucket = 'hour' | 'day' | 'week' | 'month' | 'quarter' | 'year';
export type CompareMode = 'none' | 'previous' | 'year';
export type MeasureFormat = 'number' | 'currency' | 'percent' | 'duration' | 'bytes';
export type Direction = 'up' | 'down' | 'flat';
export type DimensionType = 'field' | 'date' | 'relation';

export type PeriodPreset =
    | 'today' | 'yesterday' | 'last_7_days' | 'last_30_days' | 'last_90_days'
    | 'this_week' | 'this_month' | 'this_quarter' | 'this_year'
    | 'last_week' | 'last_month' | 'last_quarter' | 'last_year' | 'custom';

export type PeriodSelection =
    | { preset: Exclude<PeriodPreset, 'custom'> }
    | { preset: 'custom'; from: string; to: string };

export interface Delta {
    absolute: number;
    percent: number | null;
    direction: Direction;
    good: boolean;
}

export interface DrillTarget {
    url: string;
    params: Record<string, string>;
}

export interface ReportRow {
    key: string;
    label: string;
    values: Record<string, number | null>;
    previous: Record<string, number | null> | null;
    deltas: Record<string, Delta | null>;
    isOther: boolean;
    drill: DrillTarget | null;
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
}

export interface ReportState {
    period: PeriodSelection;
    compare: CompareMode;
    dimension: string;
    bucket?: Bucket;
    measures: string[];
    limit?: number;
    chart?: string;
    query?: RuleGroup | null;
    cross?: Record<string, RuleGroup>;
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

/** Ungrouped counterpart — what a StatWidget renders. */
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
    drill: DrillTarget | null;
}
