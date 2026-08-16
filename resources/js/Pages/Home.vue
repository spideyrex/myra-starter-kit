<script setup lang="ts">
import { computed, defineAsyncComponent, type Component, type DefineComponent } from 'vue';
import type { HomepageData, PageSectionRow } from '@/types';
import Classic from './Public/Templates/Classic.vue';

const props = withDefaults(
    defineProps<{
        settings: HomepageData;
        authenticated: boolean;
        template?: string;
        templateOptions?: Record<string, Record<string, unknown>>;
        sectionOrder?: string[];
        /** The authored page. Empty falls through to the flat settings. */
        blocks?: PageSectionRow[];
    }>(),
    { template: 'classic', templateOptions: () => ({}), sectionOrder: () => [], blocks: () => [] },
);

const modules = import.meta.glob<DefineComponent>('./Public/Templates/*.vue');

/** Registry keys are lower-case; the files keep their upstream casing (SaaS.vue). */
const byKey: Record<string, () => Promise<DefineComponent>> = Object.fromEntries(
    Object.entries(modules).map(([path, loader]) => [
        (path.split('/').pop() ?? '').replace(/\.vue$/, '').toLowerCase(),
        loader,
    ]),
);

/** Memoised so a re-render never hands <component> a fresh, still-pending wrapper. */
const cache = new Map<string, Component>();

/**
 * An unknown, renamed or deleted key falls back to Classic — the public
 * homepage must never render blank because of a stored setting.
 */
const resolved = computed<Component>(() => {
    const key = (props.template ?? 'classic').toLowerCase();
    const loader = byKey[key];

    if (!loader) {
        return Classic;
    }

    if (!cache.has(key)) {
        cache.set(key, defineAsyncComponent(loader));
    }

    return cache.get(key)!;
});
</script>

<template>
    <component
        :is="resolved"
        :settings="props.settings"
        :authenticated="props.authenticated"
        :section-order="props.sectionOrder"
        :template-options="props.templateOptions"
        :blocks="props.blocks"
    />
</template>
