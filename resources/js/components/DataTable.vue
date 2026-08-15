<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { PaginatedData } from '@/types';
import type { ColumnSchema, FilterSchema, ActionSchema, ActionGroupSchema, BulkActionSchema, RowAction, RowActionsConfig, QueryGroup, QueryRule } from '@/types/admin';
import { BaseColumn } from '@/composables/useTableSchema';
import { BaseFilter } from '@/composables/useTableFilters';
import { Action, BulkAction, ActionGroup, ActionDivider, ActionSectionLabel, resolveActionItems } from '@/composables/useTableActions';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select as UiSelect, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/composables/usePermissions';
import { useConfirm } from '@/composables/useConfirm';
import { accumulate, summariseAcc, computeSummary, type SummaryAccumulator } from '@/composables/useSummaries';
import CellRenderer from '@/components/admin/TableCell.vue';
import RowActions from '@/components/admin/RowActions.vue';
import ActionModal from '@/components/ActionModal.vue';
import QueryBuilderGroup from '@/components/admin/QueryBuilderGroup.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Search, ChevronUp, ChevronDown, ChevronsUpDown, Check, X, Filter as FilterIcon, GripVertical, ChevronRight, Columns3, CalendarDays, Sparkles, RotateCcw } from 'lucide-vue-next';
// [B] saved views + column manager
import { onMounted } from 'vue';
import { useTableViews, buildTableParams, type TableView } from '@/composables/useTableViews';
import { useColumnManager } from '@/composables/useColumnManager';
import TableViewsMenu from '@/components/admin/TableViewsMenu.vue';
import ColumnManager from '@/components/admin/ColumnManager.vue';
import type { SavedView, TableViewPayload, ColumnManagerOptions } from '@/types/table-views';

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
    toggleable?: boolean; // [B]
}

type ColumnInput = Column | BaseColumn;
type FilterInput = BaseFilter | FilterSchema;
type ActionInput = Action | ActionGroup | ActionDivider | ActionSectionLabel;
type BulkActionInput = BulkAction;

const props = withDefaults(defineProps<{
    columns: ColumnInput[];
    data: PaginatedData<any>;
    searchable?: boolean;
    searchPlaceholder?: string;
    selectable?: boolean;
    loading?: boolean;
    filters?: Record<string, string>;
    routeName: string;
    routeParams?: Record<string, any>;
    tableFilters?: FilterInput[];
    actions?: ActionInput[];
    bulkActions?: BulkActionInput[];
    groupBy?: string;
    summaries?: Record<string, string | number>;
    reorderable?: boolean;
    reorderRoute?: string;
    queryPrefix?: string;
    stickyHeader?: boolean;
    inlineReloadProps?: string[];   // [A]
    tableKey?: string;                              // [B]
    savedViews?: SavedView[];                       // [B]
    views?: TableView[];                            // [B]
    columnManager?: boolean | ColumnManagerOptions; // [B]
    canShareViews?: boolean;                        // [B]
}>(), {
    searchable: true,
    searchPlaceholder: 'Search...',
    selectable: false,
    loading: false,
    reorderable: false,
    queryPrefix: '',
    stickyHeader: false,
    inlineReloadProps: () => ['flash'],       // [A]
    columnManager: true,                            // [B]
    canShareViews: false,                           // [B]
});

const { can } = usePermissions();
const { confirm } = useConfirm();
const { t } = useI18n();

function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '\u00AB')
        .replace(/&raquo;/g, '\u00BB')
        .replace(/&amp;/g, '&');
}

const emit = defineEmits<{
    bulkAction: [ids: number[]];
}>();

// --- Normalize columns ---
const resolvedColumns = computed(() => {
    return props.columns.map(col => {
        if (col instanceof BaseColumn) {
            return col.toSchema();
        }
        // Legacy Column interface — wrap as text schema
        return {
            key: (col as Column).key,
            label: (col as Column).label,
            type: 'text' as const,
            sortable: (col as Column).sortable ?? false,
            searchable: false,
            hidden: false,
            alignRight: false,
            class: (col as Column).class,
            toggleable: (col as Column).toggleable ?? true, // [B]
            grow: false,
        } satisfies ColumnSchema;
    });
});

// --- Column visibility + order (Column Manager) --- [B]
const columnManagerEnabled = computed(() => props.columnManager !== false);
const columnManagerOptions = computed<ColumnManagerOptions>(() =>
    typeof props.columnManager === 'object' ? props.columnManager : {},
);

// Two tables on one page can share a routeName; the query prefix disambiguates.
const columnStorageKey = computed(() =>
    `dt-columns-${props.routeName}${props.queryPrefix ? ':' + props.queryPrefix : ''}`,
);

const columnPrefs = useColumnManager({
    columns: resolvedColumns,
    storageKey: columnStorageKey,
    persist: props.columnManager === false
        ? 'none'
        : ((typeof props.columnManager === 'object' ? props.columnManager.persist : undefined) ?? 'local'),
});

const visibleColumns = columnPrefs.visibleColumns;
const columnsReorderable = computed(() => columnManagerOptions.value.reorderable !== false);

// --- Normalize filters ---
const resolvedFilters = computed<FilterSchema[]>(() => {
    if (!props.tableFilters) return [];
    return props.tableFilters.map(f => {
        if (f instanceof BaseFilter) return f.toSchema();
        return f as FilterSchema;
    });
});

// --- Normalize actions ---
type AnyActionSchema = ActionSchema | ActionGroupSchema;

const resolvedActions = computed<AnyActionSchema[]>(() => {
    if (!props.actions) return [];
    return props.actions.map(a => a.toSchema());
});

