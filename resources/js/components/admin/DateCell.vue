<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    value: string | null;
    format?: 'date' | 'datetime' | 'relative';
}>(), {
    format: 'date',
});

const formatted = computed(() => {
    if (!props.value) return '-';

    const date = new Date(props.value);

    if (props.format === 'relative') {
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h ago`;
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 30) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    }

    if (props.format === 'datetime') {
        return date.toLocaleString();
    }

    return date.toLocaleDateString();
});
</script>

<template>
    <span class="text-sm text-muted-foreground">{{ formatted }}</span>
</template>
