<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Card, CardContent } from '@/components/ui/card';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/composables/useFlashToasts';
// >>> MYRA v2.6 [C] START
import BrandMark from '@/components/brand/BrandMark.vue';
import { useBrand } from '@/composables/useBrand';

const { brand } = useBrand();
const { t } = useI18n();
// <<< MYRA v2.6 [C] END

useFlashToasts();
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left branding panel (hidden on mobile) -->
        <div class="hidden lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center bg-primary px-8 text-primary-foreground">
            <div class="max-w-md space-y-6 text-center">
                <div class="flex items-center justify-center gap-3 text-3xl font-bold">
                    <BrandMark variant="full" size="lg" />
                </div>
                <blockquote class="mt-8 border-l-2 border-primary-foreground/30 pl-4 text-left text-lg italic text-primary-foreground/80">
                    "{{ brand.tagline || t('auth.defaultTagline') }}"
                </blockquote>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="flex w-full flex-col items-center justify-center px-4 py-8 lg:w-1/2">
            <!-- Mobile logo (visible on small screens only) -->
            <div class="mb-6 lg:hidden">
                <Link :href="route('login')" class="text-foreground">
                    <BrandMark variant="full" size="md" />
                </Link>
            </div>

            <Card class="w-full max-w-md border-0 shadow-none lg:border lg:shadow-sm">
                <CardContent class="p-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>

    <Toaster />
</template>
