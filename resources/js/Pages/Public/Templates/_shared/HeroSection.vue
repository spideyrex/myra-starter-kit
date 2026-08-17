<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowRight } from 'lucide-vue-next';
import { safeSrc, safeUrl } from '@/composables/useSafeUrl';
import type { HomepageData } from '@/types';

const props = withDefaults(
    defineProps<{ settings: HomepageData; align?: 'center' | 'split' | 'left'; compact?: boolean }>(),
    { align: 'center', compact: false },
);

/** Authored by an admin, rendered to anonymous visitors: scheme-gated, always. */
const ctaUrl = computed(() => safeUrl(props.settings?.hero_cta_url) || '#');

const image = computed(() => safeSrc(props.settings?.hero_image_url));

/**
 * Split alignment has a dedicated art slot, so the image belongs IN it. Every
 * other alignment has nowhere to put it and uses it as a full-bleed backdrop.
 * Never both, or the same picture renders twice.
 */
const inlineImage = computed(() => (props.align === 'split' ? image.value : ''));
const backdrop = computed(() => (props.align === 'split' ? '' : image.value));

/** Quoted, so a URL carrying `)` or `"` cannot break out of the declaration. */
const backdropStyle = computed(() => ({ backgroundImage: `url(${JSON.stringify(backdrop.value)})` }));
</script>

<template>
    <section class="relative overflow-hidden">
        <div
            v-if="backdrop"
            class="absolute inset-0 bg-cover bg-center"
            :style="backdropStyle"
        >
            <div class="absolute inset-0 bg-background/80 dark:bg-background/90" />
        </div>
        <div v-else class="absolute inset-0 bg-gradient-to-br from-primary/5 via-background to-primary/10" />

        <div
            class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
            :class="compact ? 'py-14 sm:py-20' : 'py-24 sm:py-32 lg:py-40'"
        >
            <div
                v-if="align === 'split'"
                class="grid items-center gap-10 lg:grid-cols-2"
            >
                <div>
                    <h1
                        class="animate-fade-in-up text-4xl font-extrabold tracking-tight sm:text-5xl"
                        data-myra-field="title"
                        data-myra-kind="text"
                    >
                        {{ settings.hero_title }}
                    </h1>
                    <p
                        class="animate-fade-in-up mt-6 text-lg text-muted-foreground"
                        style="animation-delay: 0.1s"
                        data-myra-field="subtitle"
                        data-myra-kind="multiline"
                    >
                        {{ settings.hero_subtitle }}
                    </p>
                    <div class="animate-fade-in-up mt-8" style="animation-delay: 0.2s">
                        <Link :href="ctaUrl">
                            <Button size="lg" class="gap-2 text-base">
                                <span data-myra-field="cta_text" data-myra-kind="text">{{ settings.hero_cta_text }}</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Split alignment shows the authored image; empty, it is the
                     placeholder box, which doubles as the drop target in the builder. -->
                <div
                    class="hidden aspect-[4/3] overflow-hidden rounded-xl border bg-gradient-to-br from-primary/10 to-primary/0 lg:block"
                    data-myra-field="image_url"
                    data-myra-kind="image"
                    :data-myra-placeholder="'Image'"
                >
                    <img
                        v-if="inlineImage"
                        :src="inlineImage"
                        alt=""
                        class="size-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </div>

            <div v-else :class="align === 'left' ? 'max-w-3xl' : 'mx-auto max-w-3xl text-center'">
                <h1
                    class="animate-fade-in-up text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl"
                    data-myra-field="title"
                    data-myra-kind="text"
                >
                    {{ settings.hero_title }}
                </h1>
                <p
                    class="animate-fade-in-up mt-6 text-lg text-muted-foreground sm:text-xl"
                    style="animation-delay: 0.1s"
                    data-myra-field="subtitle"
                    data-myra-kind="multiline"
                >
                    {{ settings.hero_subtitle }}
                </p>
                <div
                    class="animate-fade-in-up mt-10 flex flex-col gap-4 sm:flex-row"
                    :class="align === 'left' ? 'items-start' : 'items-center justify-center'"
                    style="animation-delay: 0.2s"
                >
                    <Link :href="ctaUrl">
                        <Button size="lg" class="gap-2 text-base">
                            <span data-myra-field="cta_text" data-myra-kind="text">{{ settings.hero_cta_text }}</span>
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
