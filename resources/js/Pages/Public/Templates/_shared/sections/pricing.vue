<script setup lang="ts">
import { computed } from 'vue';
import PricingTable from '../PricingTable.vue';
import { bool, rows, str } from '../sectionValues';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{
        block: Record<string, unknown>;
        settings: HomepageData;
        variant?: Record<string, unknown>;
    }>(),
    { variant: () => ({}) },
);

const synth = computed(
    () =>
        ({
            ...(props.settings ?? {}),
            pricing_title: str(props.block?.title),
            pricing_subtitle: str(props.block?.subtitle),
            // `features` stays a comma-joined string: PricingTable splits it.
            pricing_plans: rows(props.block?.plans).map(plan => ({
                name: str(plan.name),
                price: str(plan.price),
                period: str(plan.period),
                features: str(plan.features),
                cta_text: str(plan.cta_text),
                cta_url: str(plan.cta_url) || '#',
                highlighted: bool(plan.highlighted),
            })),
        }) as HomepageData,
);
</script>

<template>
    <PricingTable :settings="synth" v-bind="variant" />
</template>
