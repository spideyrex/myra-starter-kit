<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowRight } from 'lucide-vue-next';
import type { HomepageData } from '@/types';

withDefaults(defineProps<{ settings: HomepageData; muted?: boolean }>(), { muted: false });
</script>

<template>
    <section class="border-t">
        <div :class="muted ? 'bg-muted' : 'bg-primary'">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2
                        class="text-3xl font-bold tracking-tight sm:text-4xl"
                        :class="muted ? 'text-foreground' : 'text-primary-foreground'"
                    >
                        {{ settings.cta_title }}
                    </h2>
                    <p
                        class="mt-4 text-lg"
                        :class="muted ? 'text-muted-foreground' : 'text-primary-foreground/80'"
                    >
                        {{ settings.cta_subtitle }}
                    </p>
                    <div class="mt-8">
                        <Link :href="settings.cta_button_url">
                            <Button size="lg" :variant="muted ? 'default' : 'secondary'" class="gap-2 text-base">
                                {{ settings.cta_button_text }}
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
