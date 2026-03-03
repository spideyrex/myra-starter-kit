<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import EmptyState from '@/components/EmptyState.vue';
import type { SimpleTableColumn } from '@/types/admin';
import type { Component } from 'vue';

withDefaults(defineProps<{
    columns: SimpleTableColumn[];
    items: any[];
    rowKey?: string;
    emptyTitle?: string;
    emptyDescription?: string;
    emptyIcon?: Component;
}>(), {
    rowKey: 'id',
    emptyTitle: 'No results found',
});
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
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.class"
                        >
                            {{ col.label }}
                        </TableHead>
                        <TableHead v-if="$slots.actions" class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="item in items" :key="item[rowKey]">
                        <TableCell
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.class"
                        >
                            <slot :name="`cell-${col.key}`" :value="item[col.key]" :row="item">
                                {{ item[col.key] }}
                            </slot>
                        </TableCell>
                        <TableCell v-if="$slots.actions" class="text-right">
                            <slot name="actions" :row="item" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
