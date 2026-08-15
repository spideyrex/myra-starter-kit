import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

vi.mock('@inertiajs/vue3', () => ({ router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));

import { TableView, signature, useTableViews, RESERVED_COLUMNS_VIEW } from '@/composables/useTableViews';
import type { SavedView, TableViewPayload } from '@/types/table-views';

function saved(overrides: Partial<SavedView> = {}): SavedView {
    return {
        id: 1,
        slug: 'ABC',
        name: 'Saved',
        visibility: 'private',
        is_default: false,
        owned: true,
        payload: { search: 'ali' },
        ...overrides,
    };
}

describe('signature()', () => {
    it('is stable under key reordering', () => {
        const a: TableViewPayload = { search: 'x', sort: 'name', filters: { b: '2', a: '1' } };
        const b: TableViewPayload = { filters: { a: '1', b: '2' }, sort: 'name', search: 'x' };

        expect(signature(a)).toBe(signature(b));
    });

    it('treats empty containers and blank strings as absent', () => {
        expect(signature({ search: '', filters: {}, columnOrder: [] })).toBe(signature({}));
    });

    it('separates payloads that actually differ', () => {
        expect(signature({ search: 'a' })).not.toBe(signature({ search: 'b' }));
    });
});

describe('useTableViews', () => {
    function harness(savedViews: SavedView[] = [], declared: TableView[] = []) {
        const state = ref<TableViewPayload>({ search: 'ali' });
        const applied: TableViewPayload[] = [];

        const views = useTableViews({
            tableKey: ref('admin.users.index'),
            savedViews: ref(savedViews),
            declared: ref(declared),
            current: () => state.value,
            apply: (payload) => { applied.push(payload); state.value = { ...payload }; },
        });

        return { views, state, applied };
    }

    it('excludes the reserved __columns name from the list', () => {
        const { views } = harness([saved({ id: 1, name: 'Keep' }), saved({ id: 2, name: RESERVED_COLUMNS_VIEW })]);

        expect(views.all.value.map(v => v.name)).toEqual(['Keep']);
    });

    it('merges page-declared views with server-saved ones', () => {
        const declared = TableView.make('Active').filters({ status: 'active' }).sort('name', 'desc');
        const { views } = harness([saved({ name: 'Mine' })], [declared]);

        expect(views.all.value.map(v => v.name)).toEqual(['Active', 'Mine']);
        expect(views.all.value[0].id).toBeNull();
        expect(views.all.value[0].payload).toEqual({
            filters: { status: 'active' },
            sort: 'name',
            direction: 'desc',
        });
    });

    it('drops a declared view the actor lacks permission for', () => {
        const declared = TableView.make('Secret').permission('users.view');
        const state = ref<TableViewPayload>({});
        const views = useTableViews({
            tableKey: ref('k'),
            savedViews: ref([]),
            declared: ref([declared]),
            current: () => state.value,
            apply: () => {},
            can: () => false,
        });

        expect(views.all.value).toHaveLength(0);
    });

    it('applyView pushes the whole payload through apply() and marks the view active', () => {
        const payload: TableViewPayload = {
            search: 'ali',
            sort: 'name',
            direction: 'desc',
            per_page: 25,
            filters: { status: 'active' },
            dateRanges: { created: { from: '2026-01-01', to: '2026-02-01' } },
            query: { q: { conjunction: 'and', rules: [], groups: [] } },
            columns: { email: false },
            columnOrder: ['id', 'name'],
        };
        const view = saved({ payload });
        const { views, applied } = harness([view]);

        views.applyView(views.all.value[0]);

        expect(applied).toHaveLength(1);
        expect(applied[0]).toEqual(payload);
        expect(views.active.value?.id).toBe(1);
    });

    it('isModified flips when the current state drifts and clears when the view is re-applied', () => {
        const view = saved({ payload: { search: 'ali' } });
        const { views, state } = harness([view]);

        views.applyView(views.all.value[0]);
        expect(views.isModified.value).toBe(false);

        state.value = { search: 'ali', filters: { status: 'active' } };
        expect(views.isModified.value).toBe(true);

        views.applyView(views.all.value[0]);
        expect(views.isModified.value).toBe(false);
    });

    it('reports no modification while no view is active', () => {
        const { views, state } = harness([saved()]);
        state.value = { search: 'changed' };

        expect(views.isModified.value).toBe(false);
    });

    it('exposes the declared default view', () => {
        const declared = TableView.make('Everything').default();
        const { views } = harness([], [declared]);

        expect(views.defaultView.value?.name).toBe('Everything');
    });
});
