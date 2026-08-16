<script setup lang="ts">
import { computed } from 'vue';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{
        block?: Record<string, unknown>;
        settings?: HomepageData;
        variant?: Record<string, unknown>;
    }>(),
    { block: () => ({}), variant: () => ({}) },
);

const SIZES: Record<string, string> = {
    sm: 'py-6',
    md: 'py-12',
    lg: 'py-20',
};

/** Selects always carry a declared option; an unknown one still lands on a size. */
const spacing = computed(() => SIZES[String(props.block.size ?? '')] ?? SIZES.md);
const rule = computed(() => String(props.block.style ?? 'rule') !== 'space');
</script>

<template>
    <section :class="spacing" aria-hidden="true">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <hr v-if="rule" class="border-t border-border" />
        </div>
    </section>
</template>
