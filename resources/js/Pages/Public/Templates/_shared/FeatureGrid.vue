<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Zap, Shield, BarChart3, Users, Lock, Globe, Rocket, Heart, Star,
    Code, Database, Cloud, Settings, Mail, Bell, Search, Layers, Layout,
} from 'lucide-vue-next';
import type { HomepageData } from '@/types';

withDefaults(
    defineProps<{ settings: HomepageData; columns?: 2 | 3; bare?: boolean }>(),
    { columns: 3, bare: false },
);

const iconMap: Record<string, any> = {
    Zap, Shield, BarChart3, Users, Lock, Globe, Rocket, Heart, Star,
    Code, Database, Cloud, Settings, Mail, Bell, Search, Layers, Layout,
};

function getIcon(name: string) {
    return iconMap[name] || Zap;
}
</script>

<template>
    <section id="features" class="border-t py-20 sm:py-24" :class="bare ? '' : 'bg-muted/30'">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ settings.features_title }}</h2>
                <p class="mt-4 text-lg text-muted-foreground">{{ settings.features_subtitle }}</p>
            </div>
            <div
                class="mt-16 grid gap-8 sm:grid-cols-2"
                :class="columns === 3 ? 'lg:grid-cols-3' : ''"
            >
                <Card
                    v-for="(feature, i) in settings.features"
                    :key="i"
                    class="animate-fade-in-up border-0 bg-background shadow-sm transition-shadow hover:shadow-md"
                    :style="{ animationDelay: `${i * 0.1}s` }"
                >
                    <CardHeader>
                        <div class="mb-3 flex size-12 items-center justify-center rounded-lg bg-primary/10">
                            <component :is="getIcon(feature.icon)" class="size-6 text-primary" aria-hidden="true" />
                        </div>
                        <CardTitle class="text-lg">{{ feature.title }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground">{{ feature.description }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</template>
