<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowRight } from 'lucide-vue-next';
import type { HomepageData } from '@/types';

withDefaults(
    defineProps<{ settings: HomepageData; align?: 'center' | 'split' | 'left'; compact?: boolean }>(),
    { align: 'center', compact: false },
);
</script>

<template>
    <section class="relative overflow-hidden">
        <div
            v-if="settings.hero_image_url"
            class="absolute inset-0 bg-cover bg-center"
            :style="{ backgroundImage: `url(${settings.hero_image_url})` }"
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
                    <h1 class="animate-fade-in-up text-4xl font-extrabold tracking-tight sm:text-5xl">
                        {{ settings.hero_title }}
                    </h1>
                    <p class="animate-fade-in-up mt-6 text-lg text-muted-foreground" style="animation-delay: 0.1s">
                        {{ settings.hero_subtitle }}
                    </p>
                    <div class="animate-fade-in-up mt-8" style="animation-delay: 0.2s">
                        <Link :href="settings.hero_cta_url">
                            <Button size="lg" class="gap-2 text-base">
                                {{ settings.hero_cta_text }}
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>
                        </Link>
                    </div>
                </div>
                <div class="hidden aspect-[4/3] rounded-xl border bg-gradient-to-br from-primary/10 to-primary/0 lg:block" aria-hidden="true" />
            </div>

            <div v-else :class="align === 'left' ? 'max-w-3xl' : 'mx-auto max-w-3xl text-center'">
                <h1 class="animate-fade-in-up text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                    {{ settings.hero_title }}
                </h1>
                <p class="animate-fade-in-up mt-6 text-lg text-muted-foreground sm:text-xl" style="animation-delay: 0.1s">
                    {{ settings.hero_subtitle }}
                </p>
                <div
                    class="animate-fade-in-up mt-10 flex flex-col gap-4 sm:flex-row"
                    :class="align === 'left' ? 'items-start' : 'items-center justify-center'"
                    style="animation-delay: 0.2s"
                >
                    <Link :href="settings.hero_cta_url">
                        <Button size="lg" class="gap-2 text-base">
                            {{ settings.hero_cta_text }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