/**
 * A single top-level ActionGroup configures the trigger itself (label, icon,
 * badge, collapseAfter…) rather than nesting a submenu inside a dropdown. Its
 * own permission() gates the whole column.
 */
const resolvedRoot = computed(() => resolveActionItems(resolvedActions.value, can));

const rootGroup = computed<ActionGroupSchema | null>(() => resolvedRoot.value.rootGroup);

const actionItems = computed<AnyActionSchema[]>(() => resolvedRoot.value.items);

const resolvedBulkActions = computed<BulkActionSchema[]>(() => {
    if (!props.bulkActions) return [];
    return props.bulkActions.map(b => b instanceof BulkAction ? b.toSchema() : b);
});

const hasActions = computed(() => actionItems.value.length > 0);

// --- State ---
const qp = props.queryPrefix || '';
const search = ref(props.filters?.[qp + 'search'] || '');
const sortField = ref(props.filters?.[qp + 'sort'] || '');
const sortDirection = ref(props.filters?.[qp + 'direction'] || 'asc');
const selectedIds = ref<number[]>([]);
const filterValues = ref<Record<string, string>>({});

// Init filter values from URL
const dateRangeValues = ref<Record<string, { from: string; to: string }>>({});
const queryBuilderData = ref<Record<string, QueryGroup>>({});

if (props.filters) {
    for (const f of resolvedFilters.value) {
        if (f.type === 'date-range') {
            dateRangeValues.value[f.name] = {
                from: props.filters[qp + f.name + '_from'] || '',
                to: props.filters[qp + f.name + '_to'] || '',
            };
        } else if (f.type === 'query-builder') {
            try {
                const raw = props.filters[qp + f.name];
                if (raw) queryBuilderData.value[f.name] = JSON.parse(raw);
            } catch {}
            if (!queryBuilderData.value[f.name]) {
                queryBuilderData.value[f.name] = { conjunction: 'and', rules: [], groups: [] };
            }
        } else if (props.filters[qp + f.name]) {
            filterValues.value[f.name] = props.filters[qp + f.name];
        }
    }
}

const activeFilterCount = computed(() => {
    let count = 0;
    count += Object.values(filterValues.value).filter(v => v && v !== '').length;
    count += Object.values(dateRangeValues.value).filter(v => v.from || v.to).length;
    count += Object.values(queryBuilderData.value).filter(v => v.rules.length > 0 || v.groups.length > 0).length;
    return count;
});

const hasActiveFilters = computed(() => activeFilterCount.value > 0);

// Keep filter panel open when filters are active (persists across Inertia navigations)
const showFilters = ref(hasActiveFilters.value);

// Track whether query builder has unapplied local changes
const queryBuilderDirty = ref(false);

// Split filters into quick (inline) and advanced (collapsible) categories
const quickFilters = computed(() => resolvedFilters.value.filter(f => f.type === 'select' || f.type === 'ternary' || f.type === 'checkbox'));
const advancedFilters = computed(() => resolvedFilters.value.filter(f => f.type === 'date-range' || f.type === 'query-builder'));
const showAdvanced = ref(advancedFilters.value.some(f => {
    if (f.type === 'date-range') {
        const dr = dateRangeValues.value[f.name];
        return dr && (dr.from || dr.to);
    }
    if (f.type === 'query-builder') {
        const qb = queryBuilderData.value[f.name];
        return qb && (qb.rules.length > 0 || qb.groups.length > 0);
    }
    return false;
}));

// Get display label for an active filter value
function getFilterDisplayLabel(filter: FilterSchema, value: string): string {
    if (filter.type === 'select') {
        const opt = ((filter as any).options || []).find((o: any) => o.value === value);
        return opt ? opt.label : value;
    }
    if (filter.type === 'ternary') {
        return value === '1' ? ((filter as any).trueLabel || 'Yes') : ((filter as any).falseLabel || 'No');
    }
    if (filter.type === 'checkbox') return filter.label;
    return value;
}

function removeFilter(name: string) {
    const filter = resolvedFilters.value.find(f => f.name === name);
    if (!filter) return;
    if (filter.type === 'date-range') {
        dateRangeValues.value[name] = { from: '', to: '' };
    } else if (filter.type === 'query-builder') {
        queryBuilderData.value[name] = { conjunction: 'and', rules: [], groups: [] };
        queryBuilderDirty.value = false;
    } else {
        filterValues.value[name] = '';
    }
    applyFilters();
}

const allSelected = computed(() =>
    props.data.data.length > 0 && selectedIds.value.length === props.data.data.length,
);

const isSelectable = computed(() =>
    props.selectable || resolvedBulkActions.value.length > 0,
);

function toggleAll(checked: boolean | 'indeterminate') {
    selectedIds.value = checked === true ? props.data.data.map((item: any) => item.id) : [];
}

function toggleRow(id: number, checked: boolean | 'indeterminate') {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter(i => i !== id);
    }
}

function handleSort(key: string) {
    if (sortField.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = key;
        sortDirection.value = 'asc';
    }
    applyFilters();
}

let searchTimeout: ReturnType<typeof setTimeout>;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 300);
}

// --- Saved views: capture / build / apply --- [B]
const perPage = ref<number | undefined>(
    props.filters?.[qp + 'per_page'] ? Number(props.filters[qp + 'per_page']) : undefined,
);

/** Pure — the exact param set applyFilters() sends. `page` is never emitted. */
function buildParams(state?: TableViewPayload): Record<string, any> {
    return buildTableParams(state ?? captureState(), {
        queryPrefix: qp,
        routeParams: props.routeParams,
        currentSearch: typeof window !== 'undefined' ? window.location.search : '',
    });
}

