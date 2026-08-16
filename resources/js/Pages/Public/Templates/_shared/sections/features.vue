<script setup lang="ts">
import { computed } from 'vue';
import FeatureGrid from '../FeatureGrid.vue';
import { rows, str } from '../sectionValues';
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
            features_title: str(props.block?.title),
            features_subtitle: str(props.block?.subtitle),
            features: rows(props.block?.items).map(item => ({
                icon: str(item.icon),
                title: str(item.title),
                description: str(item.description),
            })),
        }) as HomepageData,
);

/** The schema declares columns as a string; FeatureGrid compares it to a number. */
const presentation = computed(() => {
    const out = { ...props.variant };

    if (out.columns !== undefined) {
        out.columns = Number(out.columns) === 2 ? 2 : 3;
    }

    return out;
});
</script>

<template>
    <FeatureGrid :settings="synth" v-bind="presentation" />
</template>
