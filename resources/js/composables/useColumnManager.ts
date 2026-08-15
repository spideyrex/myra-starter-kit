import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { ColumnSchema } from '@/types/admin';
import type { ColumnManagerEntry, TableViewPayload } from '@/types/table-views';

interface StoredPrefs {
    v: 2;
    visible: Record<string, boolean>;
    order: string[];
}

/**
 * A column is manageable when it opted in explicitly, or when it is simply not
 * hidden by its schema. `BaseColumn._toggleable` stays false by default, so the
 * second clause is what puts every ordinary column in the manager.
 */
export function isManageableColumn(col: ColumnSchema): boolean {
    return (col as any).toggleable === true || (col as any).hidden !== true;
}

export function useColumnManager(opts: {
    columns: Ref<ColumnSchema[]>;
    storageKey: Ref<string>;
    persist?: 'local' | 'none';
}) {
    const visible = ref<Record<string, boolean>>({});
    const order = ref<string[]>([]);
    const persist = opts.persist ?? 'local';

    function read(): void {
        visible.value = {};
        order.value = [];
        if (persist !== 'local' || typeof window === 'undefined') return;

        try {
            const raw = localStorage.getItem(opts.storageKey.value);
            if (!raw) return;
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && (parsed as StoredPrefs).v === 2) {
                visible.value = { ...((parsed as StoredPrefs).visible ?? {}) };
                order.value = [...((parsed as StoredPrefs).order ?? [])];
                return;
            }
            // v1 was a bare Record<string, boolean>; migrate in place.
            if (parsed && typeof parsed === 'object') {
                const migrated: Record<string, boolean> = {};
                for (const [key, value] of Object.entries(parsed)) {
                    if (typeof value === 'boolean') migrated[key] = value;
                }
                visible.value = migrated;
                write();
            }
        } catch {}
    }

    function write(): void {
        if (persist !== 'local' || typeof window === 'undefined') return;
        try {
            const payload: StoredPrefs = { v: 2, visible: visible.value, order: order.value };
            localStorage.setItem(opts.storageKey.value, JSON.stringify(payload));
        } catch {}
    }

    read();
    watch(opts.storageKey, () => read());

    const orderedColumns: ComputedRef<ColumnSchema[]> = computed(() => {
        const cols = opts.columns.value;
        if (order.value.length === 0) return cols;

        const byKey = new Map(cols.map(c => [c.key, c]));
        const seen = new Set<string>();
        const out: ColumnSchema[] = [];
        for (const key of order.value) {
            const col = byKey.get(key);
            if (col && !seen.has(key)) {
                out.push(col);
                seen.add(key);
            }
        }
        for (const col of cols) {
            if (!seen.has(col.key)) out.push(col);
        }
        return out;
    });

    function isVisible(col: ColumnSchema): boolean {
        if (!isManageableColumn(col)) return (col as any).hidden !== true;
        const override = visible.value[col.key];
        return override === undefined ? (col as any).hidden !== true : override;
    }

    const visibleColumns: ComputedRef<ColumnSchema[]> = computed(() =>
        orderedColumns.value.filter(isVisible),
    );

    const entries: ComputedRef<ColumnManagerEntry[]> = computed(() =>
        orderedColumns.value.filter(isManageableColumn).map(col => ({
            key: col.key,
            label: col.label,
            visible: isVisible(col),
            pinned: (col as any).pinned,
            group: (col as any).group,
        })),
    );

    function toggle(key: string, value: boolean): void {
        visible.value = { ...visible.value, [key]: value };
        write();
    }

    function reorder(from: number, to: number): void {
        const cols = orderedColumns.value;
        const manageable = cols.filter(isManageableColumn).map(c => c.key);
        if (from < 0 || to < 0 || from >= manageable.length || to >= manageable.length || from === to) return;

        const [moved] = manageable.splice(from, 1);
        manageable.splice(to, 0, moved);

        let i = 0;
        order.value = cols.map(c => (isManageableColumn(c) ? manageable[i++] : c.key));
        write();
    }

    function move(key: string, delta: -1 | 1): void {
        const index = entries.value.findIndex(e => e.key === key);
        if (index === -1) return;
        reorder(index, index + delta);
    }

    function reset(): void {
        visible.value = {};
        order.value = [];
        if (persist === 'local' && typeof window !== 'undefined') {
            try { localStorage.removeItem(opts.storageKey.value); } catch {}
        }
    }

    function apply(payload: Pick<TableViewPayload, 'columns' | 'columnOrder'>): void {
        visible.value = { ...(payload.columns ?? {}) };
        order.value = [...(payload.columnOrder ?? [])];
        write();
    }

    function snapshot(): Pick<TableViewPayload, 'columns' | 'columnOrder'> {
        return { columns: { ...visible.value }, columnOrder: [...order.value] };
    }

    const isDefault = computed(() => {
        const cols = opts.columns.value;
        const schemaOrder = cols.map(c => c.key).join('|');
        const orderMatches = order.value.length === 0 || order.value.join('|') === schemaOrder;
        const visibilityMatches = Object.entries(visible.value).every(([key, value]) => {
            const col = cols.find(c => c.key === key);
            return !col || value === ((col as any).hidden !== true);
        });
        return orderMatches && visibilityMatches;
    });

    return { visibleColumns, entries, toggle, move, reorder, reset, apply, snapshot, isDefault };
}
