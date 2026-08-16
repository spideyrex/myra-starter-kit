<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    recipeKey: string;
    css: string | undefined;
    size: string | undefined;
    pattern?: boolean;
}>();

// A server-sanitised custom-property record, exactly like brandScopeStyle().
const style = computed(() => ({
    backgroundImage: props.css ?? 'none',
    backgroundSize: props.size ?? 'auto',
}));
</script>

<template>
    <span
        aria-hidden="true"
        class="block size-full rounded-sm bg-primary"
        :class="pattern ? 'text-primary-foreground/60' : ''"
    >
        <span class="block size-full rounded-sm" :class="pattern ? 'opacity-[0.4]' : ''" :style="style"></span>
    </span>
</template>
