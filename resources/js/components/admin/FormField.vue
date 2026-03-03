<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import PasswordInput from '@/components/PasswordInput.vue';
import type { SelectOption } from '@/types/admin';
import type { FieldType } from '@/composables/useFormSchema';

const props = withDefaults(defineProps<{
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
    type?: FieldType;
    placeholder?: string;
    options?: SelectOption[];
    disabled?: boolean;
    rows?: number;
    // Date fields
    minDate?: string;
    maxDate?: string;
    // Radio fields
    inline?: boolean;
    // File fields
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
}>(), {
    type: 'text',
});

const model = defineModel<any>();

const fieldId = computed(() => `field-${props.name}`);

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (!target.files) return;
    model.value = props.multiple ? Array.from(target.files) : target.files[0];
}
</script>

<template>
    <!-- Hidden field — no visible element -->
    <input v-if="type === 'hidden'" type="hidden" :name="name" :value="model" />

    <div v-else :class="type === 'switch' || type === 'checkbox' ? 'flex items-center gap-3' : 'space-y-2'">
        <template v-if="type === 'switch'">
            <Switch :id="fieldId" v-model="model" :disabled="disabled" />
            <Label :for="fieldId">{{ label }}</Label>
        </template>
        <template v-else-if="type === 'checkbox'">
            <Checkbox :id="fieldId" :checked="model" :disabled="disabled" @update:checked="model = $event" />
            <Label :for="fieldId">{{ label }}</Label>
        </template>
        <template v-else>
            <Label :for="fieldId">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </Label>

            <slot>
                <Textarea
                    v-if="type === 'textarea' || type === 'richtext'"
                    :id="fieldId"
                    v-model="model"
                    :placeholder="placeholder"
                    :required="required"
                    :disabled="disabled"
                    :rows="rows || (type === 'richtext' ? 6 : undefined)"
                />
                <Select
                    v-else-if="type === 'select'"
                    v-model="model"
                    :disabled="disabled"
                >
                    <SelectTrigger :id="fieldId">
                        <SelectValue :placeholder="placeholder" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in options"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <PasswordInput
                    v-else-if="type === 'password'"
                    :id="fieldId"
                    v-model="model"
                    :placeholder="placeholder"
                    :required="required"
                />

                <!-- Radio group -->
                <div
                    v-else-if="type === 'radio'"
                    :class="inline ? 'flex flex-wrap gap-4' : 'space-y-2'"
                >
                    <label
                        v-for="opt in options"
                        :key="opt.value"
                        class="flex items-center gap-2 cursor-pointer"
                    >
                        <input
                            type="radio"
                            :name="name"
                            :value="opt.value"
                            :checked="model === opt.value"
                            :disabled="disabled"
                            class="size-4 border-border text-primary focus:ring-primary"
                            @change="model = opt.value"
                        />
                        <span class="text-sm">{{ opt.label }}</span>
                    </label>
                </div>

                <!-- Color picker -->
                <div v-else-if="type === 'color'" class="flex items-center gap-3">
                    <input
                        :id="fieldId"
                        type="color"
                        :value="model || '#000000'"
                        :disabled="disabled"
                        class="h-10 w-14 cursor-pointer rounded border border-border bg-background p-1"
                        @input="model = ($event.target as HTMLInputElement).value"
                    />
                    <Input
                        :model-value="model || ''"
                        :disabled="disabled"
                        placeholder="#000000"
                        class="max-w-[120px]"
                        @update:model-value="model = $event"
                    />
                </div>

                <!-- File upload -->
                <div v-else-if="type === 'file'">
                    <input
                        :id="fieldId"
                        type="file"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="disabled"
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                        @change="handleFileChange"
                    />
                    <p v-if="maxSize" class="mt-1 text-xs text-muted-foreground">Max size: {{ maxSize }}MB</p>
                </div>

                <!-- Date / DateTime / other native inputs -->
                <Input
                    v-else
                    :id="fieldId"
                    v-model="model"
                    :type="type"
                    :placeholder="placeholder"
                    :required="required"
                    :disabled="disabled"
                    :min="minDate"
                    :max="maxDate"
                />
            </slot>

            <p v-if="hint && !error" class="text-sm text-muted-foreground">{{ hint }}</p>
            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        </template>
    </div>
</template>
