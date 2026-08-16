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

    <!-- Minimal: one narrow column, no imagery, no testimonial or pricing wall. -->
    <div class="min-h-screen bg-background text-foreground">
        <SiteNavbar :settings="settings" :authenticated="authenticated" variant="plain" />

        <main id="content" class="mx-auto max-w-3xl px-4 sm:px-6">
            <OrderedSections
                :settings="settings"
                :order="sectionOrder"
                :overrides="templateOptions"
                :supports="['hero', 'features', 'cta']"
                :variants="{
                    hero: { align: 'left', compact: true },
                    features: { columns: 2, bare: true },
                    cta: { muted: true },
                }"
            />
        </main>

        <SiteFooter :settings="settings" />
    </div>
</template>
