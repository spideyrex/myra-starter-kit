<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowRight } from 'lucide-vue-next';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

const props = defineProps<{
    widget: WidgetSchema;
    pageProps: any;
}>();

const columns = computed(() => props.widget.columnsFn?.(props.pageProps) ?? []);
const rows = computed(() => props.widget.rowsFn?.(props.pageProps) ?? []);
const footerLink = computed(() => props.widget.footerLinkFn?.(props.pageProps) ?? null);
</script>

<template>
    <Card class="animate-fade-in-up">
        <CardHeader>
            <CardTitle>{{ widget.title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-for="col in columns" :key="col.key" :class="col.class">
                            {{ col.label }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(row, idx) in rows" :key="idx">
                        <TableCell v-for="col in columns" :key="col.key" :class="col.class">
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="rows.length === 0">
                        <TableCell :colspan="columns.length" class="text-center py-8 text-muted-foreground">
                            No data available.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div v-if="footerLink" class="mt-3 text-center">
                <Link :href="footerLink.href" class="text-sm text-primary hover:underline inline-flex items-center gap-1">
                    {{ footerLink.label }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
