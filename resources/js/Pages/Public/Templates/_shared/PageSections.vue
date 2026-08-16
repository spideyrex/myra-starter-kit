<script setup lang="ts">
import { computed } from 'vue';
import SectionBoundary from './SectionBoundary.vue';
import { hasSectionComponent, sectionComponents } from './sectionRegistry';
import type { HomepageData, PageSectionRow } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Server-normalised rows: disabled and unknown types are already gone. */
        blocks?: PageSectionRow[];
        settings: HomepageData;
        /** The template's per-section presentation props. */
        variants?: Record<string, Record<string, unknown>>;
        /** HomepageSettings::$template_options for this template. */
        overrides?: Record<string, Record<string, unknown>>;
    }>(),
    { blocks: () => [], variants: () => ({}), overrides: () => ({}) },
);

/** Anything the client cannot mount is skipped rather than thrown at Vue. */
const rows = computed(() =>
    (Array.isArray(props.blocks) ? props.blocks : []).filter(
        row => row !== null && typeof row === 'object' && hasSectionComponent((row as PageSectionRow).type),
    ),
);

function componentFor(row: PageSectionRow) {
    return sectionComponents[row.type];
}

function dataOf(row: PageSectionRow): Record<string, unknown> {
    const data = row.data as unknown;

    return data !== null && typeof data === 'object' && !Array.isArray(data)
        ? (data as Record<string, unknown>)
        : {};
}

/** Template variant, then the stored template option, then the block's own. */
function variantFor(row: PageSectionRow): Record<string, unknown> {
    return {
        ...(props.variants[row.type] ?? {}),
        ...(props.overrides[row.type] ?? {}),
        ...(row.variant && typeof row.variant === 'object' ? row.variant : {}),
    };
}

function keyFor(row: PageSectionRow, index: number): string {
    return typeof row.id === 'string' && row.id !== '' ? row.id : `auto-${index}`;
}
</script>

<template>
    <SectionBoundary v-for="(row, index) in rows" :key="keyFor(row, index)" :type="row.type">
        <component
            :is="componentFor(row)"
            :block="dataOf(row)"
            :settings="settings"
            :variant="variantFor(row)"
        />
    </SectionBoundary>
</template>
