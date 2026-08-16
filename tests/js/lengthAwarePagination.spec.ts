import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { roles: [], permissions: [] } } } }),
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('vue-sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { mountMyraTable } from './helpers/myraTable';
// The REAL length-aware payload — the regression guard for the new branch.
import lengthAwarePage from './fixtures/length-aware-page.json';

beforeEach(() => {
    vi.clearAllMocks();
    (globalThis as any).route = (name: string) => `/${name}`;
});

describe('DataTable — length-aware pagination is untouched', () => {
    it('still renders numbered page buttons', () => {
        mountMyraTable({ data: lengthAwarePage }).assertPaginationMode('length-aware');
    });

    it('still renders the Showing from-to of total line', () => {
        const harness = mountMyraTable({ data: lengthAwarePage });
        const m = lengthAwarePage.meta;

        expect(harness.wrapper().text()).toContain(`Showing ${m.from}-${m.to} of ${m.total}`);
    });

    it('has no cursor controls at all', () => {
        const labels = mountMyraTable({ data: lengthAwarePage })
            .wrapper().findAll('button').map(b => b.text());

        expect(labels).not.toContain('Previous');
        expect(labels).not.toContain('Next');
    });

    it('navigates through meta.links exactly as before', async () => {
        const harness = mountMyraTable({ data: lengthAwarePage });
        const two = harness.wrapper().findAll('button').find(b => b.text() === '2')!;

        await two.trigger('click');

        const target = lengthAwarePage.meta.links.find(l => l.label === '2')!.url;
        expect(router.get).toHaveBeenCalledWith(target, {}, expect.anything());
    });

    it('renders every row on the page without virtualisation', () => {
        mountMyraTable({ data: lengthAwarePage }).assertRowCount(lengthAwarePage.data.length);
    });
});
