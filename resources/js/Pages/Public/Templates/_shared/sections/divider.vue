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

/** withDefaults substitutes only `undefined`, so an explicit null still lands here. */
const obj = (v: unknown): Record<string, unknown> =>
    v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : {};

const block = computed(() => obj(props.block));

const SIZES: Record<string, string> = {
    sm: 'py-6',
    md: 'py-12',
    lg: 'py-20',
};

/** Selects always carry a declared option; an unknown one still lands on a size. */
const spacing = computed(() => SIZES[String(block.value.size ?? '')] ?? SIZES.md);
const rule = computed(() => String(block.value.style ?? 'rule') !== 'space');
</script>

<template>
    <section :class="spacing" aria-hidden="true">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <hr v-if="rule" class="border-t border-border" />
        </div>
    </section>
</template>
