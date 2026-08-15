import type {
    ChartTheme,
} from '@/components/admin/charts/chartTheme';
import type {
    Delta, MeasureSchema, ReportResultPayload, ReportRow,
} from '@/components/admin/charts/types';

export const testTheme: ChartTheme = {
    series: ['c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8'],
    grid: 'grid',
    tick: 'tick',
    muted: 'muted',
    comparison: 'comparison',
    good: 'good',
    bad: 'bad',
};

export const signups: MeasureSchema = {
    key: 'signups',
    labelKey: 'charts.demo.measure.sales',
    format: 'number',
    decimals: 0,
    goal: null,
    invertTrend: false,
    additive: true,
};

export function measure(overrides: Partial<MeasureSchema> = {}): MeasureSchema {
    return { ...signups, ...overrides };
}

export function delta(overrides: Partial<Delta> = {}): Delta {
    return { absolute: 10, percent: 12.5, direction: 'up', good: true, ...overrides };
}

export function row(key: string, values: Record<string, number | null>, extra: Partial<ReportRow> = {}): ReportRow {
    return {
        key,
        label: key,
        values,
        previous: null,
        deltas: {},
        isOther: false,
        drill: null,
        ...extra,
    };
}

export function result(overrides: Partial<ReportResultPayload> = {}): ReportResultPayload {
    const rows = overrides.rows ?? [
        row('a', { signups: 5 }),
        row('b', { signups: 8 }),
    ];

    return {
        report: 'demo',
        state: { period: { preset: 'last_30_days' }, compare: 'none', dimension: 'd', measures: ['signups'] },
        period: { preset: 'last_30_days', from: '2026-07-17', to: '2026-08-16', tz: 'UTC', bucket: 'day' },
        comparison: null,
        dimension: {
            key: 'd', labelKey: 'charts.demo.dim.month', type: 'date',
            drillable: false, allowedBuckets: ['day'],
        },
        measures: [signups],
        rows,
        totals: { signups: rows.reduce((sum, r) => sum + (r.values.signups ?? 0), 0) },
        previousTotals: null,
        deltas: { signups: null },
        truncated: false,
        groupCount: rows.length,
        ...overrides,
    };
}
