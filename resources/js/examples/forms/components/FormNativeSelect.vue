<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { NativeSelect } from '@/components/ui/native-select';

// The registry's NativeSelect declares its emit in the shorthand form, which Vue
// normalises to `() => any` — no vee-validate field handler is assignable to it.
// Re-declaring the event with its payload gives the select the same typed
// :model-value / @update:model-value seam Switch and Checkbox already have.
const props = defineProps<{ modelValue?: string, class?: HTMLAttributes['class'] }>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const model = computed({
    get: () => props.modelValue ?? '',
    set: (value: string) => emit('update:modelValue', value),
});
</script>

<template>
    <NativeSelect v-model="model" :class="props.class">
        <slot />
    </NativeSelect>
</template>
