import { computed, ref, toValue, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';

/**
 * Local type copies. `resources/js/types/index.d.ts` is bundle A's file and the
 * builder must not open it; these names are deliberately distinct from A's
 * PageSection* exports so the two never shadow one another.
 */
export interface SectionFieldSchema {
    name: string;
    type: string;
    labelKey: string;
    required: boolean;
    default: unknown;
    max: number | null;
    options?: string[];
    of?: SectionFieldSchema[];
}

export interface SectionSchema {
    key: string;
    labelKey: string;
    descriptionKey: string;
    icon: string;
    group: string;
    titleField: string | null;
    maxPerPage: number;
    variants: Record<string, string[] | 'bool'>;
    fields: SectionFieldSchema[];
}

export interface SectionRow {
    id: string;
    type: string;
    enabled: boolean;
    variant: Record<string, unknown>;
    data: Record<string, unknown>;
}

/** "Inherit the template's own presentation" — reka-ui rejects an empty SelectItem value. */
export const VARIANT_DEFAULT = '__default';

export const SECTION_GROUP_ORDER = ['content', 'proof', 'conversion', 'layout'] as const;

const HISTORY_LIMIT = 50;
const TITLE_LIMIT = 48;
const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/**
 * A client-side ULID so a row keeps ONE identity across a save: SectionWriter
 * only assigns an id when the row arrives without one, so collapse state and
 * Vue keys survive the round trip.
 */
export function ulid(now: number = Date.now()): string {
    let time = '';
    let remaining = Math.max(0, Math.floor(now));

    for (let i = 0; i < 10; i++) {
        time = CROCKFORD[remaining % 32] + time;
        remaining = Math.floor(remaining / 32);
    }

    const bytes = new Uint8Array(16);
    const source = globalThis.crypto;

    if (source && typeof source.getRandomValues === 'function') source.getRandomValues(bytes);
    else for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);

    let random = '';
    for (let i = 0; i < 16; i++) random += CROCKFORD[bytes[i] % 32];

    return time + random;
}

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value ?? null)) as T;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Type-aware, never ''. A select falls back to its first declared option. */
export function defaultForField(field: SectionFieldSchema): unknown {
    switch (field.type) {
        case 'bool':
            return typeof field.default === 'boolean' ? field.default : false;
        case 'number':
            return typeof field.default === 'number' ? field.default : 0;
        case 'list':
            return Array.isArray(field.default) ? clone(field.default) : [];
        case 'select': {
            const options = Array.isArray(field.options) ? field.options : [];

            if (typeof field.default === 'string' && field.default !== ''
                && (options.length === 0 || options.includes(field.default))) {
                return field.default;
            }

            return options[0] ?? VARIANT_DEFAULT;
        }
        default:
            return typeof field.default === 'string' ? field.default : '';
    }
}

/** Defaults for one row of a `list` field, from its flat sub-declaration. */
export function defaultsForListRow(field: SectionFieldSchema): Record<string, unknown> {
    const data: Record<string, unknown> = {};

    for (const sub of field.of ?? []) data[sub.name] = defaultForField(sub);

    return data;
}

export interface UseSectionListOptions {
    schemas: MaybeRefOrGetter<SectionSchema[]>;
    initial?: unknown;
    /** Already-bound translator. The composable never renders English of its own. */
    t: (key: string, named?: Record<string, unknown>) => string;
    /** Key-existence probe. A type whose label has not shipped falls back to its key. */
    has?: (key: string) => boolean;
    maxBlocks?: number;
}

export interface UseSectionList {
    rows: Ref<SectionRow[]>;
    selectedId: Ref<string | null>;
    dirty: ComputedRef<boolean>;
    count: ComputedRef<number>;
    atLimit: ComputedRef<boolean>;
    canUndo: ComputedRef<boolean>;
    canRedo: ComputedRef<boolean>;
    schemaOf(type: string): SectionSchema | undefined;
    labelOf(type: string): string;
    titleOf(row: SectionRow): string;
    countOf(type: string): number;
    canAdd(type: string): boolean;
    defaultsFor(type: string): Record<string, unknown>;
    add(type: string, atIndex?: number): SectionRow | null;
    duplicate(id: string): SectionRow | null;
    remove(id: string): void;
    move(id: string, toIndex: number): void;
    toggle(id: string): void;
    update(id: string, field: string, value: unknown): void;
    updateVariant(id: string, key: string, value: unknown): void;
    load(list: unknown): void;
    discard(): void;
    markSaved(list?: unknown): void;
    undo(): void;
    redo(): void;
    isCollapsed(id: string): boolean;
    toggleCollapsed(id: string): void;
    expand(id: string): void;
    payload(): SectionRow[];
}

