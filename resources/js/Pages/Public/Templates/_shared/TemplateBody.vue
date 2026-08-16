<script setup lang="ts">
import { computed } from 'vue';
import OrderedSections from './OrderedSections.vue';
import PageSections from './PageSections.vue';
import { hasSectionComponent } from './sectionRegistry';
import type { HomepageData, PageSectionRow } from '@/types';

const props = withDefaults(
    defineProps<{
        /** The authored page. Empty means the flat settings still drive the page. */
        blocks?: PageSectionRow[];
        settings: HomepageData;
        order?: string[];
        supports?: string[];
        variants?: Record<string, Record<string, unknown>>;
        overrides?: Record<string, Record<string, unknown>>;
    }>(),
    {
        blocks: () => [],
        order: () => [],
        supports: () => ['hero', 'features', 'testimonials', 'pricing', 'cta'],
        variants: () => ({}),
        overrides: () => ({}),
    },
);

/**
 * The single branch point. A block list that this client cannot render a
 * single section of falls back to the legacy path rather than leaving <main>
 * empty — the public homepage must never come back blank.
 */
const renderable = computed(() =>
    (Array.isArray(props.blocks) ? props.blocks : []).filter(
        row => row !== null && typeof row === 'object' && hasSectionComponent((row as PageSectionRow).type),
    ),
);
</script>

<template>
    <PageSections
        v-if="renderable.length > 0"
        :blocks="renderable"
        :settings="settings"
        :variants="variants"
        :overrides="overrides"
    />

    <OrderedSections
        v-else
        :settings="settings"
        :order="order"
        :supports="supports"
        :variants="variants"
        :overrides="overrides"
    />
</template>