function captureState(): TableViewPayload {
    const state: TableViewPayload = {
        search: search.value || undefined,
        sort: sortField.value || undefined,
        direction: sortField.value ? (sortDirection.value as 'asc' | 'desc') : undefined,
        per_page: perPage.value,
        filters: { ...filterValues.value },
        dateRanges: { ...dateRangeValues.value },
        query: { ...queryBuilderData.value },
    };
    if (columnManagerEnabled.value) Object.assign(state, columnPrefs.snapshot());
    return state;
}

/** Assigns a payload onto the exposed refs without navigating. */
function assignState(payload: TableViewPayload): void {
    search.value = payload.search ?? '';
    sortField.value = payload.sort ?? '';
    sortDirection.value = payload.direction ?? 'asc';
    perPage.value = payload.per_page;
    filterValues.value = { ...(payload.filters ?? {}) };
    dateRangeValues.value = { ...(payload.dateRanges ?? {}) } as Record<string, { from: string; to: string }>;
    queryBuilderData.value = { ...(payload.query ?? {}) } as Record<string, QueryGroup>;
    queryBuilderDirty.value = false;
    if (columnManagerEnabled.value) {
        columnPrefs.apply({ columns: payload.columns, columnOrder: payload.columnOrder });
    }
}

function applyView(payload: TableViewPayload): void {
    assignState(payload);
    applyFilters();
}

function applyFilters() {
    router.get(route(props.routeName, props.routeParams), buildParams(), {
        preserveState: true,
        preserveScroll: true,
    });
}

const declaredViews = computed<TableView[]>(() => props.views ?? []);
const serverViews = computed<SavedView[]>(() => props.savedViews ?? []);
const tableKeyRef = computed(() => props.tableKey ?? props.routeName);

const tableViews = useTableViews({
    tableKey: tableKeyRef,
    savedViews: serverViews,
    declared: declaredViews,
    current: captureState,
    apply: applyView,
    can,
    buildUrl: (payload) => {
        const params = buildParams(payload);
        const qs = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (value !== undefined && value !== null && value !== '') qs.append(key, String(value));
        }
        const query = qs.toString();
        const path = typeof window !== 'undefined' ? window.location.pathname : '';
        return query ? `${path}?${query}` : path;
    },
});

const showViewsMenu = computed(() => !!props.tableKey || declaredViews.value.length > 0);

function handleFilterChange(name: string, value: string) {
    filterValues.value[name] = value;
    applyFilters();
}

function clearFilters() {
    filterValues.value = {};
    for (const key of Object.keys(dateRangeValues.value)) {
        dateRangeValues.value[key] = { from: '', to: '' };
    }
    for (const key of Object.keys(queryBuilderData.value)) {
        queryBuilderData.value[key] = { conjunction: 'and', rules: [], groups: [] };
    }
    queryBuilderDirty.value = false;
    tableViews.active.value = null; // [B]
    applyFilters();
}

