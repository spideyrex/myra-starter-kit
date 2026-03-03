<script setup lang="ts">
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps<{
    modelValue?: string;
    id?: string;
    placeholder?: string;
    autocomplete?: string;
    required?: boolean;
    autofocus?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const showPassword = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :type="showPassword ? 'text' : 'password'"
            :model-value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :autofocus="autofocus"
            class="pr-10"
            @update:model-value="emit('update:modelValue', $event as string)"
        />
        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="absolute right-0 top-0 h-full px-3 hover:bg-transparent"
            tabindex="-1"
            @click="showPassword = !showPassword"
        >
            <EyeOff v-if="showPassword" class="size-4 text-muted-foreground" />
            <Eye v-else class="size-4 text-muted-foreground" />
        </Button>
    </div>
</template>
