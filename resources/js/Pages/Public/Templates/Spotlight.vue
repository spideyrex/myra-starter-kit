<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useThemeColors } from '@/composables/useThemeColors';
import SiteNavbar from './_shared/SiteNavbar.vue';
import SiteFooter from './_shared/SiteFooter.vue';
import OrderedSections from './_shared/OrderedSections.vue';
import { useSiteBrand } from './_shared/useSiteBrand';
import type { HomepageData } from '@/types';

withDefaults(
    defineProps<{
        settings: HomepageData;
        authenticated: boolean;
        sectionOrder?: string[];
        /** Per-section presentation overrides from HomepageSettings::$template_options. */
        templateOptions?: Record<string, Record<string, unknown>>;
    }>(),
    { sectionOrder: () => [], templateOptions: () => ({}) },
);

useThemeColors();

const { name } = useSiteBrand();
</script>

<template>
    <Head :title="name" />

    <!-- Spotlight: a centred hero over a brand gradient, no pricing wall. -->
    <div class="min-h-screen bg-background text-foreground">
        <div class="pointer-events-none fixed inset-x-0 top-0 h-[32rem] bg-gradient-to-b from-primary/15 to-transparent" aria-hidden="true" />

        <SiteNavbar :settings="settings" :authenticated="authenticated" variant="plain" />

        <main id="content" class="relative">
            <OrderedSections
                :settings="settings"
                :order="sectionOrder"
                :overrides="templateOptions"
                :supports="['hero', 'features', 'testimonials', 'cta']"
                :variants="{
                    hero: { align: 'center' },
                    features: { bare: true, columns: 3 },
                    testimonials: { tinted: true },
                }"
            />
        </main>

        <SiteFooter :settings="settings" />
    </div>
</template>
