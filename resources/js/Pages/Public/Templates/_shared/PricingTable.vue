<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Check } from 'lucide-vue-next';
import type { HomepageData } from '@/types';

withDefaults(defineProps<{ settings: HomepageData; tinted?: boolean }>(), { tinted: true });

const { t } = useI18n();
</script>

<template>
    <section id="pricing" class="border-t py-20 sm:py-24" :class="tinted ? 'bg-muted/30' : ''">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ settings.pricing_title }}</h2>
                <p class="mt-4 text-lg text-muted-foreground">{{ settings.pricing_subtitle }}</p>
            </div>
            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="(plan, i) in settings.pricing_plans"
                    :key="i"
                    class="animate-fade-in-up relative flex flex-col"
                    :class="plan.highlighted ? 'border-primary ring-2 ring-primary' : ''"
                    :style="{ animationDelay: `${i * 0.1}s` }"
                >
                    <Badge v-if="plan.highlighted" class="absolute -top-3 left-1/2 -translate-x-1/2">
                        {{ t('landing.pricing.popular') }}
                    </Badge>
                    <CardHeader class="text-center">
                        <CardTitle class="text-xl">{{ plan.name }}</CardTitle>
                        <div class="mt-4">
                            <span class="text-4xl font-extrabold">{{ plan.price }}</span>
                            <span class="text-muted-foreground">{{ plan.period }}</span>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col">
                        <ul class="flex-1 space-y-3">
                            <li
                                v-for="(feature, fi) in plan.features.split(',')"
                                :key="fi"
                                class="flex items-start gap-2 text-sm"
                            >
                                <Check class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                                <span>{{ feature.trim() }}</span>
                            </li>
                        </ul>
                        <Link :href="plan.cta_url" class="mt-8 block">
                            <Button class="w-full" :variant="plan.highlighted ? 'default' : 'outline'">
                                {{ plan.cta_text }}
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</template>
