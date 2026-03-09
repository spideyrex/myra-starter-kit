<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { SiteSettings } from '@/types';
import { useThemeColors } from '@/composables/useThemeColors';

const props = defineProps<{
    authenticated?: boolean;
}>();

const page = usePage();
const siteSettings = computed(() => (page.props.siteSettings ?? {}) as SiteSettings);
const siteName = computed(() => siteSettings.value.site_name || 'App');
const logoUrl = computed(() => siteSettings.value.logo_url);

useThemeColors();
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Navbar -->
        <nav class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="logoUrl" :src="logoUrl" :alt="siteName" class="h-8 w-auto" />
                    <div v-else class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm">
                        {{ siteName.charAt(0) }}
                    </div>
                    <span class="text-lg font-bold">{{ siteName }}</span>
                </Link>

                <div class="flex items-center gap-3">
                    <Link v-if="authenticated" href="/dashboard">
                        <Button variant="default" size="sm">Dashboard</Button>
                    </Link>
                    <template v-else>
                        <Link href="/login">
                            <Button variant="ghost" size="sm">Log in</Button>
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

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
                </div>
                <div class="mt-8 border-t pt-8 text-center">
                    <p class="text-xs text-muted-foreground/60">
                        &copy; {{ new Date().getFullYear() }} {{ siteName }}. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
