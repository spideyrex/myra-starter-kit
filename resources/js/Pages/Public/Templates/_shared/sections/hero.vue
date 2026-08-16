<script setup lang="ts">
import { computed } from 'vue';
import HeroSection from '../HeroSection.vue';
import { nullableUrl, str } from '../sectionValues';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{
        block: Record<string, unknown>;
        settings: HomepageData;
        variant?: Record<string, unknown>;
    }>(),
    { variant: () => ({}) },
);

/** The renderer is untouched: the block is projected onto the shape it reads. */
const synth = computed(
    () =>
        ({
            ...(props.settings ?? {}),
            hero_title: str(props.block?.title),
            hero_subtitle: str(props.block?.subtitle),
            hero_cta_text: str(props.block?.cta_text),
            hero_cta_url: str(props.block?.cta_url) || '#',
            hero_image_url: nullableUrl(props.block?.image_url),
        }) as HomepageData,
);
</script>

<template>
    <HeroSection :settings="synth" v-bind="variant" />
</template>
