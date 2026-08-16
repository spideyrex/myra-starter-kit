import { computed, ref, toValue, watch, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    applyLayout, hiddenByLayout, toWidgetSchema,
    type ColSpan, type DashboardLayoutPayload, type LayoutEntry, type ReportBinding,
    type WidgetInput, type WidgetSchema,
} from '@/composables/useDashboardWidgets';
import type { CatalogueEntry } from '@/composables/useWidgetCatalogue';

/** What the client PUTs. A request, never a schema — the server re-derives both. */
export interface InstanceRequest {
    key: string;
    catalogue: string;
    binding: Record<string, unknown>;
    title?: string;
    chartType?: string;
    colSpan?: ColSpan;
    rowSpan?: number;
    order?: number;
}

export interface UseDashboardLayoutOptions {
    dashboard: string;
    declared: MaybeRefOrGetter<WidgetInput[]>;
    /** From the Inertia prop. `instances` here are ALWAYS server-resolved. */
    saved?: MaybeRefOrGetter<DashboardLayoutPayload | null | undefined>;
    catalogue?: MaybeRefOrGetter<CatalogueEntry[]>;
    editable?: MaybeRefOrGetter<boolean>;
    /** Already-bound translator. The composable never emits English of its own. */
    t?: (key: string, params?: Record<string, unknown>) => string;
    /** Injected for tests; defaults to Ziggy's global `route()`. */
    resolveRoute?: (name: string, params?: any) => string;
}

export interface UseDashboardLayout {
    widgets: ComputedRef<WidgetInput[]>;
    /** Catalogue-added tiles only, un-overlaid: pass them to their own grid. */
    instances: ComputedRef<WidgetSchema[]>;
    hidden: ComputedRef<WidgetSchema[]>;
    layout: ComputedRef<DashboardLayoutPayload | null>;
    editing: Ref<boolean>;
    dirty: ComputedRef<boolean>;
    saving: Ref<boolean>;
    customised: ComputedRef<boolean>;
    usedCatalogueKeys: ComputedRef<string[]>;
    move(key: string, delta: -1 | 1): string;
    moveTo(key: string, index: number): void;
    reorderWithin(keys: string[], movedKey?: string): void;
    resize(key: string, colSpan: ColSpan, rowSpan?: number): void;
    toggleHidden(key: string): void;
    rename(key: string, title: string | null): void;
    addInstance(catalogueKey: string, binding?: ReportBinding): string;
    removeInstance(key: string): void;
    /** Exactly what save() PUTs. Instances are the local REQUEST array, never schemas. */
    payload(): { version: 1; entries: LayoutEntry[]; instances: InstanceRequest[] };
    save(): Promise<void>;
    reset(): Promise<void>;
    undo(): void;
    cancel(): void;
    announcement: Ref<string>;
}

interface EditState {
    order: string[];
    overrides: Record<string, { colSpan?: ColSpan; rowSpan?: number; hidden?: boolean; title?: string | null }>;
    instances: InstanceRequest[];
}

const UNDO_DEPTH = 20;

function randomSuffix(): string {
    const bytes = new Uint8Array(4);

    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }

    return [...bytes].map(b => b.toString(36)).join('').slice(0, 6).padEnd(6, '0');
}

function clone(state: EditState): EditState {
    return JSON.parse(JSON.stringify(state)) as EditState;
}

