<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Quote } from 'lucide-vue-next';
import type { HomepageData } from '@/types';

withDefaults(defineProps<{ settings: HomepageData; tinted?: boolean }>(), { tinted: false });

function getInitials(name: string): string {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}
</script>

<template>
    <section id="testimonials" class="border-t py-20 sm:py-24" :class="tinted ? 'bg-muted/30' : ''">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl" data-myra-field="title" data-myra-kind="text">{{ settings.testimonials_title }}</h2>
                <p class="mt-4 text-lg text-muted-foreground" data-myra-field="subtitle" data-myra-kind="multiline">{{ settings.testimonials_subtitle }}</p>
            </div>
            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="(testimonial, i) in settings.testimonials"
                    :key="i"
                    class="animate-fade-in-up"
                    :style="{ animationDelay: `${i * 0.1}s` }"
                >
                    <CardContent class="pt-6">
                        <Quote class="mb-4 size-8 text-primary/20" aria-hidden="true" />
                        <blockquote class="text-muted-foreground">
                            &ldquo;<span :data-myra-field="`items.${i}.quote`" data-myra-kind="multiline">{{ testimonial.quote }}</span>&rdquo;
                        </blockquote>
                        <div class="mt-6 flex items-center gap-3">
                            <div
                                aria-hidden="true"
                                class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                            >
                                {{ getInitials(testimonial.name) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold" :data-myra-field="`items.${i}.name`" data-myra-kind="text">{{ testimonial.name }}</p>
                                <p class="text-xs text-muted-foreground" :data-myra-field="`items.${i}.role`" data-myra-kind="text">{{ testimonial.role }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</template>