// A page-declared default view applies on first load only when the URL carries
// no table params, and replaces the history entry so Back still works. [B]
onMounted(() => {
    const fallback = tableViews.defaultView.value;
    if (!fallback) return;
    if (typeof window === 'undefined') return;

    for (const key of new URLSearchParams(window.location.search).keys()) {
        if (key.startsWith(qp)) return;
    }

    tableViews.active.value = fallback;
    assignState(fallback.payload);

    // A default view that resolves to the params already in the URL must not
    // navigate — that would remount and loop.
    const next = new URLSearchParams();
    for (const [key, value] of Object.entries(buildParams())) {
        if (value !== undefined && value !== null && value !== '') next.append(key, String(value));
    }
    if (next.toString() === new URLSearchParams(window.location.search).toString()) return;

    router.get(route(props.routeName, props.routeParams), buildParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

function goToPage(url: string | null) {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}

// --- Action helpers ---

/** One request path for every action — confirmation, route, payload, toast. */
async function runAction(a: ActionSchema, row: any) {
    if (a.requiresConfirmation) {
        const ok = await confirm({
            title: a.confirmTitle ?? 'Confirm',
            description: a.confirmDescription ?? '',
            confirmText: a.confirmTitle ?? 'Confirm',
            variant: a.destructive ? 'destructive' : 'default',
        });
        if (!ok) return;
    }

    const name = a.routeName ?? a.deleteRouteName;
    const method = a.routeName ? (a.method ?? 'post') : 'delete';

    if (name && !a.urlFn) {
        const params = a.routeParamsFn?.(row) ?? row.id;
        const payload = a.payloadFn?.(row) ?? {};
        const options = {
            preserveScroll: true,
            onSuccess: () => { if (a.successMessage) toast.success(a.successMessage); },
        };
        const url = route(name, params);
        if (method === 'delete') {
            // Inertia's delete() takes no data argument.
            router.delete(url, { ...options, data: payload });
        } else if (method === 'get') {
            router.get(url, payload, options);
        } else {
            router[method](url, payload, options);
        }
        return;
    }

    a.actionFn?.(row);
}

function toRowAction(a: AnyActionSchema, row: any): RowAction | null {
    if ((a as ActionGroupSchema).kind === 'group') {
        const g = a as ActionGroupSchema;
        if (g.hiddenFn?.(row)) return null;
        if (g.visibleFn && !g.visibleFn(row)) return null;
        const items = g.items.map(i => toRowAction(i, row)).filter((i): i is RowAction => i !== null);
        if (items.length === 0) return null;
        return {
            kind: 'group',
            label: g.label,
            icon: g.icon,
            color: g.color,
            permission: g.permission,
            badge: g.badgeFn?.(row) ?? null,
            tooltip: g.tooltip,
            items,
        };
    }

    const action = a as ActionSchema;
    if (action.kind === 'divider' || action.kind === 'section') {
        return { kind: action.kind, label: action.label };
    }
    if (action.hiddenFn?.(row)) return null;
    if (action.visibleFn && !action.visibleFn(row)) return null;

    return {
        kind: 'action',
        label: action.label,
        icon: action.icon,
        permission: action.permission,
        href: action.urlFn?.(row),
        external: action.external,
        color: action.color,
        tooltip: action.tooltip,
        badge: action.badgeFn?.(row) ?? null,
        onClick: action.modalConfig
            ? () => openModalAction(action, row)
            : () => runAction(action, row),
        destructive: action.destructive,
        separator: action.separator,
    };
}

function getRowActions(row: any): RowAction[] {
    return actionItems.value
        .map(a => toRowAction(a, row))
        .filter((a): a is RowAction => a !== null);
}

const rowActionsConfig = computed<RowActionsConfig | undefined>(() => {
    const g = rootGroup.value;
    if (!g) return undefined;
    return {
        label: g.label,
        icon: g.icon,
        color: g.color,
        size: g.size,
        asButton: g.asButton,
        buttonGroup: g.buttonGroup,
        tooltip: g.tooltip,
        placement: g.placement,
        width: g.width,
        maxHeight: g.maxHeight,
        collapseAfter: g.collapseAfter,
    };
});

function rowActionsConfigFor(row: any): RowActionsConfig | undefined {
    const g = rootGroup.value;
    const base = rowActionsConfig.value;
    if (!g || !base) return undefined;
    return { ...base, badge: g.badgeFn?.(row) ?? null };
}

async function handleBulkAction(bulk: BulkActionSchema) {
    if (selectedIds.value.length === 0) return;
    if (bulk.permission && !can(bulk.permission)) return;
    if (bulk.requiresConfirmation) {
        const confirmed = await confirm({
            title: bulk.confirmTitle || 'Confirm Action',
            description: bulk.confirmDescription || `This will affect ${selectedIds.value.length} selected item(s).`,
            variant: bulk.destructive ? 'destructive' : 'default',
            confirmText: bulk.confirmTitle || 'Confirm',
        });
        if (!confirmed) return;
    }
    bulk.actionFn?.(selectedIds.value);
    if (bulk.deselectAfter) {
        selectedIds.value = [];
    }
}

// --- Modal action support ---
const modalOpen = ref(false);
const modalConfig = ref<any>(null);

function openModalAction(action: ActionSchema, row: any) {
    if (!action.modalConfig) return;
    const mc = action.modalConfig;
    modalConfig.value = {
        title: action.label,
        schema: mc.schema,
        routeName: mc.routeName,
        routeParams: mc.routeParamsFn ? mc.routeParamsFn(row) : { id: row.id },
        method: mc.method || 'put',
        defaults: mc.defaultsFn ? mc.defaultsFn(row) : {},
        submitLabel: mc.submitLabel || action.label,
        payloadKey: mc.payloadKey,
        extraPayload: mc.extraPayloadFn ? mc.extraPayloadFn(row) : undefined,
    };
    modalOpen.value = true;
}

// --- Grouping ---
const groupedData = computed(() => {
    if (!props.groupBy) return null;
    const groups = new Map<string, any[]>();
    for (const row of props.data.data) {
        const key = String(row[props.groupBy] ?? 'Ungrouped');
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(row);
    }
    return groups;
});

const collapsedGroups = ref<Set<string>>(new Set());

function toggleGroup(key: string) {
    if (collapsedGroups.value.has(key)) {
        collapsedGroups.value.delete(key);
    } else {
        collapsedGroups.value.add(key);
    }
}

// --- Summaries ---
const hasSummaries = computed(() => {
    return visibleColumns.value.some(c => c.summarize);
});

// One traversal per summarised column, memoised on the current page of rows.
const summaryCache = computed<Map<string, SummaryAccumulator>>(() => {
    const m = new Map<string, SummaryAccumulator>();
    for (const col of visibleColumns.value) {
        if (!col.summarize) continue;
        m.set(col.key, accumulate(props.data.data, col.key, { keepValues: !!col.summaryFn }));
    }
    return m;
});

function serverSummary(col: ColumnSchema): string | number | undefined {
    return props.summaries?.[col.key];
}

/** Client-computed summaries only see the current page — say so in the footer. */
function isPageScoped(col: ColumnSchema): boolean {
    if (serverSummary(col) !== undefined) return false;
    if (import.meta.env.DEV && col.summaryConfig?.scope === 'all') {
        console.warn(`[DataTable] Column "${col.key}" declares summary scope 'all' but no server value arrived in the \`summaries\` prop.`);
    }
    return true;
}

function summaryValue(col: ColumnSchema): string | number {
    const fromServer = serverSummary(col);
    if (fromServer !== undefined) return fromServer;
    const acc = summaryCache.value.get(col.key);
    return acc ? summariseAcc(col, acc) : '';
}

/** Per-group footers always aggregate their own rows. */
function groupSummaryValue(col: ColumnSchema, rows: any[]): string | number {
    return computeSummary(col, rows);
}

// --- Reordering ---
const localRows = ref<any[]>([]);
const isDragging = ref(false);

if (props.reorderable) {
    localRows.value = [...props.data.data];
}

function handleReorder() {
    if (!props.reorderRoute) return;
    const ids = localRows.value.map(r => r.id);
    router.post(route(props.reorderRoute), { ids }, {
        preserveState: true,
        preserveScroll: true,
    });
}

// --- Inline editing ---
// With `updateRoute` the table owns the write: optimistic paint, rollback on error.
// Without it, the page's `onUpdate` callback is the escape hatch.
const inFlight = new Set<string>();
const inlineTimers = new Map<string, ReturnType<typeof setTimeout>>();

async function runInlineUpdate(col: ColumnSchema, row: any, value: any) {
    const c = col as any;
    if (c.permission && !can(c.permission)) return;
    if (c.disabledFn?.(row)) return;

    const message = c.confirmFn?.(row, value);
    if (message) {
        const ok = await confirm({ title: t('common.confirm'), description: message });
        if (!ok) return;
    }

    if (!c.updateRoute) {
        c.onUpdateFn?.(row, value);
        return;
    }

    const key = `${row.id}:${col.key}`;
    if (inFlight.has(key)) return;

    const previous = row[col.key];
    if (c.optimistic !== false) row[col.key] = value;
    inFlight.add(key);
    let succeeded = false;

    router.patch(
        route(c.updateRoute as string, row.id),
        { field: c.updateField ?? col.key, value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,                    // inline edits stop stacking history entries
            // `only: []` is a FULL visit — Inertia treats an empty array as "no
            // partial". Default to the one genuinely shared prop instead.
            only: props.inlineReloadProps,
            onSuccess: () => {
                succeeded = true;
                if (c.optimistic === false) row[col.key] = value;
            },
            // onError only fires for validation responses, so roll back in
            // onFinish instead — a 403 or 500 never reaches onError.
            onFinish: () => {
                inFlight.delete(key);
                if (!succeeded) {
                    if (c.optimistic !== false) row[col.key] = previous;
                    toast.error(t('table.updateFailed'));
                }
            },
        },
    );
}

function debouncedInlineUpdate(col: ColumnSchema, row: any, value: any) {
    const timerKey = `${row.id}-${col.key}`;
    const existing = inlineTimers.get(timerKey);
    if (existing) clearTimeout(existing);
    const ms = (col as any).debounceMs || 500;
    inlineTimers.set(timerKey, setTimeout(() => {
        runInlineUpdate(col, row, value);
        inlineTimers.delete(timerKey);
    }, ms));
}

function onInlineEvent(payload: { col: ColumnSchema; row: any; value: any }) {
    if (payload.col.type === 'textinput') {
        debouncedInlineUpdate(payload.col, payload.row, payload.value);
    } else {
        runInlineUpdate(payload.col, payload.row, payload.value);
    }
}

onBeforeUnmount(() => {
    inlineTimers.forEach(clearTimeout);
    inlineTimers.clear();
});

defineExpose({ selectedIds });
</script>

<template>
    <div class="space-y-4">
        <!-- Toolbar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <div v-if="searchable" class="relative w-full sm:max-w-sm sm:flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="search"
                    :placeholder="searchPlaceholder"
                    class="pl-10"
                    @input="handleSearch"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Table filters toggle -->
                <Button
                    v-if="resolvedFilters.length > 0"
                    variant="outline"
                    size="sm"
                    :class="{ 'border-primary text-primary': hasActiveFilters }"
                    @click="showFilters = !showFilters"
                >
                    <FilterIcon class="mr-2 size-4" />
                    <span class="hidden sm:inline">{{ t('table.filters') }}</span>
                    <Badge v-if="hasActiveFilters" variant="secondary" class="ml-1.5 h-5 min-w-5 px-1 text-xs">
                        {{ activeFilterCount }}
                    </Badge>
                </Button>

                <!-- Saved views [B] -->
                <TableViewsMenu
                    v-if="showViewsMenu"
                    :views="tableViews.all.value"
                    :active="tableViews.active.value"
                    :is-modified="tableViews.isModified.value"
                    :busy="tableViews.busy.value"
                    :can-share-with-team="canShareViews"
                    :share-url="tableViews.shareUrl"
                    @apply="tableViews.applyView"
                    @save-as="tableViews.saveAs"
                    @update-active="tableViews.updateActive"
                    @rename="tableViews.rename"
                    @remove="tableViews.remove"
                    @make-default="tableViews.makeDefault"
                />

                <!-- Column manager [B] -->
                <ColumnManager
                    v-if="columnManagerEnabled && columnPrefs.entries.value.length > 0"
                    :entries="columnPrefs.entries.value"
                    :is-default="columnPrefs.isDefault.value"
                    :reorderable="columnsReorderable"
                    :id-prefix="`dt-col-${queryPrefix || 'main'}`"
                    @toggle="columnPrefs.toggle"
                    @move="columnPrefs.move"
                    @reorder="columnPrefs.reorder"
                    @reset="columnPrefs.reset"
                />

                <!-- Bulk actions -->
                <template v-if="selectedIds.length > 0 && resolvedBulkActions.length > 0">
                    <Button
                        v-for="bulk in resolvedBulkActions"
                        :key="bulk.label"
                        :variant="bulk.destructive ? 'destructive' : 'outline'"
                        size="sm"
                        @click="handleBulkAction(bulk)"
                    >
                        <component :is="bulk.icon" v-if="bulk.icon" class="mr-2 size-4" />
                        {{ bulk.label }} ({{ selectedIds.length }})
                    </Button>
                </template>

                <slot name="toolbar" :selected-ids="selectedIds" />
            </div>
        </div>

        <!-- Filter Panel -->
        <div v-if="showFilters && resolvedFilters.length > 0" class="rounded-lg border bg-card shadow-sm">
            <!-- Active filter tags -->
            <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-1.5 border-b px-4 py-2.5">
                <span class="mr-1 text-xs font-medium text-muted-foreground">{{ t('table.active') }}</span>
                <template v-for="filter in resolvedFilters" :key="`tag-${filter.name}`">
                    <!-- Select / ternary tags -->
                    <Badge
                        v-if="(filter.type === 'select' || filter.type === 'ternary') && filterValues[filter.name] && filterValues[filter.name] !== ''"
                        variant="secondary"
                        class="gap-1 pr-1 text-xs font-normal"
                    >
                        {{ filter.label }}: {{ getFilterDisplayLabel(filter, filterValues[filter.name]) }}
                        <button class="ml-0.5 rounded-sm p-0.5 hover:bg-muted" @click="removeFilter(filter.name)">
                            <X class="size-3" />
                        </button>
                    </Badge>
                    <!-- Checkbox tag -->
                    <Badge
                        v-if="filter.type === 'checkbox' && filterValues[filter.name] === '1'"
                        variant="secondary"
                        class="gap-1 pr-1 text-xs font-normal"
                    >
                        {{ filter.label }}
                        <button class="ml-0.5 rounded-sm p-0.5 hover:bg-muted" @click="removeFilter(filter.name)">
                            <X class="size-3" />
                        </button>
                    </Badge>
                    <!-- Date range tag -->
                    <Badge
                        v-if="filter.type === 'date-range' && (dateRangeValues[filter.name]?.from || dateRangeValues[filter.name]?.to)"
                        variant="secondary"
                        class="gap-1 pr-1 text-xs font-normal"
                    >
                        {{ filter.label }}: {{ dateRangeValues[filter.name]?.from || '...' }} — {{ dateRangeValues[filter.name]?.to || '...' }}
                        <button class="ml-0.5 rounded-sm p-0.5 hover:bg-muted" @click="removeFilter(filter.name)">
                            <X class="size-3" />
                        </button>
                    </Badge>
                    <!-- Query builder tag -->
                    <Badge
                        v-if="filter.type === 'query-builder' && (queryBuilderData[filter.name]?.rules.length > 0 || queryBuilderData[filter.name]?.groups.length > 0)"
                        variant="secondary"
                        class="gap-1 pr-1 text-xs font-normal"
                    >
                        {{ filter.label }} ({{ queryBuilderData[filter.name]?.rules.length || 0 }} rules)
                        <button class="ml-0.5 rounded-sm p-0.5 hover:bg-muted" @click="removeFilter(filter.name)">
                            <X class="size-3" />
                        </button>
                    </Badge>
                </template>
                <button class="ml-1 inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground transition-colors" @click="clearFilters">
                    <RotateCcw class="size-3" />
                    Reset all
                </button>
            </div>

            <!-- Quick filters (select, ternary, checkbox) -->
            <div v-if="quickFilters.length > 0" class="px-4 py-3">
                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    <template v-for="filter in quickFilters" :key="filter.name">
                        <!-- Select filter -->
                        <div v-if="filter.type === 'select'" class="space-y-1.5">
                            <label class="text-xs font-medium text-muted-foreground">{{ filter.label }}</label>
                            <UiSelect
                                :model-value="filterValues[filter.name] || ''"
                                @update:model-value="(v: any) => handleFilterChange(filter.name, String(v ?? ''))"
                            >
                                <SelectTrigger class="h-8 w-[170px]">
                                    <SelectValue :placeholder="(filter as any).placeholder || 'All'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All</SelectItem>
                                    <SelectItem
                                        v-for="opt in (filter as any).options || []"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </UiSelect>
                        </div>

                        <!-- Ternary filter -->
                        <div v-else-if="filter.type === 'ternary'" class="space-y-1.5">
                            <label class="text-xs font-medium text-muted-foreground">{{ filter.label }}</label>
                            <UiSelect
                                :model-value="filterValues[filter.name] || ''"
                                @update:model-value="(v: any) => handleFilterChange(filter.name, String(v ?? ''))"
                            >
                                <SelectTrigger class="h-8 w-[140px]">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All</SelectItem>
                                    <SelectItem value="1">{{ (filter as any).trueLabel || 'Yes' }}</SelectItem>
                                    <SelectItem value="0">{{ (filter as any).falseLabel || 'No' }}</SelectItem>
                                </SelectContent>
                            </UiSelect>
                        </div>

                        <!-- Checkbox filter -->
                        <div v-else-if="filter.type === 'checkbox'" class="flex items-center gap-2 pb-0.5">
                            <Checkbox
                                :id="`filter-${filter.name}`"
                                :checked="filterValues[filter.name] === '1'"
                                @update:checked="(v: boolean | 'indeterminate') => handleFilterChange(filter.name, v === true ? '1' : '')"
                            />
                            <label :for="`filter-${filter.name}`" class="text-sm leading-none cursor-pointer select-none">{{ filter.label }}</label>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Advanced filters (date range, query builder) -->
            <template v-if="advancedFilters.length > 0">
                <Separator v-if="quickFilters.length > 0" />
                <div class="px-4 py-3 space-y-4">
                    <!-- Toggle to show/hide advanced section -->
                    <button
                        v-if="quickFilters.length > 0"
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <Sparkles class="size-3.5" />
                        Advanced Filters
                        <ChevronRight class="size-3.5 transition-transform duration-200" :class="{ 'rotate-90': showAdvanced }" />
                    </button>

                    <div v-if="showAdvanced || quickFilters.length === 0" class="space-y-4">
                        <template v-for="filter in advancedFilters" :key="filter.name">
                            <!-- Date range filter -->
                            <div v-if="filter.type === 'date-range'" class="space-y-2">
                                <div class="flex items-center gap-1.5">
                                    <CalendarDays class="size-3.5 text-muted-foreground" />
                                    <label class="text-xs font-medium text-foreground">{{ filter.label }}</label>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted-foreground">From</label>
                                        <Input
                                            type="date"
                                            class="h-8 w-[150px]"
                                            :min="(filter as any).minDate"
                                            :max="(filter as any).maxDate"
                                            :model-value="dateRangeValues[filter.name]?.from || ''"
                                            @update:model-value="(v: any) => { dateRangeValues[filter.name] = { ...dateRangeValues[filter.name], from: String(v ?? '') }; applyFilters(); }"
                                        />
                                    </div>
                                    <span class="text-xs text-muted-foreground mt-4">—</span>
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted-foreground">To</label>
                                        <Input
                                            type="date"
                                            class="h-8 w-[150px]"
                                            :min="(filter as any).minDate"
                                            :max="(filter as any).maxDate"
                                            :model-value="dateRangeValues[filter.name]?.to || ''"
                                            @update:model-value="(v: any) => { dateRangeValues[filter.name] = { ...dateRangeValues[filter.name], to: String(v ?? '') }; applyFilters(); }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Query builder filter -->
                            <div v-else-if="filter.type === 'query-builder'" class="space-y-2">
                                <div class="flex items-center gap-1.5">
                                    <Sparkles class="size-3.5 text-muted-foreground" />
                                    <label class="text-xs font-medium text-foreground">{{ filter.label }}</label>
                                </div>
                                <QueryBuilderGroup
                                    :group="queryBuilderData[filter.name] || { conjunction: 'and', rules: [], groups: [] }"
                                    :fields="(filter as any).fields || []"
                                    :depth="0"
                                    @update:group="(g: QueryGroup) => { queryBuilderData[filter.name] = g; queryBuilderDirty = true; }"
                                />
                                <div class="flex items-center gap-2">
                                    <Button size="sm" class="h-7 text-xs" :disabled="!queryBuilderDirty" @click="queryBuilderDirty = false; applyFilters();">
                                        <Check class="mr-1 size-3" />
                                        Apply Query
                                    </Button>
                                    <Button
                                        v-if="queryBuilderData[filter.name]?.rules.length > 0 || queryBuilderData[filter.name]?.groups.length > 0"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs text-muted-foreground"
                                        @click="queryBuilderData[filter.name] = { conjunction: 'and', rules: [], groups: [] }; queryBuilderDirty = false; applyFilters();"
                                    >
                                        <RotateCcw class="mr-1 size-3" />
                                        Clear Query
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Table -->
        <div :class="[stickyHeader ? 'overflow-auto max-h-[600px] rounded-md border' : 'overflow-x-auto rounded-md border']">
            <Table>
                <TableHeader :class="stickyHeader ? 'sticky top-0 z-10 bg-background' : ''">
                    <TableRow>
                        <TableHead v-if="reorderable" class="w-10" />
                        <TableHead v-if="isSelectable" class="w-12">
                            <Checkbox :model-value="allSelected" @update:model-value="toggleAll" />
                        </TableHead>
                        <TableHead
                            v-for="col in visibleColumns"
                            :key="col.key"
                            :class="[col.class, { 'text-right': col.alignRight }]"
                            class="select-none"
                            :style="col.sortable && !reorderable ? 'cursor: pointer' : ''"
                            @click="col.sortable && !reorderable ? handleSort(col.key) : undefined"
                        >
                            <div class="flex items-center gap-1" :class="[col.sortable && !reorderable ? 'hover:bg-muted/50 rounded-sm px-1 -mx-1 transition-colors' : '', col.alignRight ? 'justify-end' : '']">
                                {{ col.label }}
                                <template v-if="col.sortable && !reorderable">
                                    <ChevronUp v-if="sortField === col.key && sortDirection === 'asc'" class="size-4" />
                                    <ChevronDown v-else-if="sortField === col.key && sortDirection === 'desc'" class="size-4" />
                                    <ChevronsUpDown v-else class="size-4 text-muted-foreground" />
                                </template>
                            </div>
                        </TableHead>
                        <TableHead v-if="hasActions || $slots.actions" class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="loading">
                        <TableRow v-for="i in 5" :key="`skeleton-${i}`">
                            <TableCell v-if="reorderable"><Skeleton class="h-4 w-4" /></TableCell>
                            <TableCell v-if="isSelectable"><Skeleton class="h-4 w-4" /></TableCell>
                            <TableCell v-for="col in visibleColumns" :key="col.key" :class="col.class">
                                <Skeleton class="h-4 w-3/4" />
                            </TableCell>
                            <TableCell v-if="hasActions || $slots.actions" class="text-right"><Skeleton class="ml-auto h-4 w-16" /></TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow v-if="data.data.length === 0">
                            <TableCell :colspan="visibleColumns.length + (reorderable ? 1 : 0) + (isSelectable ? 1 : 0) + (hasActions || $slots.actions ? 1 : 0)" class="text-center py-8 text-muted-foreground">
                                <slot name="empty">No records found.</slot>
                            </TableCell>
                        </TableRow>

                        <!-- Grouped rows -->
                        <template v-if="groupBy && groupedData">
                            <template v-for="[groupKey, groupRows] in groupedData" :key="groupKey">
                                <TableRow class="bg-muted/40 hover:bg-muted/60 cursor-pointer" @click="toggleGroup(groupKey)">
                                    <TableCell :colspan="visibleColumns.length + (reorderable ? 1 : 0) + (isSelectable ? 1 : 0) + (hasActions ? 1 : 0)">
                                        <div class="flex items-center gap-2 font-medium">
                                            <ChevronRight class="size-4 transition-transform" :class="{ 'rotate-90': !collapsedGroups.has(groupKey) }" />
                                            <span>{{ groupKey }}</span>
                                            <Badge variant="secondary" class="text-xs">{{ groupRows.length }}</Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <template v-if="!collapsedGroups.has(groupKey)">
                                    <template v-for="row in groupRows" :key="row.id">
                                        <TableRow class="transition-colors hover:bg-muted/50">
                                            <TableCell v-if="reorderable" class="w-10 cursor-grab">
                                                <GripVertical class="size-4 text-muted-foreground" />
                                            </TableCell>
                                            <TableCell v-if="isSelectable">
                                                <Checkbox :model-value="selectedIds.includes(row.id)" @update:model-value="(v: boolean | 'indeterminate') => toggleRow(row.id, v)" />
                                            </TableCell>
                                            <TableCell v-for="col in visibleColumns" :key="col.key" :class="[col.class, { 'text-right': col.alignRight }]">
                                                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                                    <CellRenderer :col="col" :row="row" @inline="onInlineEvent" />
                                                </slot>
                                            </TableCell>
                                            <TableCell v-if="hasActions || $slots.actions" class="text-right">
                                                <slot name="actions" :row="row">
                                                    <RowActions v-if="hasActions" :actions="getRowActions(row)" :config="rowActionsConfigFor(row)" />
                                                </slot>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <!-- Group summary row -->
                                    <TableRow v-if="hasSummaries" class="bg-muted/20 font-medium border-t">
                                        <TableCell v-if="reorderable" />
                                        <TableCell v-if="isSelectable" />
                                        <TableCell v-for="col in visibleColumns" :key="`group-sum-${col.key}`" :class="[col.class, { 'text-right': col.alignRight }]">
                                            <span v-if="col.summarize" class="text-xs">
                                                <span v-if="col.summaryConfig?.label" class="mr-1 font-normal text-muted-foreground">{{ col.summaryConfig.label }}:</span>
                                                {{ groupSummaryValue(col, groupRows) }}
                                            </span>
                                        </TableCell>
                                        <TableCell v-if="hasActions || $slots.actions" />
                                    </TableRow>
                                </template>
                            </template>
                        </template>

                        <!-- Non-grouped rows -->
                        <template v-else>
                            <template v-for="row in (reorderable ? localRows : data.data)" :key="row.id">
                                <TableRow class="transition-colors hover:bg-muted/50 even:bg-muted/20">
                                    <TableCell v-if="reorderable" class="w-10 cursor-grab drag-handle">
                                        <GripVertical class="size-4 text-muted-foreground" />
                                    </TableCell>
                                    <TableCell v-if="isSelectable">
                                        <Checkbox :model-value="selectedIds.includes(row.id)" @update:model-value="(v: boolean | 'indeterminate') => toggleRow(row.id, v)" />
                                    </TableCell>
                                    <TableCell v-for="col in visibleColumns" :key="col.key" :class="[col.class, { 'text-right': col.alignRight }]">
                                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                            <CellRenderer :col="col" :row="row" @inline="onInlineEvent" />
                                        </slot>
                                    </TableCell>
                                    <TableCell v-if="hasActions || $slots.actions" class="text-right">
                                        <slot name="actions" :row="row">
                                            <RowActions v-if="hasActions" :actions="getRowActions(row)" :config="rowActionsConfigFor(row)" />
                                        </slot>
                                    </TableCell>
                                </TableRow>
                                <slot name="expanded-row" :row="row" />
                            </template>
                        </template>

                        <!-- Summary footer row -->
                        <TableRow v-if="hasSummaries && !groupBy && data.data.length > 0" class="bg-muted/30 font-medium border-t-2">
                            <TableCell v-if="reorderable" />
                            <TableCell v-if="isSelectable" />
                            <TableCell v-for="col in visibleColumns" :key="`sum-${col.key}`" :class="[col.class, { 'text-right': col.alignRight }]">
                                <span v-if="col.summarize" class="inline-flex items-center gap-1 text-sm">
                                    <span v-if="col.summaryConfig?.label" class="font-normal text-muted-foreground">{{ col.summaryConfig.label }}:</span>
                                    {{ summaryValue(col) }}
                                    <Badge
                                        v-if="isPageScoped(col)"
                                        variant="outline"
                                        class="ml-1 px-1 py-0 text-[10px] font-normal"
                                        title="Computed from the current page only"
                                    >{{ t('table.scope.page') }}</Badge>
                                </span>
                            </TableCell>
                            <TableCell v-if="hasActions || $slots.actions" />
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div v-if="data.meta.last_page > 1" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
            <p class="text-xs text-muted-foreground sm:text-sm">
                Showing {{ data.meta.from }}-{{ data.meta.to }} of {{ data.meta.total }}
            </p>
            <div class="flex flex-wrap justify-center gap-1">
                <Button
                    v-for="link in data.meta.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-8 px-2 text-xs sm:px-3 sm:text-sm"
                    :disabled="!link.url || link.active"
                    @click="goToPage(link.url)"
                >{{ decodePaginationLabel(link.label) }}</Button>
            </div>
        </div>

        <!-- Action Modal -->
        <ActionModal v-model:open="modalOpen" :config="modalConfig" />
    </div>
</template>
