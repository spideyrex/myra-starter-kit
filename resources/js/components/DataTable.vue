<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import type { PaginatedData } from '@/types';
import type { ColumnSchema, FilterSchema, ActionSchema, BulkActionSchema, RowAction, QueryGroup, QueryRule } from '@/types/admin';
import { BaseColumn } from '@/composables/useTableSchema';
import { BaseFilter } from '@/composables/useTableFilters';
import { Action, BulkAction, ActionGroup } from '@/composables/useTableActions';
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
import { Switch } from '@/components/ui/switch';
import { Select as UiSelect, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/composables/usePermissions';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useConfirm } from '@/composables/useConfirm';
import DateCell from '@/components/admin/DateCell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import RowActions from '@/components/admin/RowActions.vue';
import ActionModal from '@/components/ActionModal.vue';
import QueryBuilderGroup from '@/components/admin/QueryBuilderGroup.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Search, ChevronUp, ChevronDown, ChevronsUpDown, Check, X, Copy, Filter as FilterIcon, GripVertical, ChevronRight, Columns3, CalendarDays, Sparkles, RotateCcw } from 'lucide-vue-next';

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}

type ColumnInput = Column | BaseColumn;
type FilterInput = BaseFilter | FilterSchema;
type ActionInput = Action | ActionGroup;
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
}>(), {
    searchable: true,
    searchPlaceholder: 'Search...',
    selectable: false,
    loading: false,
    reorderable: false,
    queryPrefix: '',
    stickyHeader: false,
});

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();
const { confirm } = useConfirm();

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
            toggleable: false,
            grow: false,
        } satisfies ColumnSchema;
    });
});

// --- Column visibility (Column Manager) ---
const columnStorageKey = computed(() => `dt-columns-${props.routeName}`);
const hasToggleableColumns = computed(() => resolvedColumns.value.some(c => c.toggleable));

const columnVisibility = ref<Record<string, boolean>>({});

// Init from localStorage
if (typeof window !== 'undefined') {
    try {
        const stored = localStorage.getItem(`dt-columns-${props.routeName}`);
        if (stored) columnVisibility.value = JSON.parse(stored);
    } catch {}
}

function toggleColumnVisibility(key: string, visible: boolean) {
    columnVisibility.value[key] = visible;
    try {
        localStorage.setItem(columnStorageKey.value, JSON.stringify(columnVisibility.value));
    } catch {}
}

const visibleColumns = computed(() => resolvedColumns.value.filter(c => {
    if (c.toggleable && columnVisibility.value[c.key] !== undefined) {
        return columnVisibility.value[c.key];
    }
    return !c.hidden;
}));

// --- Normalize filters ---
const resolvedFilters = computed<FilterSchema[]>(() => {
    if (!props.tableFilters) return [];
    return props.tableFilters.map(f => {
        if (f instanceof BaseFilter) return f.toSchema();
        return f as FilterSchema;
    });
});

// --- Normalize actions ---
const resolvedActions = computed<ActionSchema[]>(() => {
    if (!props.actions) return [];
    const result: ActionSchema[] = [];
    for (const a of props.actions) {
        if (a instanceof ActionGroup) {
            result.push(...a.toSchema());
        } else if (a instanceof Action) {
            result.push(a.toSchema());
        }
    }
    return result;
});

const resolvedBulkActions = computed<BulkActionSchema[]>(() => {
    if (!props.bulkActions) return [];
    return props.bulkActions.map(b => b instanceof BulkAction ? b.toSchema() : b);
});

const hasActions = computed(() => resolvedActions.value.length > 0);

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

function applyFilters() {
    const params: Record<string, any> = {};

    // When using a query prefix, preserve other DataTables' params from the URL
    if (qp) {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((val, key) => {
            if (!key.startsWith(qp)) {
                params[key] = val;
            }
        });
    }

    Object.assign(params, props.routeParams);
    params[qp + 'search'] = search.value || undefined;
    params[qp + 'sort'] = sortField.value || undefined;
    params[qp + 'direction'] = sortDirection.value !== 'asc' ? sortDirection.value : undefined;

    // Add table filter values
    for (const [key, val] of Object.entries(filterValues.value)) {
        if (val && val !== '') {
            params[qp + key] = val;
        }
    }

    // Add date range filter values
    for (const [key, val] of Object.entries(dateRangeValues.value)) {
        if (val.from) params[qp + key + '_from'] = val.from;
        if (val.to) params[qp + key + '_to'] = val.to;
    }

    // Add query builder filter values
    for (const [key, val] of Object.entries(queryBuilderData.value)) {
        if (val.rules.length > 0 || val.groups.length > 0) {
            params[qp + key] = JSON.stringify(val);
        }
    }

    router.get(route(props.routeName, props.routeParams), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

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
    applyFilters();
}

function goToPage(url: string | null) {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}

// --- Action helpers ---
function getRowActions(row: any): RowAction[] {
    return resolvedActions.value
        .filter(a => {
            if (a.hiddenFn?.(row)) return false;
            if (a.visibleFn && !a.visibleFn(row)) return false;
            return true;
        })
        .map(a => ({
            label: a.label,
            icon: a.icon,
            permission: a.permission,
            href: a.urlFn?.(row),
            onClick: a.modalConfig
                ? () => openModalAction(a, row)
                : a.deleteRouteName
                    ? () => confirmDelete(a.deleteRouteName!, row.id)
                    : a.actionFn
                        ? () => a.actionFn!(row)
                        : undefined,
            destructive: a.destructive,
            separator: a.separator,
        }));
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

// --- Cell rendering helpers ---
function formatTextValue(col: ColumnSchema, value: any, row: any): string {
    if (col.type !== 'text') return String(value ?? '');

    if (col.formatFn) return col.formatFn(value, row);

    let result = value ?? col.defaultValue ?? '';

    if (col.currency) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: col.currency }).format(num);
        }
    }

    if (col.decimals !== undefined) {
        const num = typeof result === 'number' ? result : parseFloat(result);
        if (!isNaN(num)) {
            result = num.toFixed(col.decimals);
        }
    }

    result = String(result);

    if (col.limit && result.length > col.limit) {
        result = result.slice(0, col.limit) + '...';
    }

    if (col.prefix) result = col.prefix + result;
    if (col.suffix) result = result + col.suffix;

    return result;
}

