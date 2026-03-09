<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ title }}</h1>
                <slot name="badge" />
            </div>
            <p v-if="description" class="text-sm text-muted-foreground sm:text-base">{{ description }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
