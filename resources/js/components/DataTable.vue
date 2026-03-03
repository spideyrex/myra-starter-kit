<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import type { PaginatedData } from '@/types';
import type { ColumnSchema, FilterSchema, ActionSchema, BulkActionSchema, RowAction } from '@/types/admin';
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
import DateCell from '@/components/admin/DateCell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import RowActions from '@/components/admin/RowActions.vue';
import { Search, ChevronUp, ChevronDown, ChevronsUpDown, Check, X, Copy, Filter as FilterIcon } from 'lucide-vue-next';

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
}>(), {
    searchable: true,
    searchPlaceholder: 'Search...',
    selectable: false,
    loading: false,
});

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();

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

const visibleColumns = computed(() => resolvedColumns.value.filter(c => !c.hidden));

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
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || '');
const sortDirection = ref(props.filters?.direction || 'asc');
const selectedIds = ref<number[]>([]);
const filterValues = ref<Record<string, string>>({});
const showFilters = ref(false);

// Init filter values from URL
if (props.filters) {
    for (const f of resolvedFilters.value) {
        if (props.filters[f.name]) {
            filterValues.value[f.name] = props.filters[f.name];
        }
    }
}

const hasActiveFilters = computed(() =>
    Object.values(filterValues.value).some(v => v && v !== ''),
);

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
    const params: Record<string, any> = {
        ...props.routeParams,
        search: search.value || undefined,
        sort: sortField.value || undefined,
        direction: sortDirection.value !== 'asc' ? sortDirection.value : undefined,
    };

    // Add table filter values
    for (const [key, val] of Object.entries(filterValues.value)) {
        if (val && val !== '') {
            params[key] = val;
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
            onClick: a.deleteRouteName
                ? () => confirmDelete(a.deleteRouteName!, row.id)
                : a.actionFn
                    ? () => a.actionFn!(row)
                    : undefined,
            destructive: a.destructive,
            separator: a.separator,
        }));
}

