<script setup lang="ts">
import { computed } from 'vue';
import { highlightRuns, type SearchMatch } from '@/composables/useGlobalSearch';

/**
 * Renders server-supplied offset ranges as alternating text runs. Never v-html —
 * a record titled `<img src=x onerror=alert(1)>` must render as literal text.
 */
const props = withDefaults(defineProps<{
    text: string;
    matches?: SearchMatch[];
    field?: 'title' | 'description';
}>(), {
    matches: () => [],
    field: 'title',
});

const runs = computed(() => highlightRuns(props.text, props.matches, props.field));
</script>

<template>
    <span>
        <template v-for="(run, i) in runs" :key="i">
            <mark v-if="run.mark" class="rounded-sm bg-primary/20 px-0.5 text-foreground">{{ run.text }}</mark>
            <template v-else>{{ run.text }}</template>
        </template>
    </span>
</template>
