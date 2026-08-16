<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Menu, X } from 'lucide-vue-next';
import { safeSrc, safeUrl } from '@/composables/useSafeUrl';
import type { HomepageData } from '@/types';
import { useSiteBrand } from './useSiteBrand';

const props = withDefaults(
    defineProps<{ settings: HomepageData; authenticated: boolean; variant?: 'sticky' | 'plain' }>(),
    { variant: 'sticky' },
);

const { t } = useI18n();
const { name, initial, logoUrl } = useSiteBrand();

const mobileMenuOpen = ref(false);

function isAnchorLink(url: string): boolean {
    return typeof url === 'string' && url.startsWith('#');
}

/** Authored by an admin, rendered to anonymous visitors: scheme-gated, always. */
const navUrl = (url: unknown): string => safeUrl(url) || '#';

const ctaUrl = computed(() => safeUrl(props.settings?.navbar_cta_url) || '#');
const brandLogo = computed(() => safeSrc(logoUrl.value));

function smoothScroll(url: string) {
    if (!isAnchorLink(url)) return;

    mobileMenuOpen.value = false;

    try {
        document.querySelector(url)?.scrollIntoView({ behavior: 'smooth' });
    } catch {
        // An authored anchor is not guaranteed to be a valid selector.
    }
}
</script>

<template>
    <!-- The skip link is the first focusable element on every template. -->
    <a
        href="#content"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:ring-2 focus:ring-ring"
    >
        {{ t('landing.nav.skipToContent') }}
    </a>

    <nav
        :aria-label="t('landing.nav.label')"
        class="z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        :class="props.variant === 'sticky' ? 'sticky top-0' : 'relative'"
    >
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" class="flex items-center gap-2.5">
                <img v-if="brandLogo" :src="brandLogo" :alt="name" class="h-8 w-auto" />
                <div
                    v-else
                    aria-hidden="true"
                    class="flex size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground"
                >
                    {{ initial }}
                </div>
                <span class="text-lg font-bold">{{ name }}</span>
            </Link>

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
                        :href="navUrl(link.url)"
                        class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </Link>
                </template>
            </div>

            <div class="hidden items-center gap-3 md:flex">
                <template v-if="authenticated">
                    <Link href="/dashboard">
                        <Button variant="default">{{ t('landing.nav.dashboard') }}</Button>
                    </Link>
                </template>
                <template v-else>
                    <Link href="/login">
                        <Button variant="ghost">{{ t('landing.nav.login') }}</Button>
                    </Link>
                    <Link :href="ctaUrl">
                        <Button>{{ settings.navbar_cta_text }}</Button>
                    </Link>
                </template>
            </div>

            <button
                type="button"
                class="rounded-md p-1 md:hidden"
                :aria-expanded="mobileMenuOpen"
                aria-controls="site-mobile-menu"
                :aria-label="mobileMenuOpen ? t('landing.nav.closeMenu') : t('landing.nav.openMenu')"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <X v-if="mobileMenuOpen" class="size-6" aria-hidden="true" />
                <Menu v-else class="size-6" aria-hidden="true" />
            </button>
        </div>

        <div v-if="mobileMenuOpen" id="site-mobile-menu" class="border-t md:hidden">
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
                        :href="navUrl(link.url)"
                        class="block rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        {{ link.label }}
                    </Link>
                </template>
                <div class="mt-2 space-y-2 border-t pt-3">
                    <template v-if="authenticated">
                        <Link href="/dashboard" class="block">
                            <Button class="w-full">{{ t('landing.nav.dashboard') }}</Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="block">
                            <Button variant="outline" class="w-full">{{ t('landing.nav.login') }}</Button>
                        </Link>
                        <Link :href="ctaUrl" class="block">
                            <Button class="w-full">{{ settings.navbar_cta_text }}</Button>
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </nav>
</template>
