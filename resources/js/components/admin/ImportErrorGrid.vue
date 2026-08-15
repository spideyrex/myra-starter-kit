<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertCircle } from 'lucide-vue-next';
import type { ImportColumnSchema, ImportRow } from '@/composables/useImportRunner';

const props = defineProps<{
    columns: ImportColumnSchema[];
    rows: ImportRow[];
    /** Show only rows that failed validation. */
    invalidOnly?: boolean;
}>();

const { t } = useI18n();

const visibleRows = computed(() =>
    props.invalidOnly ? props.rows.filter(r => Object.keys(r.errors).length > 0) : props.rows,
);

function cellId(line: number, name: string) {
    return `imp-${line}-${name}`;
}
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <table class="min-w-full text-sm">
            <caption class="sr-only">{{ t('transfer.import.a11y.errorGrid') }}</caption>
            <thead class="bg-muted/50">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left font-medium">#</th>
                    <th
                        v-for="col in columns"
                        :key="col.name"
                        scope="col"
                        class="px-3 py-2 text-left font-medium whitespace-nowrap"
                    >
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in visibleRows" :key="row.line" class="border-t align-top">
                    <th scope="row" class="px-3 py-2 text-left font-normal text-muted-foreground">
                        {{ row.line }}
                    </th>
                    <td
                        v-for="col in columns"
                        :key="col.name"
                        class="px-3 py-2"
                        :class="row.errors[col.name] ? 'bg-destructive/10' : ''"
                        :aria-invalid="row.errors[col.name] ? 'true' : undefined"
                        :aria-describedby="row.errors[col.name] ? cellId(row.line, col.name) : undefined"
                    >
                        <span class="block break-words">{{ row.values[col.name] ?? '' }}</span>
                        <!-- Colour is never the only signal: icon + text accompany the tint. -->
                        <span
                            v-if="row.errors[col.name]"
                            :id="cellId(row.line, col.name)"
                            class="mt-1 flex items-start gap-1 text-xs text-destructive"
                        >
                            <AlertCircle class="mt-0.5 size-3 shrink-0" aria-hidden="true" />
                            <span>{{ row.errors[col.name].join(' ') }}</span>
                        </span>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td :colspan="columns.length + 1" class="px-3 py-4 text-center text-muted-foreground">
                        {{ t('transfer.import.noErrors') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
