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

const text = (v: unknown): string => (typeof v === 'string' ? v : '');

const title = computed(() => text(props.block.title));

const items = computed(() =>
    (Array.isArray(props.block.items) ? props.block.items : [])
        .filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
        .map(row => ({ value: text(row.value), label: text(row.label) }))
        .filter(row => row.value !== '' || row.label !== '')
        .slice(0, 4),
);

const tinted = computed(() => props.variant.tinted === true);

const columns = computed(() => {
    if (items.value.length >= 4) return 'sm:grid-cols-2 lg:grid-cols-4';
    if (items.value.length === 3) return 'sm:grid-cols-3';

    return 'sm:grid-cols-2';
});
</script>

<template>
    <section class="border-t py-16 sm:py-20" :class="tinted ? 'bg-muted/30' : ''">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 v-if="title" class="mx-auto max-w-2xl text-center text-3xl font-bold tracking-tight sm:text-4xl">
                {{ title }}
            </h2>
            <dl v-if="items.length" class="mt-12 grid grid-cols-1 gap-8" :class="columns">
                <div
                    v-for="(item, i) in items"
                    :key="i"
                    class="flex flex-col items-center gap-2 rounded-xl border bg-background p-6 text-center"
                >
                    <dt class="order-2 text-sm text-muted-foreground">{{ item.label }}</dt>
                    <dd class="order-1 text-4xl font-bold tracking-tight text-primary">{{ item.value }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
