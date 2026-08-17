<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowRight } from 'lucide-vue-next';
import { safeUrl } from '@/composables/useSafeUrl';
import type { HomepageData } from '@/types';

const props = withDefaults(defineProps<{ settings: HomepageData; muted?: boolean }>(), { muted: false });

/** Authored by an admin, rendered to anonymous visitors: scheme-gated, always. */
const ctaUrl = computed(() => safeUrl(props.settings?.cta_button_url) || '#');
</script>

<template>
    <section class="border-t">
        <div :class="muted ? 'bg-muted' : 'bg-primary'">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2
                        class="text-3xl font-bold tracking-tight sm:text-4xl"
                        :class="muted ? 'text-foreground' : 'text-primary-foreground'"
                        data-myra-field="title"
                        data-myra-kind="text"
                    >
                        {{ settings.cta_title }}
                    </h2>
                    <p
                        class="mt-4 text-lg"
                        :class="muted ? 'text-muted-foreground' : 'text-primary-foreground/80'"
                        data-myra-field="subtitle"
                        data-myra-kind="multiline"
                    >
                        {{ settings.cta_subtitle }}
                    </p>
                    <div class="mt-8">
                        <Link :href="ctaUrl">
                            <Button size="lg" :variant="muted ? 'default' : 'secondary'" class="gap-2 text-base">
                                <span data-myra-field="button_text" data-myra-kind="text">{{ settings.cta_button_text }}</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
