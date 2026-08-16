import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { roles: [], permissions: [] } } } }),
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('vue-sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

import { buildTableParams, stripVolatile, VOLATILE_PARAMS } from '@/composables/useTableViews';
import { mountMyraTable } from './helpers/myraTable';
import cursorPage from './fixtures/cursor-page.json';

beforeEach(() => {
    (globalThis as any).route = (name: string) => `/${name}`;
});

describe('volatile params', () => {
    it('names exactly the two params that describe a position', () => {
        expect([...VOLATILE_PARAMS]).toEqual(['page', 'cursor']);
    });

    it('buildTableParams never emits page or cursor', () => {
        const params = buildTableParams(
            { search: 'x', sort: 'name', direction: 'desc', per_page: 25 } as any,
            { queryPrefix: 'p_', currentSearch: '?page=3&cursor=abc&other=keep' },
        );

        expect(params).not.toHaveProperty('page');
        expect(params).not.toHaveProperty('cursor');
        expect(params).not.toHaveProperty('p_page');
        expect(params).not.toHaveProperty('p_cursor');
        expect(params.other).toBe('keep');
        expect(params.p_search).toBe('x');
    });

    it('stripVolatile keeps the query and drops the position', () => {
        expect(stripVolatile('?cursor=abc&search=x')).toBe('?search=x');
        expect(stripVolatile('?page=4&search=x')).toBe('?search=x');
        expect(stripVolatile('?p_cursor=abc&p_search=x', 'p_')).toBe('?p_search=x');
        // Another table's prefixed cursor is not this table's business.
        expect(stripVolatile('?q_cursor=abc&search=x', 'p_')).toBe('?q_cursor=abc&search=x');
        expect(stripVolatile('?cursor=abc')).toBe('');
    });

    it('captureState persists neither key even when both are in the URL', () => {
        window.history.replaceState({}, '', '/admin/demo/scale?page=7&cursor=abc&search=widget');

        const harness = mountMyraTable({ data: cursorPage, tableKey: 'admin.demo.scale' });
        const state = (harness.wrapper().vm as any).captureState();

        expect(state).not.toHaveProperty('page');
        expect(state).not.toHaveProperty('cursor');
        expect(JSON.stringify(state)).not.toContain('cursor');
    });
});
