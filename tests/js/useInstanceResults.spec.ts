import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { useInstanceResults } from '@/composables/useInstanceResults';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

function schema(key: string, binding: Record<string, unknown> | undefined, type = 'chart'): WidgetSchema {
    return { key, type, colSpan: 1, title: key, binding } as unknown as WidgetSchema;
}

function ok(results: Record<string, unknown>) {
    return vi.fn(async () => ({ ok: true, json: async () => ({ results }) }) as unknown as Response);
}

function build(widgets: WidgetSchema[], request: any) {
    return useInstanceResults({
        widgets: ref(widgets),
        request,
        resolveRoute: (name: string) => `/${name}`,
    });
}

describe('useInstanceResults', () => {
    it('makes no request at all when there is no instance', async () => {
        const request = ok({});

        const r = build([], request);
        await flushPromises();

        expect(request).not.toHaveBeenCalled();
        expect(r.results.value).toEqual({});
        expect(r.loading.value).toEqual({});
    });

    it('batches ONE post and keys the results by widget key', async () => {
        const request = ok({ 'trend#aa': { rows: [] } });

        const r = build([
            schema('trend#aa', { report: 'users', dimension: 'created_at', measures: ['signups'], limit: 12 }),
            schema('count#bb', { report: 'users', measures: ['signups'] }, 'stat'),
        ], request);

        await flushPromises();

        expect(request).toHaveBeenCalledTimes(1);

        const [url, init] = request.mock.calls[0];
        const body = JSON.parse(init.body);

        expect(url).toBe('/admin.reports.widgets');
        expect(init.method).toBe('POST');
        expect(Object.keys(body.widgets)).toEqual(['trend#aa', 'count#bb']);
        expect(body.widgets['trend#aa']).toEqual({
            report: 'users', dimension: 'created_at', measures: ['signups'], limit: 12,
        });
        // A stat tile asks for the stat mode, not a series.
        expect(body.widgets['count#bb'].mode).toBe('stat');
        expect(r.results.value['trend#aa']).toEqual({ rows: [] });
        expect(r.loading.value).toEqual({});
    });

    it('never sends anything the report request does not understand', async () => {
        const request = ok({});

        build([schema('trend#aa', {
            report: 'users', dimension: 'created_at', table: 'users', raw: '1=1',
        })], request);

        await flushPromises();

        const body = JSON.parse(request.mock.calls[0][1].body);

        expect(body.widgets['trend#aa']).toEqual({ report: 'users', dimension: 'created_at' });
    });

    it('skips a tile whose binding names no report — only the server mints one', async () => {
        const request = ok({});

        build([schema('pending#cc', { dimension: 'created_at' })], request);
        await flushPromises();

        expect(request).not.toHaveBeenCalled();
    });

    it('fails soft on a rejected request: no throw, no results, no stuck spinner', async () => {
        const request = vi.fn(async () => { throw new Error('offline'); });

        const r = build([schema('trend#aa', { report: 'users' })], request);
        await flushPromises();

        expect(r.results.value).toEqual({});
        expect(r.loading.value).toEqual({});
    });

    it('fails soft on a non-ok response', async () => {
        const request = vi.fn(async () => ({ ok: false, json: async () => ({}) }) as unknown as Response);

        const r = build([schema('trend#aa', { report: 'users' })], request);
        await flushPromises();

        expect(r.results.value).toEqual({});
        expect(r.loading.value).toEqual({});
    });

    it('refetches when the bindings change and drops a stale response', async () => {
        const widgets = ref<WidgetSchema[]>([schema('trend#aa', { report: 'users', dimension: 'created_at' })]);
        const request = ok({ 'trend#aa': { rows: [1] } });

        const r = useInstanceResults({ widgets, request, resolveRoute: (name: string) => `/${name}` });
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(1);

        widgets.value = [schema('trend#aa', { report: 'users', dimension: 'status' })];
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(2);
        expect(r.results.value['trend#aa']).toEqual({ rows: [1] });
    });
});
