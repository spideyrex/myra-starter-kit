<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import EmptyState from '@/components/EmptyState.vue';
import CellRenderer from '@/components/admin/TableCell.vue';
import { BaseColumn } from '@/composables/useTableSchema';
import type { ColumnSchema, SimpleTableColumn } from '@/types/admin';
import type { Component } from 'vue';

type ColumnInput = SimpleTableColumn | BaseColumn;

const props = withDefaults(defineProps<{
    columns: ColumnInput[];
    items: any[];
    rowKey?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    emptyIcon?: Component;
}>(), {
    rowKey: 'id',
    emptyTitle: 'No results found',
});

const emit = defineEmits<{
    inline: [payload: { col: ColumnSchema; row: any; value: any }];
}>();

// Schema columns render through the shared cell renderer; plain
// { key, label, class } columns keep their legacy raw-value behaviour.
interface ResolvedEntry {
    key: string;
    schema: ColumnSchema | null;
    legacy: SimpleTableColumn | null;
}

const resolvedColumns = computed<ResolvedEntry[]>(() =>
    props.columns.map(col => (col instanceof BaseColumn
        ? { key: col.key, schema: col.toSchema(), legacy: null }
        : { key: (col as SimpleTableColumn).key, schema: null, legacy: col as SimpleTableColumn })),
);

function headerLabel(entry: ResolvedEntry): string {
    return entry.schema ? entry.schema.label : (entry.legacy?.label ?? '');
}

function cellClass(entry: ResolvedEntry) {
    return entry.schema
        ? [entry.schema.class, { 'text-right': entry.schema.alignRight }]
        : [entry.legacy?.class];
}
</script>

<template>
    <Card class="mt-6">
        <CardContent class="pt-6">
            <EmptyState
                v-if="items.length === 0"
                :title="emptyTitle"
                :description="emptyDescription"
                :icon="emptyIcon"
            >
                <template v-if="$slots.empty" #action>
                    <slot name="empty" />
                </template>
            </EmptyState>
            <div v-else class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="entry in resolvedColumns"
                            :key="entry.key"
                            :class="cellClass(entry)"
                        >
                            {{ headerLabel(entry) }}
                        </TableHead>
                        <TableHead v-if="$slots.actions" class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="item in items" :key="item[rowKey]">
                        <TableCell
                            v-for="entry in resolvedColumns"
                            :key="entry.key"
                            :class="cellClass(entry)"
                        >
                            <slot :name="`cell-${entry.key}`" :value="item[entry.key]" :row="item">
                                <CellRenderer
                                    v-if="entry.schema"
                                    :col="entry.schema"
                                    :row="item"
                                    @inline="(p) => emit('inline', p)"
                                />
                                <template v-else>{{ item[entry.key] }}</template>
                            </slot>
                        </TableCell>
                        <TableCell v-if="$slots.actions" class="text-right">
                            <slot name="actions" :row="item" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            </div>
        </CardContent>
    </Card>
</template>
