<script setup lang="ts">
import { computed } from 'vue';
import SectionBoundary from './SectionBoundary.vue';
import { hasSectionComponent, sectionComponents } from './sectionRegistry';
import { ALL_SECTIONS_SUPPORTED, isSupportedSection } from './sectionSupport';
import type { HomepageData, PageSectionRow } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Server-normalised rows: disabled and unknown types are already gone. */
        blocks?: PageSectionRow[];
        settings: HomepageData;
        /** The legacy section keys this template renders. Non-legacy types always render. */
        supports?: string[];
        /** The template's per-section presentation props. */
        variants?: Record<string, Record<string, unknown>>;
        /** HomepageSettings::$template_options for this template. */
        overrides?: Record<string, Record<string, unknown>>;
    }>(),
    {
        blocks: () => [],
        supports: () => [...ALL_SECTIONS_SUPPORTED],
        variants: () => ({}),
        overrides: () => ({}),
    },
);

/** Anything the client cannot mount, or the template does not render, is skipped. */
const rows = computed(() =>
    (Array.isArray(props.blocks) ? props.blocks : []).filter(row => {
        if (row === null || typeof row !== 'object') {
            return false;
        }

        const type = (row as PageSectionRow).type;

        return hasSectionComponent(type) && isSupportedSection(type, props.supports);
    }),
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
