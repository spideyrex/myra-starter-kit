<script setup lang="ts">
import { InboxIcon } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
    icon?: any;
}>();
</script>

<template>
    <div class="animate-fade-in-up flex flex-col items-center justify-center py-12 text-center">
        <div class="rounded-full bg-muted p-4">
            <component :is="icon || InboxIcon" class="size-12 text-muted-foreground/50" />
        </div>
        <h3 class="mt-4 text-lg font-semibold">{{ title || 'No results found' }}</h3>
        <p v-if="description" class="mt-2 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
