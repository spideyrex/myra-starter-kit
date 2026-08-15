import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

import { useReportState } from '@/composables/useReportState';
import type { ReportResultPayload, ReportSchema, ReportState } from '@/types/reports';

(globalThis as any).route = (name: string, param?: string) =>
    `/admin/${name.replace(/\./g, '-')}${param ? '/' + param : ''}`;

function schema(): ReportSchema {
    return {
        key: 'users',
        titleKey: 'reports.users.title',
        dimensions: [
            { key: 'created_at', labelKey: 'reports.dim.signupDate', type: 'date', drillable: true, allowedBuckets: ['day', 'week', 'month'] },
            { key: 'status', labelKey: 'reports.dim.status', type: 'field', drillable: true, allowedBuckets: [] },
        ],
        measures: [
            { key: 'signups', labelKey: 'reports.measure.signups', format: 'number', decimals: 0, goal: null, invertTrend: false, additive: true },
            { key: 'emails', labelKey: 'reports.measure.uniqueEmails', format: 'number', decimals: 0, goal: null, invertTrend: false, additive: false },
        ],
        comparisons: ['previous', 'year'],
        periods: ['last_7_days', 'last_30_days', 'custom'],
        defaults: { dimension: 'created_at', measures: ['signups'], period: 'last_30_days', chart: 'area' },
        fields: { maxRules: 25, maxDepth: 3, fields: [] },
        formats: ['csv', 'xlsx'],
        maxGroups: 200,
        maxMeasures: 6,
        schedulable: true,
        drillable: true,
    };
}

function initialState(): ReportState {
    return {
        period: { preset: 'last_30_days' },
        compare: 'none',
        dimension: 'created_at',
        bucket: 'day',
        measures: ['signups'],
        limit: 20,
        chart: 'area',
        query: null,
        cross: {},
    };
}

function initialResult(): ReportResultPayload {
    return {
        report: 'users',
        state: initialState(),
        period: { preset: 'last_30_days', from: '2026-07-18', to: '2026-08-16', tz: 'UTC', bucket: 'day' },
        comparison: null,
        dimension: { key: 'created_at', labelKey: 'reports.dim.signupDate', type: 'date', drillable: true, allowedBuckets: ['day'] },
        measures: [schema().measures[0]],
        rows: [{ key: '2026-07-18', label: '18 Jul', values: { signups: 4 }, previous: null, deltas: { signups: null }, isOther: false, drill: null }],
        totals: { signups: 4 },
        previousTotals: null,
        deltas: { signups: null },
        truncated: false,
        groupCount: 1,
    };
}

function harness() {
    return useReportState({
        reportKey: 'users',
        schema: ref(schema()),
        initial: initialState(),
        initialResult: initialResult(),
        syncUrl: false,
    });
}

function okResponse(body: unknown) {
    return { ok: true, status: 200, json: async () => body } as unknown as Response;
}

function errorResponse(message: string) {
    return { ok: false, status: 422, json: async () => ({ message }) } as unknown as Response;
}

describe('useReportState', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock = vi.fn().mockResolvedValue(okResponse({ ...initialResult(), groupCount: 9 }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('debounces three rapid mutations into a single request', async () => {
        const report = harness();

        report.setBucket('day');
        report.setBucket('week');
        report.setBucket('month');

        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(report.state.value.bucket).toBe('month');
    });

    it('posts the state as JSON with the CSRF header', async () => {
        const report = harness();

        report.setCompare('previous');
        await vi.advanceTimersByTimeAsync(300);

        const [, init] = fetchMock.mock.calls[0];
        expect(init.method).toBe('POST');
        expect(init.credentials).toBe('same-origin');
        expect(init.headers['X-CSRF-TOKEN']).toBeDefined();
        expect(JSON.parse(init.body).state.compare).toBe('previous');
    });

    it('applies a successful response to result', async () => {
        const report = harness();

        report.setCompare('previous');
        await vi.advanceTimersByTimeAsync(300);

        expect(report.result.value.groupCount).toBe(9);
        expect(report.error.value).toBeNull();
    });

    it('surfaces a 422 key and leaves the previous result intact', async () => {
        const report = harness();
        const before = JSON.stringify(report.result.value);

        fetchMock.mockResolvedValueOnce(errorResponse('reports.errors.tooManyBuckets'));
        report.setBucket('week');
        await vi.advanceTimersByTimeAsync(300);

        expect(report.error.value).toBe('reports.errors.tooManyBuckets');
        expect(JSON.stringify(report.result.value)).toBe(before);
    });

    it('falls back to a generic key when the error body is not JSON', async () => {
        const report = harness();

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: async () => { throw new Error('not json'); },
        } as unknown as Response);

        report.setBucket('week');
        await vi.advanceTimersByTimeAsync(300);

        expect(report.error.value).toBe('reports.errors.failed');
    });

    it('drops a stale bucket when switching to a non-date dimension', async () => {
        const report = harness();

        report.setDimension('status');
        await vi.advanceTimersByTimeAsync(300);

        expect(report.state.value.bucket).toBeNull();
    });

    it('never empties the measure list', async () => {
        const report = harness();

        report.toggleMeasure('signups');
        await vi.advanceTimersByTimeAsync(300);

        expect(report.state.value.measures).toEqual(['signups']);
    });

    it('toggles a measure on and off again', async () => {
        const report = harness();

        report.toggleMeasure('emails');
        await vi.advanceTimersByTimeAsync(300);
        expect(report.state.value.measures).toEqual(['signups', 'emails']);

        report.toggleMeasure('emails');
        await vi.advanceTimersByTimeAsync(300);
        expect(report.state.value.measures).toEqual(['signups']);
    });

    it('round-trips a payload losslessly', async () => {
        const report = harness();

        report.setCompare('year');
        report.setLimit(7);
        await vi.advanceTimersByTimeAsync(300);

        const payload = report.toPayload();
        report.setCompare('none');
        await vi.advanceTimersByTimeAsync(300);

        report.applyPayload(payload);
        await vi.advanceTimersByTimeAsync(300);

        expect(report.toPayload()).toEqual(payload);
    });

    it('carries the serialised state in the export href', () => {
        const report = harness();
        const href = report.exportHref('csv');

        expect(href).toContain('format=csv');

        const state = new URL(href, 'http://localhost').searchParams.get('state');
        expect(JSON.parse(state as string).dimension).toBe('created_at');
    });

    it('keeps cross-filter fragments keyed by their emitting widget', async () => {
        const report = harness();
        const group = { conjunction: 'and' as const, rules: [], groups: [] };

        report.setCross('widget-a', group);
        await vi.advanceTimersByTimeAsync(300);
        expect(Object.keys(report.state.value.cross ?? {})).toEqual(['widget-a']);

        report.clearCross('widget-a');
        await vi.advanceTimersByTimeAsync(300);
        expect(report.state.value.cross).toEqual({});
    });

    it('does not hit the server for a chart-type change', async () => {
        const report = harness();

        report.setChart('pie');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(report.state.value.chart).toBe('pie');
    });
});