function getBadgeVariant(col: ColumnSchema, value: string): string {
    if (col.type !== 'badge') return 'secondary';
    return col.colors[value] ?? 'secondary';
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
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
        routeParams: { id: row.id },
        method: mc.method || 'put',
        defaults: mc.defaultsFn ? mc.defaultsFn(row) : {},
        submitLabel: mc.submitLabel || action.label,
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

function computeSummary(col: ColumnSchema, rows?: any[]): string | number {
    // Server-provided summaries take precedence
    if (props.summaries && props.summaries[col.key] !== undefined) {
        return props.summaries[col.key];
    }

    if (!col.summarize) return '';

    const data = rows || props.data.data;
    const values = data.map(r => r[col.key]).filter(v => v !== null && v !== undefined);

    if (col.summaryFn) return col.summaryFn(values);

    const nums = values.map(v => typeof v === 'number' ? v : parseFloat(v)).filter(n => !isNaN(n));

    switch (col.summarize) {
        case 'sum': {
            const total = nums.reduce((a, b) => a + b, 0);
            if ((col as any).currency) {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: (col as any).currency }).format(total);
            }
            return Math.round(total * 100) / 100;
        }
        case 'average': {
            if (nums.length === 0) return 0;
            const avg = nums.reduce((a, b) => a + b, 0) / nums.length;
            return Math.round(avg * 100) / 100;
        }
        case 'count':
            return values.length;
        case 'range': {
            if (nums.length === 0) return '—';
            return `${Math.min(...nums)} – ${Math.max(...nums)}`;
        }
        default:
            return '';
    }
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
function handleInlineUpdate(col: ColumnSchema, row: any, value: string) {
    (col as any).onUpdateFn?.(row, value);
}

