<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

withDefaults(defineProps<{
    loading?: boolean;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
}>(), {
    loading: false,
    variant: 'default',
    size: 'default',
    type: 'submit',
    disabled: false,
});
</script>

<template>
    <Button
        :type="type"
        :variant="variant"
        :size="size"
        :disabled="disabled || loading"
    >
        <Spinner v-if="loading" class="size-4" />
        <template v-else>
            <slot />
        </template>
    </Button>
</template>
