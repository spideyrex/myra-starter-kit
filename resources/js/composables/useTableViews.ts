import { computed, markRaw, ref, shallowRef, type Component, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { SavedView, TableViewPayload } from '@/types/table-views';

/** Reserved name for a future server-side column-preference record. */
export const RESERVED_COLUMNS_VIEW = '__columns';

/**
 * Stable JSON serialisation with sorted keys and empty values dropped, so two
 * payloads that describe the same table state compare equal regardless of the
 * order their keys were written in.
 */
export function signature(payload: TableViewPayload | null | undefined): string {
    return JSON.stringify(normalize(payload ?? {}) ?? {});
}

function normalize(value: any): any {
    if (Array.isArray(value)) {
        const out = value.map(normalize).filter(v => v !== undefined);
        return out.length > 0 ? out : undefined;
    }
    if (value !== null && typeof value === 'object') {
        const out: Record<string, any> = {};
        for (const key of Object.keys(value).sort()) {
            const normalized = normalize(value[key]);
            if (normalized !== undefined) out[key] = normalized;
        }
        return Object.keys(out).length > 0 ? out : undefined;
    }
    if (value === null || value === undefined || value === '') return undefined;
    return value;
}

/**
 * The exact param set a DataTable sends for a given state. Pure — extracted
 * verbatim from the old applyFilters() so the same params drive a live filter
 * change, a replayed saved view and a share link. `page` is never emitted.
 */
export function buildTableParams(state: TableViewPayload, opts: {
    queryPrefix?: string;
    routeParams?: Record<string, any>;
    /** `window.location.search`, so other tables' params survive a prefixed table. */
    currentSearch?: string;
} = {}): Record<string, any> {
    const qp = opts.queryPrefix || '';
    const params: Record<string, any> = {};

    if (qp && opts.currentSearch) {
        new URLSearchParams(opts.currentSearch).forEach((val, key) => {
            if (!key.startsWith(qp)) params[key] = val;
        });
    }

    Object.assign(params, opts.routeParams);

    params[qp + 'search'] = state.search || undefined;
    params[qp + 'sort'] = state.sort || undefined;
    params[qp + 'direction'] = state.direction && state.direction !== 'asc' ? state.direction : undefined;
    if (state.per_page) params[qp + 'per_page'] = state.per_page;

    for (const [key, val] of Object.entries(state.filters ?? {})) {
        if (val && val !== '') params[qp + key] = val;
    }

    for (const [key, val] of Object.entries(state.dateRanges ?? {})) {
        if (val?.from) params[qp + key + '_from'] = val.from;
        if (val?.to) params[qp + key + '_to'] = val.to;
    }

    for (const [key, val] of Object.entries(state.query ?? {})) {
        const group = val as { rules?: unknown[]; groups?: unknown[] } | null;
        if (group && ((group.rules?.length ?? 0) > 0 || (group.groups?.length ?? 0) > 0)) {
            params[qp + key] = JSON.stringify(group);
        }
    }

    return params;
}

/** A page-declared view. Saved views arrive from the server in the same shape. */
export class TableView {
    private _name: string;
    private _icon?: Component;
    private _default = false;
    private _permission?: string;
    private _payload: TableViewPayload = {};

    constructor(name: string) {
        this._name = name;
    }

    static make(name: string): TableView {
        return new TableView(name);
    }

    icon(icon: Component): this {
        this._icon = markRaw(icon);
        return this;
    }

    search(term: string): this {
        this._payload.search = term;
        return this;
    }

    filters(values: Record<string, string>): this {
        this._payload.filters = { ...(this._payload.filters ?? {}), ...values };
        return this;
    }

    dateRange(name: string, from: string, to: string): this {
        this._payload.dateRanges = { ...(this._payload.dateRanges ?? {}), [name]: { from, to } };
        return this;
    }

    /** Opaque blob — validated for shape and size only, never interpreted here. */
    query(name: string, group: unknown): this {
        this._payload.query = { ...(this._payload.query ?? {}), [name]: group };
        return this;
    }

    sort(field: string, direction: 'asc' | 'desc' = 'asc'): this {
        this._payload.sort = field;
        this._payload.direction = direction;
        return this;
    }

    perPage(n: number): this {
        this._payload.per_page = n;
        return this;
    }

    columns(visible: Record<string, boolean>): this {
        this._payload.columns = { ...(this._payload.columns ?? {}), ...visible };
        return this;
    }

    columnOrder(keys: string[]): this {
        this._payload.columnOrder = [...keys];
        return this;
    }

    /** Applied on first load when the URL carries no table params. */
    default(value = true): this {
        this._default = value;
        return this;
    }

    /** Client gate only — the index route keeps its own permission middleware. */
    permission(perm: string): this {
        this._permission = perm;
        return this;
    }

    toPayload(): TableViewPayload {
        return JSON.parse(JSON.stringify(this._payload));
    }

    toSavedView(): SavedView {
        return {
            id: null,
            slug: null,
            name: this._name,
            visibility: 'private',
            is_default: this._default,
            owned: false,
            payload: this.toPayload(),
            permission: this._permission,
            icon: this._icon,
        };
    }
}

export interface UseTableViewsOptions {
    tableKey: Ref<string>;
    savedViews: Ref<SavedView[]>;
    declared: Ref<TableView[]>;
    /** Current table state, captured by the host table. */
    current: () => TableViewPayload;
    /** Assigns a payload back onto the host table's refs and navigates. */
    apply: (payload: TableViewPayload) => void;
    /**
     * Builds a shareable absolute-path URL for a payload. `?view={slug}` deep
     * links are out of scope, so the share URL is the full param URL.
     */
    buildUrl?: (payload: TableViewPayload) => string;
    can?: (permission: string) => boolean;
    /** Props reloaded after a write so the list refreshes in place. */
    reloadProps?: string[];
}

export function useTableViews(opts: UseTableViewsOptions) {
    const active = shallowRef<SavedView | null>(null);
    const busy = ref(false);
    const reloadProps = opts.reloadProps ?? ['savedViews', 'flash'];

    const declaredViews = computed<SavedView[]>(() =>
        opts.declared.value
            .map(v => v.toSavedView())
            .filter(v => !v.permission || (opts.can ? opts.can(v.permission) : true)),
    );

    const all: ComputedRef<SavedView[]> = computed(() => [
        ...declaredViews.value,
        ...opts.savedViews.value,
    ].filter(v => v.name !== RESERVED_COLUMNS_VIEW));

    const mine = computed(() => all.value.filter(v => v.visibility !== 'team'));
    const team = computed(() => all.value.filter(v => v.visibility === 'team'));

    const defaultView = computed<SavedView | null>(() => all.value.find(v => v.is_default) ?? null);

    const isModified = computed(() => {
        if (!active.value) return false;
        return signature(opts.current()) !== signature(active.value.payload);
    });

    function applyView(view: SavedView): void {
        active.value = view;
        opts.apply(view.payload);
    }

    /**
     * Resolves on failure too — the server's validation errors surface through
     * Inertia's error bag, and a rejected promise from a template handler would
     * only become an unhandled rejection.
     */
    function write(run: (done: () => void) => void): Promise<void> {
        return new Promise<void>((resolve) => {
            busy.value = true;
            run(() => { busy.value = false; resolve(); });
        });
    }

    function saveAs(name: string, visibility: 'private' | 'team' = 'private'): Promise<void> {
        return write((done) => {
            router.post(route('admin.table-views.store'), {
                table_key: opts.tableKey.value,
                name,
                visibility,
                is_default: false,
                payload: opts.current(),
            }, {
                preserveState: true,
                preserveScroll: true,
                only: reloadProps,
                onSuccess: () => done(),
                onError: () => done(),
            });
        });
    }

    function updateActive(): Promise<void> {
        const view = active.value;
        if (!view || view.id === null) return Promise.resolve();

        return write((done) => {
            router.put(route('admin.table-views.update', view.id as number), {
                table_key: opts.tableKey.value,
                name: view.name,
                visibility: view.visibility,
                is_default: view.is_default,
                payload: opts.current(),
            }, {
                preserveState: true,
                preserveScroll: true,
                only: reloadProps,
                onSuccess: () => { active.value = { ...view, payload: opts.current() }; done(); },
                onError: () => done(),
            });
        });
    }

    function rename(view: SavedView, name: string): Promise<void> {
        if (view.id === null) return Promise.resolve();

        return write((done) => {
            router.put(route('admin.table-views.update', view.id as number), {
                table_key: opts.tableKey.value,
                name,
                visibility: view.visibility,
                is_default: view.is_default,
                payload: view.payload,
            }, {
                preserveState: true,
                preserveScroll: true,
                only: reloadProps,
                onSuccess: () => done(),
                onError: () => done(),
            });
        });
    }

    function remove(view: SavedView): Promise<void> {
        if (view.id === null) return Promise.resolve();

        return write((done) => {
            router.delete(route('admin.table-views.destroy', view.id as number), {
                preserveState: true,
                preserveScroll: true,
                only: reloadProps,
                onSuccess: () => { if (active.value?.id === view.id) active.value = null; done(); },
                onError: () => done(),
            });
        });
    }

    function makeDefault(view: SavedView): Promise<void> {
        if (view.id === null) return Promise.resolve();

        return write((done) => {
            router.post(route('admin.table-views.default', view.id as number), {}, {
                preserveState: true,
                preserveScroll: true,
                only: reloadProps,
                onSuccess: () => done(),
                onError: () => done(),
            });
        });
    }

    function shareUrl(view: SavedView): string {
        if (opts.buildUrl) return opts.buildUrl(view.payload);
        return typeof window !== 'undefined' ? window.location.pathname : '';
    }

    return {
        all, mine, team, active, busy, defaultView, isModified,
        applyView, saveAs, updateActive, rename, remove, makeDefault, shareUrl,
    };
}
