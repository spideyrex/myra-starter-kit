<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { HomepageData, SiteSettings } from '@/types';
import { useThemeColors } from '@/composables/useThemeColors';
import {
    Zap, Shield, BarChart3, Users, Lock, Globe, Rocket, Heart, Star,
    Code, Database, Cloud, Settings, Mail, Bell, Search, Layers, Layout,
    Menu, X, Check, ArrowRight, Quote,
} from 'lucide-vue-next';

const props = defineProps<{
    settings: HomepageData;
    siteSettings?: SiteSettings;
    authenticated: boolean;
}>();

useThemeColors();

const mobileMenuOpen = ref(false);

// Map icon name strings to lucide components
const iconMap: Record<string, any> = {
    Zap, Shield, BarChart3, Users, Lock, Globe, Rocket, Heart, Star,
    Code, Database, Cloud, Settings, Mail, Bell, Search, Layers, Layout,
};

function getIcon(name: string) {
    return iconMap[name] || Zap;
}

function getInitials(name: string): string {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function isAnchorLink(url: string): boolean {
    return url.startsWith('#');
}

function smoothScroll(url: string) {
    if (isAnchorLink(url)) {
        mobileMenuOpen.value = false;
        const el = document.querySelector(url);
        el?.scrollIntoView({ behavior: 'smooth' });
    }
}

const siteName = computed(() => props.siteSettings?.site_name || 'App');
const logoUrl = computed(() => props.siteSettings?.logo_url);
</script>

<template>
    <Head :title="siteName" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Navbar -->
        <nav class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Logo + Site Name -->
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="logoUrl" :src="logoUrl" :alt="siteName" class="h-8 w-auto" />
                    <div v-else class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm">
                        {{ siteName.charAt(0) }}
                    </div>
                    <span class="text-lg font-bold">{{ siteName }}</span>
                </Link>

                <!-- Desktop nav links -->
                <div class="hidden items-center gap-1 md:flex">
                    <template v-for="link in settings.navbar_links" :key="link.label">
                        <a
                            v-if="isAnchorLink(link.url)"
                            :href="link.url"
                            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                            @click.prevent="smoothScroll(link.url)"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-else
                            :href="link.url"
                            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ link.label }}
                        </Link>
                    </template>
                </div>

                <!-- Desktop CTA -->
                <div class="hidden items-center gap-3 md:flex">
                    <template v-if="authenticated">
                        <Link href="/dashboard">
                            <Button variant="default">Dashboard</Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link href="/login">
                            <Button variant="ghost">Log in</Button>
                        </Link>
                        <Link :href="settings.navbar_cta_url">
                            <Button>{{ settings.navbar_cta_text }}</Button>
                        </Link>
                    </template>
                </div>

                <!-- Mobile hamburger -->
                <button class="md:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                    <X v-if="mobileMenuOpen" class="size-6" />
                    <Menu v-else class="size-6" />
                </button>
            </div>

            <!-- Mobile menu -->
            <div v-if="mobileMenuOpen" class="border-t md:hidden">
                <div class="space-y-1 px-4 py-3">
                    <template v-for="link in settings.navbar_links" :key="link.label">
                        <a
                            v-if="isAnchorLink(link.url)"
                            :href="link.url"
                            class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                            @click.prevent="smoothScroll(link.url)"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-else
                            :href="link.url"
                            class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            {{ link.label }}
                        </Link>
                    </template>
                    <div class="border-t pt-3 mt-2 space-y-2">
                        <template v-if="authenticated">
                            <Link href="/dashboard" class="block">
                                <Button class="w-full">Dashboard</Button>
                            </Link>
                        </template>
                        <template v-else>
                            <Link href="/login" class="block">
                                <Button variant="outline" class="w-full">Log in</Button>
                            </Link>
                            <Link :href="settings.navbar_cta_url" class="block">
                                <Button class="w-full">{{ settings.navbar_cta_text }}</Button>
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <!-- Background: image with overlay or gradient -->
            <div
                v-if="settings.hero_image_url"
                class="absolute inset-0 bg-cover bg-center"
                :style="{ backgroundImage: `url(${settings.hero_image_url})` }"
            >
                <div class="absolute inset-0 bg-background/80 dark:bg-background/90" />
            </div>
            <div v-else class="absolute inset-0 bg-gradient-to-br from-primary/5 via-background to-primary/10" />

            <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8 lg:py-40">
                <div class="mx-auto max-w-3xl text-center">
                    <h1 class="animate-fade-in-up text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                        {{ settings.hero_title }}
                    </h1>
                    <p class="animate-fade-in-up mt-6 text-lg text-muted-foreground sm:text-xl" style="animation-delay: 0.1s">
                        {{ settings.hero_subtitle }}
                    </p>
                    <div class="animate-fade-in-up mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row" style="animation-delay: 0.2s">
                        <Link :href="settings.hero_cta_url">
                            <Button size="lg" class="gap-2 text-base">
                                {{ settings.hero_cta_text }}
                                <ArrowRight class="size-4" />
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section v-if="settings.features_enabled" id="features" class="border-t bg-muted/30 py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ settings.features_title }}</h2>
                    <p class="mt-4 text-lg text-muted-foreground">{{ settings.features_subtitle }}</p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="(feature, i) in settings.features" :key="i" class="animate-fade-in-up border-0 bg-background shadow-sm transition-shadow hover:shadow-md" :style="{ animationDelay: `${i * 0.1}s` }">
                        <CardHeader>
                            <div class="mb-3 flex size-12 items-center justify-center rounded-lg bg-primary/10">
                                <component :is="getIcon(feature.icon)" class="size-6 text-primary" />
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

        <!-- Testimonials Section -->
        <section v-if="settings.testimonials_enabled" id="testimonials" class="border-t py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ settings.testimonials_title }}</h2>
                    <p class="mt-4 text-lg text-muted-foreground">{{ settings.testimonials_subtitle }}</p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="(testimonial, i) in settings.testimonials" :key="i" class="animate-fade-in-up" :style="{ animationDelay: `${i * 0.1}s` }">
                        <CardContent class="pt-6">
                            <Quote class="mb-4 size-8 text-primary/20" />
                            <blockquote class="text-muted-foreground">
                                "{{ testimonial.quote }}"
                            </blockquote>
                            <div class="mt-6 flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                    {{ getInitials(testimonial.name) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-sm">{{ testimonial.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ testimonial.role }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section v-if="settings.pricing_enabled" id="pricing" class="border-t bg-muted/30 py-20 sm:py-24">
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
                        <Badge v-if="plan.highlighted" class="absolute -top-3 left-1/2 -translate-x-1/2">Most Popular</Badge>
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
                                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                                    <span>{{ feature.trim() }}</span>
                                </li>
                            </ul>
                            <Link :href="plan.cta_url" class="mt-8 block">
                                <Button
                                    class="w-full"
                                    :variant="plan.highlighted ? 'default' : 'outline'"
                                >
                                    {{ plan.cta_text }}
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section v-if="settings.cta_enabled" class="border-t">
            <div class="bg-primary">
                <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-primary-foreground sm:text-4xl">
                            {{ settings.cta_title }}
                        </h2>
                        <p class="mt-4 text-lg text-primary-foreground/80">
                            {{ settings.cta_subtitle }}
                        </p>
                        <div class="mt-8">
                            <Link :href="settings.cta_button_url">
                                <Button size="lg" variant="secondary" class="gap-2 text-base">
                                    {{ settings.cta_button_text }}
                                    <ArrowRight class="size-4" />
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-2">
                        <img v-if="logoUrl" :src="logoUrl" :alt="siteName" class="h-6 w-auto" />
                        <div v-else class="flex size-6 items-center justify-center rounded bg-primary text-primary-foreground text-xs font-bold">
                            {{ siteName.charAt(0) }}
                        </div>
                        <span class="font-semibold">{{ siteName }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-6">
                        <template v-for="link in settings.footer_links" :key="link.label">
                            <a
                                v-if="link.url.startsWith('http') || link.url.startsWith('#')"
                                :href="link.url"
                                class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ link.label }}
                            </a>
                            <Link v-else :href="link.url" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                                {{ link.label }}
                            </Link>
                        </template>
                    </div>
                </div>
                <div class="mt-8 border-t pt-8 text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ settings.footer_text }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground/60">
                        &copy; {{ new Date().getFullYear() }} {{ siteName }}. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
