<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useThemeColors } from '@/composables/useThemeColors';
import SiteNavbar from './_shared/SiteNavbar.vue';
import SiteFooter from './_shared/SiteFooter.vue';
import SiteSurface from './_shared/SiteSurface.vue';
import TemplateBody from './_shared/TemplateBody.vue';
import { useSiteBrand } from './_shared/useSiteBrand';
import type { HomepageData, PageSectionRow } from '@/types';

withDefaults(
    defineProps<{
        settings: HomepageData;
        authenticated: boolean;
        sectionOrder?: string[];
        /** Per-section presentation overrides from HomepageSettings::$template_options. */
        templateOptions?: Record<string, Record<string, unknown>>;
        blocks?: PageSectionRow[];
    }>(),
    { sectionOrder: () => [], templateOptions: () => ({}), blocks: () => [] },
);

useThemeColors();

const { name } = useSiteBrand();
</script>

<template>
    <Head :title="name" />

    <!-- Minimal: one narrow column, no imagery, no testimonial or pricing wall. -->
    <SiteSurface v-slot="{ translucent }">
        <SiteNavbar :settings="settings" :authenticated="authenticated" variant="plain" :translucent="translucent" />

        <main id="content" class="mx-auto max-w-3xl px-4 sm:px-6">
            <TemplateBody
                :blocks="blocks"
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
    </SiteSurface>
</template>
