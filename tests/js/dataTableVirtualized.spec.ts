import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { roles: [], permissions: [] } } } }),
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('vue-sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ToggleColumn, TextColumn } from '@/composables/useTableSchema';
import { mountMyraTable, scaleColumns } from './helpers/myraTable';
import lengthAwarePage from './fixtures/length-aware-page.json';

/** Big enough that the window is obviously doing the work, small enough for jsdom. */
const BIG = 1000;

/** A page of the same SHAPE the server produces, grown to a size worth windowing. */
function bigPage(n: number) {
    const template = lengthAwarePage.data[0];

    return {
        ...lengthAwarePage,
        data: Array.from({ length: n }, (_, i) => ({
            ...template,
            id: i + 1,
            name: `Scale Row ${String(i + 1).padStart(5, '0')}`,
            email: `scale${i + 1}@example.test`,
        })),
        meta: { ...lengthAwarePage.meta, total: n, to: n, per_page: n, last_page: 1 },
    };
}

beforeEach(() => {
    vi.clearAllMocks();
    (globalThis as any).route = (name: string) => `/${name}`;
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('DataTable — virtualised rows', () => {
    it('renders a window, not the whole page', () => {
        mountMyraTable({ data: bigPage(BIG), virtualized: true }).assertRenderedRowsAtMost(30);
    });

    // The control proves the flag is what does the work — rendering every row in
    // jsdom is slow on purpose, which is the whole point of the window.
    it('the control mount without the flag renders every row', () => {
        mountMyraTable({ data: bigPage(BIG) }).assertRowCount(BIG);
    }, 60_000);

    it('keeps total scroll height intact with spacer rows', () => {
        const harness = mountMyraTable({ data: bigPage(BIG), virtualized: true });
        const spacers = harness.wrapper().findAll('tbody tr[aria-hidden="true"]');
        const padded = spacers.reduce((sum, tr) => sum + parseInt((tr.attributes('style') || '0').replace(/\D/g, ''), 10), 0);

        expect(padded + harness.rows().length * 44).toBe(BIG * 44);
    });

    it('shifts the window on scroll and keeps its size constant', async () => {
        const harness = mountMyraTable({ data: bigPage(BIG), virtualized: true });
        const before = harness.rows().map(r => r.text());

        await harness.scrollTo(44 * 500);

        const after = harness.rows().map(r => r.text());
        expect(after.length).toBe(before.length);
        expect(after[0]).not.toBe(before[0]);
        expect(after[0]).toContain('Scale Row 00');
    });

    it('refuses to virtualise a grouped table and says so', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const harness = mountMyraTable({ data: bigPage(40), virtualized: true, groupBy: 'status' });

        expect(harness.rows().length).toBeGreaterThanOrEqual(40);
        expect(warn).toHaveBeenCalled();

        warn.mockRestore();
    });

    /**
     * The row.id proof: the optimistic paint and its rollback are keyed by the row
     * object the closure captured, so scrolling the window away and back in between
     * still rolls back the right row.
     */
    it('rolls an inline edit back onto the right row across a scroll', async () => {
        const columns = [
            ...scaleColumns,
            ToggleColumn.make('flag').label('Flag').updateRoute('admin.demo.inline-update'),
        ];

        const data = bigPage(BIG);
        data.data.forEach((row: any) => { row.flag = false; });

        const harness = mountMyraTable({ data, virtualized: true, columns });

        const target = harness.rows()[4];
        expect(target.text()).toContain('Scale Row 00005');

        await target.find('button[role="switch"]').trigger('click');

        expect(data.data[4].flag).toBe(true);
        expect(router.patch).toHaveBeenCalledTimes(1);

        await harness.scrollTo(44 * 900);
        await harness.scrollTo(0);

        // The request finishes (unsuccessfully) only now — a 403 never reaches onError.
        const options = (router.patch as any).mock.calls[0][2];
        options.onFinish();

        expect(data.data[4].flag).toBe(false);
        expect(data.data[3].flag).toBe(false);
        expect(toast.error).toHaveBeenCalled();
    });

    it('an unvirtualised table with the same columns behaves identically', () => {
        const columns = [...scaleColumns, TextColumn.make('flag').label('Flag')];
        const data = bigPage(12);

        mountMyraTable({ data, columns }).assertRowCount(12);
    });
});
