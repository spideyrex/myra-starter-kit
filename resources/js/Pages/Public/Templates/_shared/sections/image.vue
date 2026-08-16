<script setup lang="ts">
import { computed } from 'vue';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{
        block?: Record<string, unknown>;
        settings?: HomepageData;
        variant?: Record<string, unknown>;
    }>(),
    { block: () => ({}), variant: () => ({}) },
);

const text = (v: unknown): string => (typeof v === 'string' ? v : '');

/**
 * The normaliser only emits `image_url` when the file is actually on disk, so a
 * missing upload renders the caption alone rather than a broken image icon on
 * the public page.
 */
const url = computed(() => text(props.block.image_url));
const alt = computed(() => text(props.block.alt));
const caption = computed(() => text(props.block.caption));
const link = computed(() => text(props.block.link_url));

const full = computed(() => props.variant.width === 'full');
const rounded = computed(() => props.variant.rounded === true);
const external = computed(() => /^https?:/i.test(link.value));
</script>

<template>
    <section class="border-t py-12 sm:py-16">
        <div class="mx-auto px-4 sm:px-6 lg:px-8" :class="full ? 'max-w-none' : 'max-w-5xl'">
            <figure v-if="url || caption" class="m-0">
                <component
                    :is="link ? 'a' : 'div'"
                    v-if="url"
                    :href="link || undefined"
                    :rel="link && external ? 'noopener noreferrer' : undefined"
                    class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <img
                        :src="url"
                        :alt="alt"
                        loading="lazy"
                        decoding="async"
                        class="h-auto w-full bg-muted object-cover"
                        :class="rounded ? 'rounded-xl' : ''"
                    />
                </component>
                <figcaption v-if="caption" class="mt-3 text-center text-sm text-muted-foreground">
                    {{ caption }}
                </figcaption>
            </figure>
        </div>
    </section>
</template>