function handleBulkAction(bulk: BulkActionSchema) {
    if (selectedIds.value.length === 0) return;
    if (bulk.permission && !can(bulk.permission)) return;
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

defineExpose({ selectedIds });
</script>

<template>
    <div class="space-y-4">
        <!-- Toolbar -->
        <div class="flex items-center justify-between gap-4">
            <div v-if="searchable" class="relative max-w-sm flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="search"
                    :placeholder="searchPlaceholder"
                    class="pl-10"
                    @input="handleSearch"
                />
            </div>
            <div class="flex items-center gap-2">
                <!-- Table filters toggle -->
                <Button
                    v-if="resolvedFilters.length > 0"
                    variant="outline"
                    size="sm"
                    :class="{ 'border-primary text-primary': hasActiveFilters }"
                    @click="showFilters = !showFilters"
                >
                    <FilterIcon class="mr-2 size-4" />
                    Filters
                    <Badge v-if="hasActiveFilters" variant="secondary" class="ml-1.5 h-5 min-w-5 px-1 text-xs">
                        {{ Object.values(filterValues).filter(v => v && v !== '').length }}
                    </Badge>
                </Button>

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

        <!-- Filter controls -->
        <div v-if="showFilters && resolvedFilters.length > 0" class="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-3">
            <template v-for="filter in resolvedFilters" :key="filter.name">
                <!-- Select filter -->
                <div v-if="filter.type === 'select'" class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">{{ filter.label }}</label>
                    <UiSelect
                        :model-value="filterValues[filter.name] || ''"
                        @update:model-value="(v: any) => handleFilterChange(filter.name, String(v ?? ''))"
                    >
                        <SelectTrigger class="h-8 w-[160px]">
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
                <div v-else-if="filter.type === 'ternary'" class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">{{ filter.label }}</label>
                    <UiSelect
                        :model-value="filterValues[filter.name] || ''"
                        @update:model-value="(v: any) => handleFilterChange(filter.name, String(v ?? ''))"
                    >
                        <SelectTrigger class="h-8 w-[120px]">
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
                <div v-else-if="filter.type === 'checkbox'" class="flex items-center gap-2 pb-1">
                    <Checkbox
                        :checked="filterValues[filter.name] === '1'"
                        @update:checked="(v: boolean | 'indeterminate') => handleFilterChange(filter.name, v === true ? '1' : '')"
                    />
                    <label class="text-sm">{{ filter.label }}</label>
                </div>
            </template>

            <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-8" @click="clearFilters">
                Clear filters
            </Button>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-if="isSelectable" class="w-12">
                            <Checkbox :model-value="allSelected" @update:model-value="toggleAll" />
                        </TableHead>
                        <TableHead
                            v-for="col in visibleColumns"
                            :key="col.key"
                            :class="[col.class, { 'text-right': col.alignRight }]"
                            class="select-none"
                            :style="col.sortable ? 'cursor: pointer' : ''"
                            @click="col.sortable ? handleSort(col.key) : undefined"
                        >
                            <div class="flex items-center gap-1" :class="[col.sortable ? 'hover:bg-muted/50 rounded-sm px-1 -mx-1 transition-colors' : '', col.alignRight ? 'justify-end' : '']">
                                {{ col.label }}
                                <template v-if="col.sortable">
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
                            <TableCell v-if="isSelectable"><Skeleton class="h-4 w-4" /></TableCell>
                            <TableCell v-for="col in visibleColumns" :key="col.key" :class="col.class">
                                <Skeleton class="h-4 w-3/4" />
                            </TableCell>
                            <TableCell v-if="hasActions || $slots.actions" class="text-right"><Skeleton class="ml-auto h-4 w-16" /></TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow v-if="data.data.length === 0">
                            <TableCell :colspan="visibleColumns.length + (isSelectable ? 1 : 0) + (hasActions || $slots.actions ? 1 : 0)" class="text-center py-8 text-muted-foreground">
                                <slot name="empty">No records found.</slot>
                            </TableCell>
                        </TableRow>
                        <template v-for="row in data.data" :key="row.id">
                            <TableRow class="transition-colors hover:bg-muted/50 even:bg-muted/20">
                                <TableCell v-if="isSelectable">
                                    <Checkbox
                                        :model-value="selectedIds.includes(row.id)"
                                        @update:model-value="(v: boolean | 'indeterminate') => toggleRow(row.id, v)"
                                    />
                                </TableCell>
                                <TableCell v-for="col in visibleColumns" :key="col.key" :class="[col.class, { 'text-right': col.alignRight }]">
                                    <!-- Slot override always wins -->
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
                                            <component
                                                :is="row[col.key] ? ((col as any).trueIcon || Check) : ((col as any).falseIcon || X)"
                                                class="size-4"
                                                :class="row[col.key] ? ((col as any).trueColor || 'text-success') : ((col as any).falseColor || 'text-muted-foreground')"
                                            />
                                        </template>

                                        <template v-else-if="col.type === 'image'">
                                            <img
                                                :src="row[col.key] || (col as any).defaultUrl || ''"
                                                :class="{ 'rounded-full': (col as any).circular }"
                                                :style="{ width: `${(col as any).imageSize || 40}px`, height: `${(col as any).imageSize || 40}px` }"
                                                class="object-cover"
                                                :alt="col.label"
                                            />
                                        </template>

                                        <template v-else-if="col.type === 'icon'">
                                            <component
                                                v-if="(col as any).iconFn"
                                                :is="(col as any).iconFn(row[col.key], row)"
                                                class="size-5"
                                                :class="(col as any).colorFn ? (col as any).colorFn(row[col.key], row) : ''"
                                            />
                                        </template>

                                        <template v-else-if="col.type === 'toggle'">
                                            <Switch
                                                :model-value="!!row[col.key]"
                                                @update:model-value="(v: boolean) => (col as any).onUpdateFn?.(row, v)"
                                            />
                                        </template>

                                        <!-- Default: text column -->
                                        <template v-else>
                                            <div class="flex items-center gap-1">
                                                <template v-if="(col as any).urlFn">
                                                    <Link :href="(col as any).urlFn(row)" class="text-primary hover:underline">
                                                        {{ formatTextValue(col, row[col.key], row) }}
                                                    </Link>
                                                </template>
                                                <template v-else>
                                                    <span :class="{ 'whitespace-nowrap': !(col as any).wrap }">{{ formatTextValue(col, row[col.key], row) }}</span>
                                                </template>
                                                <button
                                                    v-if="(col as any).copyable && row[col.key]"
                                                    class="text-muted-foreground hover:text-foreground"
                                                    @click.stop="copyToClipboard(String(row[col.key]))"
                                                >
                                                    <Copy class="size-3" />
                                                </button>
                                            </div>
                                            <p v-if="(col as any).descriptionFn" class="text-xs text-muted-foreground">
                                                {{ (col as any).descriptionFn(row) }}
                                            </p>
                                        </template>
                                    </slot>
                                </TableCell>

                                <!-- Actions column -->
                                <TableCell v-if="hasActions || $slots.actions" class="text-right">
                                    <slot name="actions" :row="row">
                                        <RowActions v-if="hasActions" :actions="getRowActions(row)" />
                                    </slot>
                                </TableCell>
                            </TableRow>
                            <slot name="expanded-row" :row="row" />
                        </template>
                    </template>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div v-if="data.meta.last_page > 1" class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                Showing {{ data.meta.from }} to {{ data.meta.to }} of {{ data.meta.total }} results
            </p>
            <div class="flex gap-1">
                <Button
                    v-for="link in data.meta.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url || link.active"
                    @click="goToPage(link.url)"
                >{{ decodePaginationLabel(link.label) }}</Button>
            </div>
        </div>
    </div>
</template>
