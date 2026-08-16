<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { HomepageData } from '@/types';
import { useSiteBrand } from './useSiteBrand';

defineProps<{ settings: HomepageData }>();

const { t } = useI18n();
const { name, initial, logoUrl } = useSiteBrand();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t bg-muted/30">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                <div class="flex items-center gap-2">
                    <img v-if="logoUrl" :src="logoUrl" :alt="name" class="h-6 w-auto" />
                    <div
                        v-else
                        aria-hidden="true"
                        class="flex size-6 items-center justify-center rounded bg-primary text-xs font-bold text-primary-foreground"
                    >
                        {{ initial }}
                    </div>
                    <span class="font-semibold">{{ name }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <template v-for="link in settings.footer_links" :key="link.label">
                        <a
                            v-if="link.url.startsWith('http') || link.url.startsWith('#')"
                            :href="link.url"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-else
                            :href="link.url"
                            class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {{ link.label }}
                        </Link>
                    </template>
                </div>
            </div>
            <div class="mt-8 border-t pt-8 text-center">
                <p class="text-sm text-muted-foreground">{{ settings.footer_text }}</p>
                <p class="mt-2 text-xs text-muted-foreground/60">
                    {{ t('landing.footer.rights', { year, name }) }}
                </p>
            </div>
        </div>
    </footer>
</template>
