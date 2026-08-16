<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useThemeColors } from '@/composables/useThemeColors';
import SiteNavbar from './_shared/SiteNavbar.vue';
import SiteFooter from './_shared/SiteFooter.vue';
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

    <div class="min-h-screen bg-background text-foreground">
        <SiteNavbar :settings="settings" :authenticated="authenticated" />

        <main id="content">
            <TemplateBody
                :blocks="blocks"
                :settings="settings"
                :order="sectionOrder"
                :overrides="templateOptions"
                :supports="['hero', 'features', 'testimonials', 'pricing', 'cta']"
            />
        </main>

        <SiteFooter :settings="settings" />
    </div>
</template>
