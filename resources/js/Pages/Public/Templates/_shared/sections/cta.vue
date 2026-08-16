<script setup lang="ts">
import { computed } from 'vue';
import CtaBand from '../CtaBand.vue';
import { str } from '../sectionValues';
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
            cta_title: str(props.block?.title),
            cta_subtitle: str(props.block?.subtitle),
            cta_button_text: str(props.block?.button_text),
            cta_button_url: str(props.block?.button_url) || '#',
        }) as HomepageData,
);
</script>

<template>
    <CtaBand :settings="synth" v-bind="variant" />
</template>
