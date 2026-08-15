import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));

import { buildTableParams } from '@/composables/useTableViews';

describe('buildTableParams', () => {
    it('preserves non-prefixed params from the URL when a query prefix is in play', () => {
        const params = buildTableParams(
            { search: 'ali' },
            { queryPrefix: 'p_', currentSearch: '?other=1&p_search=stale' },
        );

        expect(params.other).toBe('1');
        expect(params.p_search).toBe('ali');
    });

    it('never emits page', () => {
        const params = buildTableParams(
            { search: 'ali', sort: 'name', direction: 'desc', per_page: 25 },
            {},
        );

        expect(Object.keys(params)).not.toContain('page');
    });

    it('omits an ascending direction and emits a descending one', () => {
        expect(buildTableParams({ sort: 'name', direction: 'asc' }, {}).direction).toBeUndefined();
        expect(buildTableParams({ sort: 'name', direction: 'desc' }, {}).direction).toBe('desc');
    });

    it('emits per_page only when set', () => {
        expect(buildTableParams({}, {}).per_page).toBeUndefined();
        expect(buildTableParams({ per_page: 50 }, {}).per_page).toBe(50);
    });

    it('emits filters, date ranges and non-empty query trees', () => {
        const params = buildTableParams({
            filters: { status: 'active', empty: '' },
            dateRanges: { created: { from: '2026-01-01', to: '' } },
            query: {
                q: { conjunction: 'and', rules: [{ field: 'a', operator: 'eq', value: '1' }], groups: [] },
                blank: { conjunction: 'and', rules: [], groups: [] },
            },
        }, {});

        expect(params.status).toBe('active');
        expect(params.empty).toBeUndefined();
        expect(params.created_from).toBe('2026-01-01');
        expect(params.created_to).toBeUndefined();
        expect(typeof params.q).toBe('string');
        expect(params.blank).toBeUndefined();
    });
});
