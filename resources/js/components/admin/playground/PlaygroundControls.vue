<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PropControl } from '@/composables/usePlayground';

/**
 * Auto-rendered from the closed control union — every control is a shadcn
 * primitive with a real <label for>. Hand-rolling one here would be a defect.
 */
const props = defineProps<{
    controls: PropControl[];
    values: Record<string, any>;
    idPrefix: string;
}>();

const emit = defineEmits<{ update: [name: string, value: unknown] }>();

const { t } = useI18n();

function controlId(control: PropControl): string {
    return `${props.idPrefix}-${control.name}`;
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="control in controls" :key="control.name" class="space-y-1.5">
            <div v-if="control.kind === 'boolean'" class="flex items-center justify-between gap-3">
                <Label :for="controlId(control)">{{ t(control.labelKey) }}</Label>
                <Switch
                    :id="controlId(control)"
                    :model-value="Boolean(values[control.name])"
                    @update:model-value="emit('update', control.name, $event)"
                />
            </div>

            <template v-else-if="control.kind === 'select'">
                <Label :for="controlId(control)">{{ t(control.labelKey) }}</Label>
                <Select
                    :model-value="String(values[control.name] ?? '')"
                    @update:model-value="emit('update', control.name, $event)"
                >
                    <SelectTrigger :id="controlId(control)" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in control.options" :key="option.value" :value="option.value">
                            {{ t(option.labelKey) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </template>

            <template v-else-if="control.kind === 'number'">
                <Label :for="controlId(control)">{{ t(control.labelKey) }}</Label>
                <Input
                    :id="controlId(control)"
                    type="number"
                    :min="control.min"
                    :max="control.max"
                    :step="control.step ?? 1"
                    :model-value="values[control.name]"
                    @update:model-value="emit('update', control.name, Number($event))"
                />
            </template>

            <template v-else>
                <Label :for="controlId(control)">{{ t(control.labelKey) }}</Label>
                <Input
                    :id="controlId(control)"
                    type="text"
                    :maxlength="control.maxLength"
                    :model-value="String(values[control.name] ?? '')"
                    @update:model-value="emit('update', control.name, $event)"
                />
            </template>
        </div>
    </div>
</template>
