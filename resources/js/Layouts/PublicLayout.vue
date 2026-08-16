<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useThemeColors } from '@/composables/useThemeColors';
// >>> MYRA v2.6 [C] START
import BrandMark from '@/components/brand/BrandMark.vue';
import { useBrand } from '@/composables/useBrand';

defineProps<{
    authenticated?: boolean;
}>();

const { name: siteName } = useBrand();
// <<< MYRA v2.6 [C] END

useThemeColors();
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Navbar -->
        <nav class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
<!-- >>> MYRA v2.6 [C] START -->
                <Link href="/" class="flex items-center gap-2.5">
                    <BrandMark size="md" />
                </Link>
                <!-- <<< MYRA v2.6 [C] END -->

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
                    <BrandMark size="sm" />
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
