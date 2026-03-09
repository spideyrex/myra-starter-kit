<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from './FormField.vue';
import { BaseField, isLayoutItem, resolveLayout, type SchemaItem, type FieldSchema } from '@/composables/useFormSchema';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Plus, Trash2, ChevronUp, ChevronDown, GripVertical } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    modelValue?: any[];
    subSchema: SchemaItem[];
    minItems?: number;
    maxItems?: number;
    addLabel?: string;
    reorderable?: boolean;
    collapsible?: boolean;
    disabled?: boolean;
    errors?: string;
}>(), {
    modelValue: () => [],
    addLabel: 'Add Item',
    reorderable: true,
    collapsible: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: any[]];
}>();

const collapsedRows = ref<Set<number>>(new Set());

const rows = computed({
    get: () => props.modelValue || [],
    set: (val) => emit('update:modelValue', val),
});

const resolvedSchema = computed(() => {
    return props.subSchema.map(item => {
        if (item instanceof BaseField) {
            return item.toProps();
        }
        if (isLayoutItem(item)) {
            return resolveLayout(item as any);
        }
        return item as FieldSchema;
    });
});

const canAdd = computed(() => !props.maxItems || rows.value.length < props.maxItems);
const canRemove = computed(() => !props.minItems || rows.value.length > props.minItems);

function addRow() {
    if (!canAdd.value) return;
    const newRow: Record<string, any> = {};
    for (const field of resolvedSchema.value) {
        if ('name' in field) {
            newRow[(field as FieldSchema).name] = '';
        }
    }
    rows.value = [...rows.value, newRow];
}

function removeRow(index: number) {
    if (!canRemove.value) return;
    const updated = [...rows.value];
    updated.splice(index, 1);
    rows.value = updated;
    collapsedRows.value.delete(index);
}

function moveUp(index: number) {
    if (index <= 0) return;
    const updated = [...rows.value];
    [updated[index - 1], updated[index]] = [updated[index], updated[index - 1]];
    rows.value = updated;
}

function moveDown(index: number) {
    if (index >= rows.value.length - 1) return;
    const updated = [...rows.value];
    [updated[index], updated[index + 1]] = [updated[index + 1], updated[index]];
    rows.value = updated;
}

function updateField(rowIndex: number, fieldName: string, value: any) {
    const updated = [...rows.value];
    updated[rowIndex] = { ...updated[rowIndex], [fieldName]: value };
    rows.value = updated;
}

function toggleCollapsed(index: number) {
    if (collapsedRows.value.has(index)) {
        collapsedRows.value.delete(index);
    } else {
        collapsedRows.value.add(index);
    }
}

function getRowLabel(row: any, index: number): string {
    const firstField = resolvedSchema.value.find(f => 'name' in f) as FieldSchema | undefined;
    if (firstField && row[firstField.name]) {
        return String(row[firstField.name]);
    }
    return `Item ${index + 1}`;
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(row, rowIndex) in rows" :key="rowIndex">
            <Card class="border-dashed">
                <div class="flex items-center gap-1.5 border-b bg-muted/30 px-2 py-1.5 sm:gap-2 sm:px-3 sm:py-2">
                    <GripVertical v-if="reorderable" class="hidden size-4 text-muted-foreground cursor-grab sm:block" />

                    <button
                        v-if="collapsible"
                        type="button"
                        class="flex items-center gap-1 text-sm font-medium"
                        @click="toggleCollapsed(rowIndex)"
                    >
                        <ChevronDown
                            class="size-4 transition-transform"
                            :class="{ '-rotate-90': collapsedRows.has(rowIndex) }"
                        />
                        {{ getRowLabel(row, rowIndex) }}
                    </button>
                    <span v-else class="text-sm font-medium text-muted-foreground">
                        Item {{ rowIndex + 1 }}
                    </span>

                    <div class="ml-auto flex items-center gap-1">
                        <template v-if="reorderable">
                            <Button type="button" variant="ghost" size="sm" class="size-7 p-0" :disabled="rowIndex === 0 || disabled" @click="moveUp(rowIndex)">
                                <ChevronUp class="size-3.5" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-7 p-0" :disabled="rowIndex === rows.length - 1 || disabled" @click="moveDown(rowIndex)">
                                <ChevronDown class="size-3.5" />
                            </Button>
                        </template>
                        <Button type="button" variant="ghost" size="sm" class="size-7 p-0 text-destructive hover:text-destructive" :disabled="!canRemove || disabled" @click="removeRow(rowIndex)">
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </div>
                <CardContent v-show="!collapsible || !collapsedRows.has(rowIndex)" class="form-grid grid gap-4 p-3 sm:grid-cols-2">
                    <template v-for="(field, fieldIndex) in resolvedSchema" :key="fieldIndex">
                        <FormField
                            v-if="'name' in field"
                            v-bind="(field as FieldSchema)"
                            :model-value="row[(field as FieldSchema).name]"
                            :disabled="disabled"
                            @update:model-value="updateField(rowIndex, (field as FieldSchema).name, $event)"
                        />
                    </template>
                </CardContent>
            </Card>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-full border-dashed"
            :disabled="!canAdd || disabled"
            @click="addRow"
        >
            <Plus class="mr-2 size-4" />
            {{ addLabel }}
        </Button>

        <p v-if="errors" class="text-sm text-destructive">{{ errors }}</p>
    </div>
</template>
