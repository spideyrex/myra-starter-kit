import type { QueryGroup } from '@/types/admin';

/**
 * SEAM — structural copies of the report wire shape owned by the report bundle
 * (`@/types/reports`). Kept local so this bundle builds standalone.
 * POST-MERGE: delete ReportRow/Delta/Direction below and re-export from
 * '@/types/reports'; QueryGroup already lives in '@/types/admin'.
 */
export type RuleGroup = QueryGroup;

export type Direction = 'up' | 'down' | 'flat';

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

// --- schedules ---------------------------------------------------------------

export type ScheduleFrequency = 'daily' | 'weekly' | 'monthly' | 'quarterly';
export type ScheduleFormat = 'pdf' | 'xlsx' | 'csv' | 'inline';

export type ScheduleRecipient =
    | { type: 'user'; id: number }
    | { type: 'email'; address: string };

export interface ScheduleInput {
    report_key: string;
    name: string;
    state: Record<string, unknown>;
    format: ScheduleFormat;
    frequency: ScheduleFrequency;
    day_of_week: number | null;
    day_of_month: number | null;
    hour: number;
    minute: number;
    timezone: string;
    recipients: ScheduleRecipient[];
    subject?: string | null;
    message?: string | null;
    skip_if_empty: boolean;
    is_active: boolean;
}

export interface ReportSchedule extends ScheduleInput {
    id: number;
    slug: string;
    next_run_at: string | null;
    last_run_at: string | null;
    last_status: string | null;
    last_error: string | null;
    failure_count: number;
}
