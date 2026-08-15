import type { ColumnSchema, SummaryConfig } from '@/types/admin';

export interface SummaryAccumulator {
    /** Non-null values seen. */
    n: number;
    /** Rows seen, nulls included. */
    total: number;
    sum: number;
    min: number;
    max: number;
    nums: number[];
    values: any[];
}

export const EMPTY_SUMMARY = '—';

function emptyAcc(): SummaryAccumulator {
    return { n: 0, total: 0, sum: 0, min: Infinity, max: -Infinity, nums: [], values: [] };
}

/** One pass per column. Never spreads an array into Math.min/Math.max. */
export function accumulate(
    rows: any[],
    key: string,
    opts: { keepValues?: boolean } = {},
): SummaryAccumulator {
    const acc = emptyAcc();
    for (const row of rows) {
        acc.total++;
        const v = row?.[key];
        if (v === null || v === undefined) continue;
        acc.n++;
        if (opts.keepValues) acc.values.push(v);
        const num = typeof v === 'number' ? v : parseFloat(v);
        if (!Number.isNaN(num)) {
            acc.sum += num;
            if (num < acc.min) acc.min = num;
            if (num > acc.max) acc.max = num;
            acc.nums.push(num);
        }
    }
    return acc;
}

function median(nums: number[]): number | null {
    if (nums.length === 0) return null;
    const sorted = [...nums].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
}

function defaultDecimals(cfg: SummaryConfig | undefined): number | undefined {
    if (!cfg) return undefined;
    if (cfg.decimals !== undefined) return cfg.decimals;
    return cfg.type === 'count' ? 0 : 2;
}

/** Applies currency / decimals / prefix / suffix. */
export function formatSummary(value: string | number, cfg?: SummaryConfig, currency?: string): string {
    const cur = cfg?.currency ?? currency;
    const locale = cfg?.locale ?? 'en-US';
    let out: string;

    const num = typeof value === 'number' ? value : parseFloat(String(value));

    if (cur && !Number.isNaN(num)) {
        out = new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(num);
    } else if (Number.isNaN(num)) {
        out = String(value);
    } else {
        const decimals = defaultDecimals(cfg);
        out = decimals !== undefined ? num.toFixed(decimals) : String(Math.round(num * 100) / 100);
    }

    if (cfg?.prefix) out = cfg.prefix + out;
    if (cfg?.suffix) out = out + cfg.suffix;
    return out;
}

/** Turns an accumulator into the displayed summary for a column. */
export function summariseAcc(col: ColumnSchema, acc: SummaryAccumulator): string | number {
    if (!col.summarize) return '';

    const cfg = col.summaryConfig;
    const currency = (col as any).currency as string | undefined;

    if (col.summaryFn) return col.summaryFn(acc.values);

    // Without a config (and without .money()) the raw rounded number is returned,
    // which is what the footer showed before SummaryConfig existed.
    const out = (value: number): string | number =>
        (!cfg && !currency ? Math.round(value * 100) / 100 : formatSummary(value, cfg, currency));

    switch (col.summarize) {
        case 'sum':
            return out(acc.sum);
        case 'average': {
            if (acc.nums.length === 0) return out(0);
            return out(acc.sum / acc.nums.length);
        }
        case 'count': {
            const n = cfg?.excludeNull === false ? acc.total : acc.n;
            return cfg ? formatSummary(n, cfg) : n;
        }
        case 'min':
            return acc.nums.length === 0 ? EMPTY_SUMMARY : out(acc.min);
        case 'max':
            return acc.nums.length === 0 ? EMPTY_SUMMARY : out(acc.max);
        case 'median': {
            const m = median(acc.nums);
            return m === null ? EMPTY_SUMMARY : out(m);
        }
        case 'range': {
            if (acc.nums.length === 0) return EMPTY_SUMMARY;
            const sep = cfg?.separator ?? ' – ';
            return `${formatSummary(acc.min, cfg, currency)}${sep}${formatSummary(acc.max, cfg, currency)}`;
        }
        default:
            return '';
    }
}

/** Convenience: accumulate + summarise in one call (used for per-group footers). */
export function computeSummary(col: ColumnSchema, rows: any[]): string | number {
    return summariseAcc(col, accumulate(rows, col.key, { keepValues: !!col.summaryFn }));
}

/** Footer label, e.g. "Total" for a sum, unless the config names one. */
export function summaryLabel(col: ColumnSchema): string | undefined {
    if (col.summaryConfig?.label) return col.summaryConfig.label;
    return undefined;
}
