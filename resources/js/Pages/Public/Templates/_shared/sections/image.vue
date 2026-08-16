<script setup lang="ts">
import { computed } from 'vue';
import { isExternalUrl, safeSrc, safeUrl } from '@/composables/useSafeUrl';
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

/** withDefaults substitutes only `undefined`, so an explicit null still lands here. */
const obj = (v: unknown): Record<string, unknown> =>
    v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : {};

const block = computed(() => obj(props.block));
const variant = computed(() => obj(props.variant));

/**
 * `image_url` is the resolved URL and wins whenever the key is present: a
 * normaliser that emits null there is saying the file is gone, and the caption
 * renders alone rather than a broken image. When the key is absent altogether
 * the stored `image_path` is resolved against the public disk convention, so a
 * server that never learned about `image_url` still paints the figure.
 */
const url = computed(() => {
    if ('image_url' in block.value) return safeSrc(block.value.image_url);

    const path = text(block.value.image_path);

    if (path === '') return '';

    return safeSrc(/^(?:https?:|\/)/i.test(path) ? path : `/storage/${path}`);
});

const alt = computed(() => text(block.value.alt));
const caption = computed(() => text(block.value.caption));

/** Scheme-gated, not truthiness-gated: `javascript:` must never reach href. */
const link = computed(() => safeUrl(block.value.link_url));

const full = computed(() => variant.value.width === 'full');
const rounded = computed(() => variant.value.rounded === true);
const external = computed(() => isExternalUrl(link.value));
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
