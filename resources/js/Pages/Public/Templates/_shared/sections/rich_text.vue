<script setup lang="ts">
import { computed } from 'vue';
import { sanitizeHtml } from '@/composables/useSanitize';
import type { HomepageData } from '@/types';

/**
 * The uniform section contract: normalised `data`, chrome context, presentation.
 * Every field is optional here so a half-migrated or hand-edited row degrades
 * to an empty section instead of throwing on the public front door.
 */
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

const heading = computed(() => text(block.value.heading));

/** Sanitised server-side on write and again here on render. Belt and braces. */
const body = computed(() => {
    const raw = text(block.value.body);

    return raw === '' ? '' : sanitizeHtml(raw);
});

const wide = computed(() => variant.value.width === 'wide');
const tinted = computed(() => variant.value.tinted === true);
</script>

<template>
    <section class="border-t py-16 sm:py-20" :class="tinted ? 'bg-muted/30' : ''">
        <div class="mx-auto px-4 sm:px-6 lg:px-8" :class="wide ? 'max-w-5xl' : 'max-w-3xl'">
            <h2 v-if="heading" class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                {{ heading }}
            </h2>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
                v-if="body"
                class="mt-6 max-w-full overflow-x-auto text-base leading-7 text-muted-foreground
                       [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4
                       [&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:italic
                       [&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-sm
                       [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-foreground
                       [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-foreground
                       [&_h4]:mt-6 [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:text-foreground
                       [&_hr]:my-8 [&_hr]:border-border
                       [&_img]:my-6 [&_img]:max-w-full [&_img]:rounded-lg
                       [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6
                       [&_p]:my-4 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-muted [&_pre]:p-4
                       [&_strong]:text-foreground [&_table]:w-full [&_table]:text-left
                       [&_td]:border-b [&_td]:border-border [&_td]:py-2 [&_th]:border-b [&_th]:border-border [&_th]:py-2 [&_th]:font-semibold
                       [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6"
                v-html="body"
            />
        </div>
    </section>
</template>
