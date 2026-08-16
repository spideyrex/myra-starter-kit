<script setup lang="ts">
import { computed } from 'vue';
import HeroSection from './HeroSection.vue';
import FeatureGrid from './FeatureGrid.vue';
import TestimonialWall from './TestimonialWall.vue';
import PricingTable from './PricingTable.vue';
import CtaBand from './CtaBand.vue';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{
        settings: HomepageData;
        /** Server-resolved order, already filtered to what this template supports. */
        order?: string[];
        /** Sections this template renders, regardless of what the order contains. */
        supports?: string[];
        /** Per-section presentation props, so templates differ in chrome only. */
        variants?: Record<string, Record<string, unknown>>;
        /** HomepageSettings::$template_options for this template, merged over the variants. */
        overrides?: Record<string, Record<string, unknown>>;
    }>(),
    {
        order: () => [],
        supports: () => ['hero', 'features', 'testimonials', 'pricing', 'cta'],
        variants: () => ({}),
        overrides: () => ({}),
    },
);

function sectionProps(key: string): Record<string, unknown> {
    return { ...(props.variants[key] ?? {}), ...(props.overrides[key] ?? {}) };
}

/** The section toggles live in HomepageSettings; hero has no toggle. */
const enabled: Record<string, (s: HomepageData) => boolean> = {
    hero: () => true,
    features: s => s.features_enabled,
    testimonials: s => s.testimonials_enabled,
    pricing: s => s.pricing_enabled,
    cta: s => s.cta_enabled,
};

const components: Record<string, unknown> = {
    hero: HeroSection,
    features: FeatureGrid,
    testimonials: TestimonialWall,
    pricing: PricingTable,
    cta: CtaBand,
};

const sections = computed(() => {
    const requested = props.order.length > 0 ? props.order : props.supports;

    return requested.filter(
        key =>
            props.supports.includes(key) &&
            Object.prototype.hasOwnProperty.call(components, key) &&
            enabled[key](props.settings),
    );
});
</script>

<template>
    <component
        :is="components[key]"
        v-for="key in sections"
        :key="key"
        :settings="settings"
        v-bind="sectionProps(key)"
    />
</template>
