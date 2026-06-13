<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from './FormField.vue';
import { BaseField, isLayoutItem, resolveLayout, type SchemaItem, type FieldSchema, type BuilderBlock } from '@/composables/useFormSchema';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { Plus, Trash2, ChevronUp, ChevronDown, GripVertical, Blocks } from 'lucide-vue-next';

/**
 * Builder — a repeatable list where each item is one of several "block" types.
 * Value shape: [{ type: blockName, data: { ...fields } }, ...].
 */
const props = withDefaults(defineProps<{
    modelValue?: any[];
    blocks: BuilderBlock[];
    minItems?: number;
    maxItems?: number;
    addLabel?: string;
    reorderable?: boolean;
    collapsible?: boolean;
    disabled?: boolean;
    errors?: string;
}>(), {
    modelValue: () => [],
    blocks: () => [],
    addLabel: 'Add Block',
    reorderable: true,
    collapsible: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: any[]] }>();

const collapsedRows = ref<Set<number>>(new Set());

const rows = computed<any[]>({
    get: () => props.modelValue || [],
    set: (val) => emit('update:modelValue', val),
});

function blockDef(type: string): BuilderBlock | undefined {
    return props.blocks.find(b => b.name === type);
}

function resolveSchema(schema: SchemaItem[]): FieldSchema[] {
    return schema.map((item) => {
        if (item instanceof BaseField) return item.toProps();
        if (isLayoutItem(item)) return resolveLayout(item as any) as unknown as FieldSchema;
        return item as FieldSchema;
    });
}

const canAdd = computed(() => !props.maxItems || rows.value.length < props.maxItems);
const canRemove = computed(() => !props.minItems || rows.value.length > props.minItems);

function addBlock(block: BuilderBlock) {
    if (!canAdd.value) return;
    const data: Record<string, any> = {};
    for (const field of resolveSchema(block.schema)) {
        if ('name' in field) data[(field as FieldSchema).name] = '';
    }
    rows.value = [...rows.value, { type: block.name, data }];
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
    updated[rowIndex] = {
        ...updated[rowIndex],
        data: { ...(updated[rowIndex].data || {}), [fieldName]: value },
    };
    rows.value = updated;
}

function toggleCollapsed(index: number) {
    if (collapsedRows.value.has(index)) collapsedRows.value.delete(index);
    else collapsedRows.value.add(index);
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(row, rowIndex) in rows" :key="rowIndex">
            <Card class="border-dashed">
                <div class="flex items-center gap-1.5 border-b bg-muted/30 px-2 py-1.5 sm:gap-2 sm:px-3 sm:py-2">
                    <GripVertical v-if="reorderable" class="hidden size-4 cursor-grab text-muted-foreground sm:block" />

                    <button
                        v-if="collapsible"
                        type="button"
                        class="flex items-center gap-1 text-sm font-medium"
                        @click="toggleCollapsed(rowIndex)"
                    >
                        <ChevronDown class="size-4 transition-transform" :class="{ '-rotate-90': collapsedRows.has(rowIndex) }" />
                        {{ blockDef(row.type)?.label || row.type }}
                    </button>
                    <span v-else class="flex items-center gap-2 text-sm font-medium">
                        <Badge variant="secondary" class="text-xs">{{ blockDef(row.type)?.label || row.type }}</Badge>
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
                    <template v-for="(field, fieldIndex) in resolveSchema(blockDef(row.type)?.schema || [])" :key="fieldIndex">
                        <FormField
                            v-if="'name' in field"
                            v-bind="(field as FieldSchema)"
                            :model-value="(row.data || {})[(field as FieldSchema).name]"
                            :disabled="disabled"
                            @update:model-value="updateField(rowIndex, (field as FieldSchema).name, $event)"
                        />
                    </template>
                </CardContent>
            </Card>
        </div>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button type="button" variant="outline" size="sm" class="w-full border-dashed" :disabled="!canAdd || disabled">
                    <Plus class="mr-2 size-4" />
                    {{ addLabel }}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-56">
                <DropdownMenuItem v-for="block in blocks" :key="block.name" @click="addBlock(block)">
                    <Blocks class="mr-2 size-4 text-muted-foreground" />
                    {{ block.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <p v-if="errors" class="text-sm text-destructive">{{ errors }}</p>
    </div>
</template>