export function useSectionList(options: UseSectionListOptions): UseSectionList {
    const maxBlocks = options.maxBlocks ?? 100;

    function schemaList(): SectionSchema[] {
        const list = toValue(options.schemas);

        return Array.isArray(list) ? list : [];
    }

    function schemaOf(type: string): SectionSchema | undefined {
        return schemaList().find(schema => schema.key === type);
    }

    /** Total: a stored list may predate a type, or arrive half-formed. */
    function adopt(list: unknown): SectionRow[] {
        if (!Array.isArray(list)) return [];

        return list.slice(0, maxBlocks).flatMap((raw): SectionRow[] => {
            if (!isPlainObject(raw)) return [];

            const type = typeof raw.type === 'string' ? raw.type : '';
            if (type === '') return [];

            const id = typeof raw.id === 'string' && raw.id !== '' ? raw.id : ulid();

            return [{
                id,
                type,
                enabled: raw.enabled !== false,
                variant: isPlainObject(raw.variant) ? clone(raw.variant) : {},
                data: isPlainObject(raw.data) ? clone(raw.data) : {},
            }];
        });
    }

    const rows = ref<SectionRow[]>(adopt(options.initial)) as Ref<SectionRow[]>;
    const saved = ref<SectionRow[]>(clone(rows.value)) as Ref<SectionRow[]>;
    const selectedId = ref<string | null>(rows.value[0]?.id ?? null);
    const collapsed = ref<Set<string>>(new Set());

    const past = ref<SectionRow[][]>([]) as Ref<SectionRow[][]>;
    const future = ref<SectionRow[][]>([]) as Ref<SectionRow[][]>;

    function commit(next: SectionRow[]): void {
        past.value = [...past.value, clone(rows.value)].slice(-HISTORY_LIMIT);
        future.value = [];
        rows.value = next;
    }

    function indexOf(id: string): number {
        return rows.value.findIndex(row => row.id === id);
    }

    const count = computed(() => rows.value.length);
    const atLimit = computed(() => rows.value.length >= maxBlocks);
    const dirty = computed(() => JSON.stringify(rows.value) !== JSON.stringify(saved.value));
    const canUndo = computed(() => past.value.length > 0);
    const canRedo = computed(() => future.value.length > 0);

    function labelOf(type: string): string {
        const schema = schemaOf(type);
        if (!schema) return type;

        return (options.has?.(schema.labelKey) ?? true) ? options.t(schema.labelKey) : type;
    }

    function titleOf(row: SectionRow): string {
        const label = labelOf(row.type);
        const field = schemaOf(row.type)?.titleField ?? null;
        const raw = field ? row.data?.[field] : null;
        const text = typeof raw === 'string' ? raw.trim() : '';

        if (text === '') return label;

        const combined = `${label} — ${text}`;

        return combined.length > TITLE_LIMIT ? `${combined.slice(0, TITLE_LIMIT - 1)}…` : combined;
    }

    function countOf(type: string): number {
        return rows.value.filter(row => row.type === type).length;
    }

    function canAdd(type: string): boolean {
        const schema = schemaOf(type);
        if (!schema || atLimit.value) return false;

        return schema.maxPerPage <= 0 || countOf(type) < schema.maxPerPage;
    }

    function defaultsFor(type: string): Record<string, unknown> {
        const data: Record<string, unknown> = {};

        for (const field of schemaOf(type)?.fields ?? []) data[field.name] = defaultForField(field);

        return data;
    }

    function add(type: string, atIndex?: number): SectionRow | null {
        if (!canAdd(type)) return null;

        // `variant` starts EMPTY so the chosen template still owns presentation
        // until the author overrides a key explicitly.
        const row: SectionRow = { id: ulid(), type, enabled: true, variant: {}, data: defaultsFor(type) };

        const next = [...rows.value];
        const at = typeof atIndex === 'number' && atIndex >= 0 && atIndex <= next.length ? atIndex : next.length;

        next.splice(at, 0, row);
        commit(next);
        selectedId.value = row.id;

        return row;
    }

    function duplicate(id: string): SectionRow | null {
        const index = indexOf(id);
        if (index < 0 || atLimit.value) return null;

        const source = rows.value[index];
        if (!canAdd(source.type)) return null;

        const copy: SectionRow = { ...clone(source), id: ulid() };
        const next = [...rows.value];

        next.splice(index + 1, 0, copy);
        commit(next);
        selectedId.value = copy.id;

        return copy;
    }

    function remove(id: string): void {
        const index = indexOf(id);
        if (index < 0) return;

        const next = rows.value.filter(row => row.id !== id);
        commit(next);

        if (collapsed.value.has(id)) {
            const remaining = new Set(collapsed.value);
            remaining.delete(id);
            collapsed.value = remaining;
        }

        if (selectedId.value === id) selectedId.value = next[Math.min(index, next.length - 1)]?.id ?? null;
    }

    function move(id: string, toIndex: number): void {
        const from = indexOf(id);
        if (from < 0) return;

        const next = [...rows.value];
        const to = Math.max(0, Math.min(toIndex, next.length - 1));
        if (to === from) return;

        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        commit(next);
    }

    function replace(id: string, patch: (row: SectionRow) => SectionRow): void {
        const index = indexOf(id);
        if (index < 0) return;

        const next = [...rows.value];
        next[index] = patch(next[index]);
        commit(next);
    }

    function toggle(id: string): void {
        replace(id, row => ({ ...row, enabled: !row.enabled }));
    }

    function update(id: string, field: string, value: unknown): void {
        replace(id, row => ({ ...row, data: { ...row.data, [field]: value } }));
    }

    function updateVariant(id: string, key: string, value: unknown): void {
        replace(id, (row) => {
            const variant = { ...row.variant };

            if (value === VARIANT_DEFAULT || value === undefined || value === null) delete variant[key];
            else variant[key] = value;

            return { ...row, variant };
        });
    }

    function load(list: unknown): void {
        const next = adopt(list);
        commit(next);
        selectedId.value = next[0]?.id ?? null;
    }

    function discard(): void {
        commit(clone(saved.value));
        collapsed.value = new Set();
    }

    function markSaved(list?: unknown): void {
        if (list !== undefined) {
            const next = adopt(list);
            rows.value = next;
            saved.value = clone(next);

            return;
        }

        saved.value = clone(rows.value);
    }

    function undo(): void {
        const previous = past.value[past.value.length - 1];
        if (previous === undefined) return;

        past.value = past.value.slice(0, -1);
        future.value = [...future.value, clone(rows.value)].slice(-HISTORY_LIMIT);
        rows.value = previous;
    }

    function redo(): void {
        const next = future.value[future.value.length - 1];
        if (next === undefined) return;

        future.value = future.value.slice(0, -1);
        past.value = [...past.value, clone(rows.value)].slice(-HISTORY_LIMIT);
        rows.value = next;
    }

    function isCollapsed(id: string): boolean {
        return collapsed.value.has(id);
    }

    function toggleCollapsed(id: string): void {
        const next = new Set(collapsed.value);

        if (next.has(id)) next.delete(id);
        else next.add(id);

        collapsed.value = next;
    }

    function expand(id: string): void {
        if (!collapsed.value.has(id)) return;

        const next = new Set(collapsed.value);
        next.delete(id);
        collapsed.value = next;
    }

    return {
        rows,
        selectedId,
        dirty,
        count,
        atLimit,
        canUndo,
        canRedo,
        schemaOf,
        labelOf,
        titleOf,
        countOf,
        canAdd,
        defaultsFor,
        add,
        duplicate,
        remove,
        move,
        toggle,
        update,
        updateVariant,
        load,
        discard,
        markSaved,
        undo,
        redo,
        isCollapsed,
        toggleCollapsed,
        expand,
        payload: () => clone(rows.value),
    };
}