const inlineTimers = new Map<string, ReturnType<typeof setTimeout>>();
function debouncedInlineUpdate(col: ColumnSchema, row: any, value: string) {
    const timerKey = `${row.id}-${col.key}`;
    const existing = inlineTimers.get(timerKey);
    if (existing) clearTimeout(existing);
    const ms = (col as any).debounceMs || 500;
    inlineTimers.set(timerKey, setTimeout(() => {
        handleInlineUpdate(col, row, value);
        inlineTimers.delete(timerKey);
    }, ms));
}

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
                    <span class="hidden sm:inline">Filters</span>
                    <Badge v-if="hasActiveFilters" variant="secondary" class="ml-1.5 h-5 min-w-5 px-1 text-xs">
                        {{ activeFilterCount }}
                    </Badge>
                </Button>

                <!-- Column manager -->
                <Popover v-if="hasToggleableColumns">
                    <PopoverTrigger as-child>
                        <Button variant="outline" size="sm">
                            <Columns3 class="mr-2 size-4" />
                            <span class="hidden sm:inline">Columns</span>
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-48 p-3" align="end">
                        <p class="mb-2 text-xs font-medium text-muted-foreground">Toggle columns</p>
                        <div class="space-y-2">
                            <label
                                v-for="col in resolvedColumns.filter(c => c.toggleable)"
                                :key="col.key"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :checked="visibleColumns.some(vc => vc.key === col.key)"
                                    @update:checked="(v: boolean | 'indeterminate') => toggleColumnVisibility(col.key, v === true)"
                                />
                                {{ col.label }}
                            </label>
                        </div>
                    </PopoverContent>
                </Popover>

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
                <span class="mr-1 text-xs font-medium text-muted-foreground">Active:</span>
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
                                                        <template v-if="col.type === 'badge'">
                                                        <StatusBadge v-if="!('colors' in col) || Object.keys((col as any).colors || {}).length === 0" :status="row[col.key]" />
                                                        <Badge v-else :variant="getBadgeVariant(col, row[col.key]) as any">{{ row[col.key] }}</Badge>
                                                    </template>
                                                    <template v-else-if="col.type === 'date'">
                                                        <DateCell :value="row[col.key]" :format="(col as any).dateFormat || 'date'" />
                                                    </template>
                                                    <template v-else-if="col.type === 'boolean'">
                                                        <component :is="row[col.key] ? ((col as any).trueIcon || Check) : ((col as any).falseIcon || X)" class="size-4" :class="row[col.key] ? ((col as any).trueColor || 'text-success') : ((col as any).falseColor || 'text-muted-foreground')" />
                                                    </template>
                                                    <template v-else>
                                                        <span>{{ formatTextValue(col, row[col.key], row) }}</span>
                                                    </template>
                                                </slot>
                                            </TableCell>
                                            <TableCell v-if="hasActions || $slots.actions" class="text-right">
                                                <slot name="actions" :row="row">
                                                    <RowActions v-if="hasActions" :actions="getRowActions(row)" />
                                                </slot>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <!-- Group summary row -->
                                    <TableRow v-if="hasSummaries" class="bg-muted/20 font-medium border-t">
                                        <TableCell v-if="reorderable" />
                                        <TableCell v-if="isSelectable" />
                                        <TableCell v-for="col in visibleColumns" :key="`group-sum-${col.key}`" :class="[col.class, { 'text-right': col.alignRight }]">
                                            <span v-if="col.summarize" class="text-xs">{{ computeSummary(col, groupRows) }}</span>
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
                                            <!-- Auto-render based on column type -->
                                            <template v-if="col.type === 'badge'">
                                                <StatusBadge v-if="!('colors' in col) || Object.keys((col as any).colors || {}).length === 0" :status="row[col.key]" />
                                                <Badge v-else :variant="getBadgeVariant(col, row[col.key]) as any">{{ row[col.key] }}</Badge>
                                            </template>
                                            <template v-else-if="col.type === 'date'">
                                                <DateCell :value="row[col.key]" :format="(col as any).dateFormat || 'date'" />
                                            </template>
                                            <template v-else-if="col.type === 'boolean'">
                                                <component :is="row[col.key] ? ((col as any).trueIcon || Check) : ((col as any).falseIcon || X)" class="size-4" :class="row[col.key] ? ((col as any).trueColor || 'text-success') : ((col as any).falseColor || 'text-muted-foreground')" />
                                            </template>
                                            <template v-else-if="col.type === 'image'">
                                                <img :src="row[col.key] || (col as any).defaultUrl || ''" :class="{ 'rounded-full': (col as any).circular }" :style="{ width: `${(col as any).imageSize || 40}px`, height: `${(col as any).imageSize || 40}px` }" class="object-cover" :alt="col.label" />
                                            </template>
                                            <template v-else-if="col.type === 'icon'">
                                                <component v-if="(col as any).iconFn" :is="(col as any).iconFn(row[col.key], row)" class="size-5" :class="(col as any).colorFn ? (col as any).colorFn(row[col.key], row) : ''" />
                                            </template>
                                            <template v-else-if="col.type === 'toggle'">
                                                <Switch :model-value="!!row[col.key]" @update:model-value="(v: boolean) => (col as any).onUpdateFn?.(row, v)" />
                                            </template>
                                            <template v-else-if="col.type === 'select'">
                                                <UiSelect :model-value="String(row[col.key] ?? '')" @update:model-value="(v: any) => handleInlineUpdate(col, row, String(v))">
                                                    <SelectTrigger class="h-8 w-[140px]">
                                                        <SelectValue :placeholder="(col as any).placeholder || 'Select...'" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="opt in ((col as any).options || [])" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                                    </SelectContent>
                                                </UiSelect>
                                            </template>
                                            <template v-else-if="col.type === 'textinput'">
                                                <Input :model-value="row[col.key] ?? ''" :placeholder="(col as any).placeholder || ''" class="h-8 w-[160px]" @update:model-value="(v: any) => debouncedInlineUpdate(col, row, String(v))" />
                                            </template>
                                            <template v-else>
                                                <div class="flex items-center gap-1">
                                                    <template v-if="(col as any).urlFn">
                                                        <Link :href="(col as any).urlFn(row)" class="text-primary hover:underline">{{ formatTextValue(col, row[col.key], row) }}</Link>
                                                    </template>
                                                    <template v-else>
                                                        <span :class="{ 'whitespace-nowrap': !(col as any).wrap }">{{ formatTextValue(col, row[col.key], row) }}</span>
                                                    </template>
                                                    <button v-if="(col as any).copyable && row[col.key]" class="text-muted-foreground hover:text-foreground" @click.stop="copyToClipboard(String(row[col.key]))">
                                                        <Copy class="size-3" />
                                                    </button>
                                                </div>
                                                <p v-if="(col as any).descriptionFn" class="text-xs text-muted-foreground">{{ (col as any).descriptionFn(row) }}</p>
                                            </template>
                                        </slot>
                                    </TableCell>
                                    <TableCell v-if="hasActions || $slots.actions" class="text-right">
                                        <slot name="actions" :row="row">
                                            <RowActions v-if="hasActions" :actions="getRowActions(row)" />
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
                                <span v-if="col.summarize" class="text-sm">{{ computeSummary(col) }}</span>
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
