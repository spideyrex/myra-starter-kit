<script setup lang="ts">
import { computed } from 'vue';
import OrderedSections from './OrderedSections.vue';
import PageSections from './PageSections.vue';
import { hasSectionComponent } from './sectionRegistry';
import { ALL_SECTIONS_SUPPORTED, isSupportedSection } from './sectionSupport';
import type { HomepageData, PageSectionRow } from '@/types';

const props = withDefaults(
    defineProps<{
        /** The authored page. Empty means the flat settings still drive the page. */
        blocks?: PageSectionRow[];
        settings: HomepageData;
        order?: string[];
        /** Applies to BOTH branches: the legacy order and the legacy five block types. */
        supports?: string[];
        variants?: Record<string, Record<string, unknown>>;
        overrides?: Record<string, Record<string, unknown>>;
    }>(),
    {
        blocks: () => [],
        order: () => [],
        supports: () => [...ALL_SECTIONS_SUPPORTED],
        variants: () => ({}),
        overrides: () => ({}),
    },
);

/**
 * The single branch point. A block list that this client cannot render a
 * single section of falls back to the legacy path rather than leaving <main>
 * empty — the public homepage must never come back blank. The template
 * restriction is applied here too, so the branch decision is made on the rows
 * that will actually mount.
 */
const renderable = computed(() =>
    (Array.isArray(props.blocks) ? props.blocks : []).filter(row => {
        if (row === null || typeof row !== 'object') {
            return false;
        }

        const type = (row as PageSectionRow).type;

        return hasSectionComponent(type) && isSupportedSection(type, props.supports);
    }),
);
</script>

<template>
    <PageSections
        v-if="renderable.length > 0"
        :blocks="renderable"
        :settings="settings"
        :supports="supports"
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
