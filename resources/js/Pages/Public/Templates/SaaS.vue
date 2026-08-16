<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useThemeColors } from '@/composables/useThemeColors';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
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

const { t } = useI18n();
const { name } = useSiteBrand();

/** Pricing-first, so the stored order is nudged rather than obeyed blindly. */
const faqs = computed(() => ['billing', 'trial', 'support', 'cancel']);
</script>

<template>
    <Head :title="name" />

    <div class="min-h-screen bg-background text-foreground">
        <SiteNavbar :settings="settings" :authenticated="authenticated" />

        <main id="content">
            <OrderedSections
                :settings="settings"
                :order="sectionOrder"
                :overrides="templateOptions"
                :supports="['hero', 'pricing', 'features', 'testimonials', 'cta']"
                :variants="{
                    hero: { align: 'center', compact: true },
                    pricing: { tinted: false },
                    features: { bare: false },
                }"
            />

            <section id="faq" class="border-t bg-muted/30 py-20 sm:py-24">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ t('landing.faq.title') }}
                    </h2>
                    <Accordion type="single" collapsible class="mt-10 w-full">
                        <AccordionItem v-for="id in faqs" :key="id" :value="id">
                            <AccordionTrigger>{{ t(`landing.faq.items.${id}.question`) }}</AccordionTrigger>
                            <AccordionContent>{{ t(`landing.faq.items.${id}.answer`) }}</AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>
            </section>
        </main>

        <SiteFooter :settings="settings" />
    </div>
</template>
