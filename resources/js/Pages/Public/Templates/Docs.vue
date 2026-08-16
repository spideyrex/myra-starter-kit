<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useThemeColors } from '@/composables/useThemeColors';
import SiteNavbar from './_shared/SiteNavbar.vue';
import SiteFooter from './_shared/SiteFooter.vue';
import OrderedSections from './_shared/OrderedSections.vue';
import { useSiteBrand } from './_shared/useSiteBrand';
import type { HomepageData } from '@/types';

const props = withDefaults(
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

/** The rail mirrors the sections actually rendered, so it never links to nothing. */
const rail = computed(() =>
    [
        { id: 'features', shown: props.settings.features_enabled },
        { id: 'pricing', shown: props.settings.pricing_enabled },
        { id: 'faq', shown: true },
    ].filter(entry => entry.shown),
);
</script>

<template>
    <Head :title="name" />

    <div class="min-h-screen bg-background text-foreground">
        <SiteNavbar :settings="settings" :authenticated="authenticated" />

        <div class="mx-auto flex max-w-7xl gap-8 px-4 sm:px-6 lg:px-8">
            <aside class="hidden w-56 shrink-0 py-10 lg:block">
                <nav :aria-label="t('landing.docs.onThisPage')" class="sticky top-24">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ t('landing.docs.onThisPage') }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="entry in rail" :key="entry.id">
                            <a
                                :href="`#${entry.id}`"
                                class="block rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                {{ t(`landing.sections.${entry.id === 'faq' ? 'cta' : entry.id}`) }}
                            </a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <main id="content" class="min-w-0 flex-1">
                <OrderedSections
                    :settings="settings"
                    :order="sectionOrder"
                :overrides="templateOptions"
                    :supports="['hero', 'features', 'pricing', 'cta']"
                    :variants="{
                        hero: { align: 'left', compact: true },
                        features: { columns: 2, bare: true },
                        pricing: { tinted: false },
                        cta: { muted: true },
                    }"
                />
            </main>
        </div>

        <SiteFooter :settings="settings" />
    </div>
</template>
