import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { roles: [], permissions: [] } } } }),
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('vue-sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { mountMyraTable } from './helpers/myraTable';
// The REAL cursor payload, written by tests/Feature/Performance/CursorPayloadTest.
import cursorPage from './fixtures/cursor-page.json';

beforeEach(() => {
    vi.clearAllMocks();
    (globalThis as any).route = (name: string) => `/${name}`;
});

describe('DataTable — cursor pagination', () => {
    it('renders Previous / Next instead of numbered pages', () => {
        mountMyraTable({ data: cursorPage }).assertPaginationMode('cursor');
    });

    it('renders exactly the rows the server sent', () => {
        mountMyraTable({ data: cursorPage }).assertRowCount(cursorPage.data.length);
    });

    it('disables Previous on the first page and enables Next', () => {
        const harness = mountMyraTable({ data: cursorPage });
        const buttons = harness.wrapper().findAll('button');

        const prev = buttons.find(b => b.text() === 'Previous')!;
        const next = buttons.find(b => b.text() === 'Next')!;

        expect(cursorPage.links.prev).toBeNull();
        expect(prev.attributes('disabled')).toBeDefined();
        expect(next.attributes('disabled')).toBeUndefined();
    });

    it('navigates with the opaque next link the server produced', async () => {
        const harness = mountMyraTable({ data: cursorPage });
        const next = harness.wrapper().findAll('button').find(b => b.text() === 'Next')!;

        await next.trigger('click');

        expect(router.get).toHaveBeenCalledWith(cursorPage.links.next, {}, expect.anything());
    });

    it('reports the row count, never a total the cursor payload does not have', () => {
        const harness = mountMyraTable({ data: cursorPage });

        expect(harness.wrapper().text()).toContain(`Showing ${cursorPage.data.length} rows`);
        expect(cursorPage.meta).not.toHaveProperty('total');
    });
});
