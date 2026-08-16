<script setup lang="ts">
import { computed } from 'vue';
import TestimonialWall from '../TestimonialWall.vue';
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
            testimonials_title: str(props.block?.title),
            testimonials_subtitle: str(props.block?.subtitle),
            // `name` must stay a string: TestimonialWall derives initials from it.
            testimonials: rows(props.block?.items).map(item => ({
                name: str(item.name),
                role: str(item.role),
                quote: str(item.quote),
            })),
        }) as HomepageData,
);
</script>

<template>
    <TestimonialWall :settings="synth" v-bind="variant" />
</template>