export function useDashboardLayout(options: UseDashboardLayoutOptions): UseDashboardLayout {
    const t = options.t ?? ((key: string) => key);
    const resolveRoute = options.resolveRoute
        ?? ((name: string, params?: any) => (globalThis as any).route(name, params));

    const editing = ref(false);
    const saving = ref(false);
    const announcement = ref('');
    const history = ref<EditState[]>([]);

    const saved = computed<DashboardLayoutPayload | null>(() => toValue(options.saved ?? null) ?? null);
    const catalogue = computed<CatalogueEntry[]>(() => toValue(options.catalogue ?? []) ?? []);
    const declaredSchemas = computed<WidgetSchema[]>(() => (toValue(options.declared) ?? []).map(toWidgetSchema));

    /** Server-resolved instance schemas. Never read out of a local blob. */
    const serverInstances = computed<WidgetSchema[]>(() => saved.value?.instances ?? []);

    const state = ref<EditState>(fromSaved());
    const baseline = ref<string>(JSON.stringify(state.value));

    function fromSaved(): EditState {
        const layout = saved.value;
        const overrides: EditState['overrides'] = {};
        const order: string[] = [];

        for (const entry of layout?.entries ?? []) {
            if (!entry || typeof entry.key !== 'string') continue;
            order.push(entry.key);
            overrides[entry.key] = {
                colSpan: entry.colSpan,
                rowSpan: entry.rowSpan,
                hidden: entry.hidden === true,
            };
        }

        // The stored REQUEST array is not readable from the client (only the
        // resolved schemas are), so it is reconstructed from what the server
        // resolved: catalogue key + binding + presentation, all server-minted.
        const instances: InstanceRequest[] = (layout?.instances ?? []).map((schema: any, index: number) => ({
            key: schema.key,
            catalogue: schema.catalogue,
            binding: bindingOf(schema),
            title: schema.title ?? undefined,
            chartType: schema.chartType ?? undefined,
            colSpan: schema.colSpan,
            rowSpan: schema.rowSpan ?? undefined,
            order: index,
        }));

        return { order, overrides, instances };
    }

    function bindingOf(schema: any): Record<string, unknown> {
        const binding = { ...(schema?.binding ?? {}) } as Record<string, unknown>;
        delete binding.report;

        return binding;
    }

    watch(saved, () => {
        state.value = fromSaved();
        baseline.value = JSON.stringify(state.value);
        history.value = [];
    });

    /** Local preview for an instance the server has not resolved yet. */
    const pendingInstances = computed<WidgetSchema[]>(() => {
        const known = new Set(serverInstances.value.map(s => s.key));

        return state.value.instances
            .filter(instance => !known.has(instance.key))
            .map(instance => {
                const entry = catalogue.value.find(e => e.key === instance.catalogue);

                return {
                    key: instance.key,
                    type: entry?.type ?? 'stat',
                    colSpan: instance.colSpan ?? entry?.defaultColSpan ?? 1,
                    rowSpan: instance.rowSpan,
                    title: instance.title ?? (entry ? t(entry.titleKey) : instance.key),
                    chartType: (instance.chartType ?? entry?.chartTypes?.[0]) as any,
                    binding: { ...(entry?.defaults ?? {}), ...instance.binding } as any,
                    lazy: true,
                } as WidgetSchema;
            });
    });

    const merged = computed<WidgetSchema[]>(() => {
        const instanceKeys = new Set(state.value.instances.map(i => i.key));

        const base = [
            ...declaredSchemas.value,
            ...serverInstances.value.filter(s => instanceKeys.has(s.key)),
            ...pendingInstances.value,
        ];

        return base.map(schema => {
            const title = state.value.overrides[schema.key]?.title;

            if (title === undefined) return schema;

            return { ...schema, title: title === null ? schema.title : title };
        });
    });

    const dirty = computed(() => JSON.stringify(state.value) !== baseline.value);

    /**
     * Null until there is something to overlay. With no saved row and no local
     * edit, applyLayout() is handed null and returns its input by reference —
     * the v2.4.0 render, byte for byte.
     */
    const layout = computed<DashboardLayoutPayload | null>(() => {
        if (saved.value === null && ! dirty.value) return null;

        return { version: 1, entries: buildEntries(), instances: serverInstances.value };
    });

    function buildEntries(): LayoutEntry[] {
        const known = new Map<string, number>(merged.value.map((schema, index) => [schema.key, index] as [string, number]));
        const ordered = [...state.value.order.filter(key => known.has(key))];

        for (const schema of merged.value) {
            if (!ordered.includes(schema.key)) ordered.push(schema.key);
        }

        return ordered.map((key, index) => {
            const override = state.value.overrides[key] ?? {};
            const schema = merged.value[known.get(key) ?? -1];

            return {
                key,
                order: Math.min(index, 255),
                colSpan: override.colSpan ?? schema?.colSpan ?? 1,
                rowSpan: override.rowSpan ?? schema?.rowSpan ?? 1,
                hidden: override.hidden === true,
            };
        });
    }

    const widgets = computed<WidgetInput[]>(() => applyLayout(merged.value, layout.value));
    const instanceKeySet = computed(() => new Set(state.value.instances.map(i => i.key)));
    const instances = computed<WidgetSchema[]>(() => merged.value.filter(s => instanceKeySet.value.has(s.key)));
    const hidden = computed<WidgetSchema[]>(() => hiddenByLayout(merged.value, layout.value));

    const customised = computed(() => saved.value !== null);
    const usedCatalogueKeys = computed(() => state.value.instances.map(i => i.catalogue));

    function push(): void {
        history.value.push(clone(state.value));
        if (history.value.length > UNDO_DEPTH) history.value.shift();
    }

    /** Pure: never writes state, so reading a position cannot mark the layout dirty. */
    function currentOrder(): string[] {
        return buildEntries().map(e => e.key);
    }

    function indexOf(key: string): number {
        return currentOrder().indexOf(key);
    }

    function announce(key: string): string {
        const order = currentOrder();
        const index = order.indexOf(key);
        const schema = merged.value.find(s => s.key === key);
        const text = t('dashboardLayout.a11y.position', {
            label: schema?.title ?? key,
            index: index + 1,
            total: order.length,
        });

        announcement.value = text;

        return text;
    }

    function moveTo(key: string, index: number): void {
        const order = currentOrder();
        const from = order.indexOf(key);

        if (from === -1) return;

        const to = Math.max(0, Math.min(index, order.length - 1));
        if (to === from) return;

        push();
        const next = [...order];
        next.splice(from, 1);
        next.splice(to, 0, key);
        state.value.order = next;
        announce(key);
    }

    /**
     * Reorder from a grid that renders only PART of the dashboard.
     *
     * `keys` is that grid's own key sequence after the move. The global slots
     * those keys occupy are refilled in that sequence, so a positional index is
     * never carried across the component boundary and tiles owned by the other
     * grids (and hidden tiles) keep their place.
     */
    function reorderWithin(keys: string[], movedKey?: string): void {
        const order = currentOrder();
        const slots: number[] = [];

        order.forEach((key, index) => {
            if (keys.includes(key)) slots.push(index);
        });

        if (slots.length === 0 || slots.length !== keys.length) return;

        const next = [...order];
        slots.forEach((slot, i) => { next[slot] = keys[i]; });

        if (next.every((key, i) => key === order[i])) return;

        push();
        state.value.order = next;
        if (movedKey !== undefined) announce(movedKey);
    }

    function move(key: string, delta: -1 | 1): string {
        const from = indexOf(key);
        if (from === -1) return announcement.value;

        moveTo(key, from + delta);

        return announce(key);
    }

    function overrideFor(key: string) {
        if (!state.value.overrides[key]) state.value.overrides[key] = {};

        return state.value.overrides[key];
    }

    function resize(key: string, colSpan: ColSpan, rowSpan?: number): void {
        push();
        const override = overrideFor(key);
        override.colSpan = colSpan;
        if (rowSpan !== undefined) override.rowSpan = rowSpan;

        const instance = state.value.instances.find(i => i.key === key);
        if (instance) {
            instance.colSpan = colSpan;
            if (rowSpan !== undefined) instance.rowSpan = rowSpan;
        }
    }

    function toggleHidden(key: string): void {
        push();
        const override = overrideFor(key);
        override.hidden = override.hidden !== true;
        announcement.value = t(override.hidden ? 'dashboardLayout.hide' : 'dashboardLayout.restore');
    }

    function rename(key: string, title: string | null): void {
        push();
        overrideFor(key).title = title;

        const instance = state.value.instances.find(i => i.key === key);
        if (instance) instance.title = title === null ? undefined : title.slice(0, 60);
    }

    function addInstance(catalogueKey: string, binding?: ReportBinding): string {
        const entry = catalogue.value.find(e => e.key === catalogueKey);
        const key = `${catalogueKey}#${randomSuffix()}`;
        const before = currentOrder();

        push();
        state.value.instances.push({
            key,
            catalogue: catalogueKey,
            binding: { ...(entry?.defaults ?? {}), ...(binding ?? {}) } as Record<string, unknown>,
            chartType: (binding as any)?.chartType ?? entry?.chartTypes?.[0],
            colSpan: entry?.defaultColSpan,
            order: before.length,
        });
        state.value.order = [...before, key];
        announcement.value = t('dashboardLayout.addWidget');

        return key;
    }

    function removeInstance(key: string): void {
        push();
        state.value.instances = state.value.instances.filter(i => i.key !== key);
        state.value.order = state.value.order.filter(k => k !== key);
        delete state.value.overrides[key];
        announcement.value = t('dashboardLayout.remove');
    }

    function payload(): { version: 1; entries: LayoutEntry[]; instances: InstanceRequest[] } {
        const entries = buildEntries();
        const order = new Map<string, number>(entries.map((e, i) => [e.key, i] as [string, number]));

        return {
            version: 1,
            entries,
            instances: state.value.instances.map(instance => {
                const override = state.value.overrides[instance.key] ?? {};

                return {
                    key: instance.key,
                    catalogue: instance.catalogue,
                    binding: instance.binding ?? {},
                    ...(instance.title ? { title: instance.title.slice(0, 60) } : {}),
                    ...(instance.chartType ? { chartType: instance.chartType } : {}),
                    ...(override.colSpan ?? instance.colSpan ? { colSpan: (override.colSpan ?? instance.colSpan) as ColSpan } : {}),
                    ...(override.rowSpan ?? instance.rowSpan ? { rowSpan: (override.rowSpan ?? instance.rowSpan) as number } : {}),
                    order: Math.min(order.get(instance.key) ?? 0, 255),
                };
            }),
        };
    }

    function save(): Promise<void> {
        return new Promise(resolve => {
            saving.value = true;

            router.put(
                resolveRoute('admin.dashboard-layouts.update', options.dashboard),
                { dashboard_key: options.dashboard, payload: payload() } as any,
                {
                    preserveScroll: true,
                    preserveState: true,
                    // The rendered instances always come back through
                    // WidgetInstance::resolveAll(), never from local state.
                    only: ['dashboardLayout', 'flash', 'errors'],
                    onFinish: () => {
                        saving.value = false;
                        baseline.value = JSON.stringify(state.value);
                        history.value = [];
                        editing.value = false;
                        resolve();
                    },
                },
            );
        });
    }

    function reset(): Promise<void> {
        return new Promise(resolve => {
            saving.value = true;

            router.delete(resolveRoute('admin.dashboard-layouts.destroy', options.dashboard), {
                preserveScroll: true,
                preserveState: true,
                only: ['dashboardLayout', 'flash', 'errors'],
                onFinish: () => {
                    saving.value = false;
                    state.value = { order: [], overrides: {}, instances: [] };
                    baseline.value = JSON.stringify(state.value);
                    history.value = [];
                    editing.value = false;
                    resolve();
                },
            });
        });
    }

    function undo(): void {
        const previous = history.value.pop();
        if (!previous) return;

        state.value = previous;
        announcement.value = t('dashboardLayout.undo');
    }

    function cancel(): void {
        state.value = fromSaved();
        history.value = [];
        editing.value = false;
    }

    watch(() => toValue(options.editable ?? true) !== false, allowed => {
        if (!allowed) editing.value = false;
    });

    return {
        widgets, instances, hidden, layout, editing, dirty, saving, customised, usedCatalogueKeys,
        move, moveTo, reorderWithin, resize, toggleHidden, rename, addInstance, removeInstance,
        payload, save, reset, undo, cancel, announcement,
    };
}
