import { beforeEach, describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useColumnManager, isManageableColumn } from '@/composables/useColumnManager';
import type { ColumnSchema } from '@/types/admin';

function col(key: string, extra: Partial<ColumnSchema> = {}): ColumnSchema {
    return {
        key,
        label: key.toUpperCase(),
        type: 'text',
        sortable: false,
        searchable: false,
        hidden: false,
        alignRight: false,
        toggleable: false,
        grow: false,
        ...extra,
    } as ColumnSchema;
}

const schema = [col('id'), col('name'), col('email'), col('secret', { hidden: true })];

beforeEach(() => {
    localStorage.clear();
});

describe('useColumnManager', () => {
    it('treats every non-hidden column as manageable without touching useTableSchema', () => {
        expect(isManageableColumn(col('id'))).toBe(true);
        expect(isManageableColumn(col('secret', { hidden: true }))).toBe(false);
        expect(isManageableColumn(col('optIn', { hidden: true, toggleable: true }))).toBe(true);
    });

    it('upgrades a v1 Record<string, boolean> in localStorage without losing visibility', () => {
        localStorage.setItem('dt-columns-users', JSON.stringify({ email: false }));

        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('dt-columns-users') });

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'name']);

        const stored = JSON.parse(localStorage.getItem('dt-columns-users') as string);
        expect(stored.v).toBe(2);
        expect(stored.visible).toEqual({ email: false });
        expect(stored.order).toEqual([]);
    });

    it('writes under exactly the storage key it was given (query prefix included)', () => {
        const manager = useColumnManager({
            columns: ref(schema),
            storageKey: ref('dt-columns-admin.users.index:p_'),
        });

        manager.toggle('email', false);

        expect(localStorage.getItem('dt-columns-admin.users.index:p_')).toContain('"email":false');
        expect(localStorage.getItem('dt-columns-admin.users.index')).toBeNull();
    });

    it('move() reorders and visibleColumns reflects the new order', () => {
        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('k') });

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'name', 'email']);

        manager.move('email', -1);

        expect(manager.entries.value.map(e => e.key)).toEqual(['id', 'email', 'name']);
        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'email', 'name']);
        expect(manager.isDefault.value).toBe(false);
    });

    it('move() at the boundary is a no-op', () => {
        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('k') });

        manager.move('id', -1);

        expect(manager.entries.value.map(e => e.key)).toEqual(['id', 'name', 'email']);
    });

    it('reset() restores schema defaults, clears storage and flips isDefault back to true', () => {
        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('k') });

        manager.toggle('email', false);
        manager.move('name', 1);
        expect(manager.isDefault.value).toBe(false);

        manager.reset();

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'name', 'email']);
        expect(manager.isDefault.value).toBe(true);
        expect(localStorage.getItem('k')).toBeNull();
    });

    it('apply()/snapshot() round-trip a saved view payload', () => {
        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('k') });

        manager.apply({ columns: { name: false }, columnOrder: ['email', 'id', 'name', 'secret'] });

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['email', 'id']);
        expect(manager.snapshot()).toEqual({
            columns: { name: false },
            columnOrder: ['email', 'id', 'name', 'secret'],
        });
    });

    it('persist: none never writes to localStorage', () => {
        const manager = useColumnManager({ columns: ref(schema), storageKey: ref('k'), persist: 'none' });

        manager.toggle('email', false);

        expect(localStorage.getItem('k')).toBeNull();
        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'name']);
    });

    it('can turn a hidden but opted-in column on', () => {
        const columns = [col('id'), col('audit', { hidden: true, toggleable: true })];
        const manager = useColumnManager({ columns: ref(columns), storageKey: ref('k') });

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id']);
        expect(manager.entries.value.map(e => e.key)).toEqual(['id', 'audit']);

        manager.toggle('audit', true);

        expect(manager.visibleColumns.value.map(c => c.key)).toEqual(['id', 'audit']);
    });
});
