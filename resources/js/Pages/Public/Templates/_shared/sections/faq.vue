<script setup lang="ts">
import { computed } from 'vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
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

/** withDefaults substitutes only `undefined`, so an explicit null still lands here. */
const obj = (v: unknown): Record<string, unknown> =>
    v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : {};

const block = computed(() => obj(props.block));
const variant = computed(() => obj(props.variant));

const title = computed(() => text(block.value.title));
const subtitle = computed(() => text(block.value.subtitle));

const items = computed(() =>
    (Array.isArray(block.value.items) ? block.value.items : [])
        .filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
        .map((row, i) => ({ value: `q-${i}`, question: text(row.question), answer: text(row.answer) }))
        .filter(row => row.question !== '')
        .slice(0, 20),
);

const tinted = computed(() => variant.value.tinted === true);
</script>

<template>
    <section class="border-t py-16 sm:py-20" :class="tinted ? 'bg-muted/30' : ''">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div v-if="title || subtitle" class="text-center">
                <h2 v-if="title" class="text-3xl font-bold tracking-tight sm:text-4xl">{{ title }}</h2>
                <p v-if="subtitle" class="mt-4 text-lg text-muted-foreground">{{ subtitle }}</p>
            </div>
            <Accordion v-if="items.length" type="single" collapsible class="mt-10 w-full">
                <AccordionItem v-for="item in items" :key="item.value" :value="item.value">
                    <AccordionTrigger class="text-base">{{ item.question }}</AccordionTrigger>
                    <AccordionContent class="text-muted-foreground">{{ item.answer }}</AccordionContent>
                </AccordionItem>
            </Accordion>
        </div>
    </section>
</template>
