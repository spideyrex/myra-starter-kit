<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import BrandMark from '@/components/brand/BrandMark.vue';

defineProps<{
    code: string;
    title: string;
    description: string;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="`${code} - ${title}`" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="p-4">
            <BrandMark size="sm" href="/" />
        </header>

        <main id="content" class="flex flex-1 flex-col items-center justify-center px-4 text-center">
            <div class="animate-fade-in-up flex max-w-md flex-col items-center">
                <div class="rounded-full bg-muted p-6">
                    <slot name="icon" />
                </div>

                <p class="mt-6 text-6xl font-bold" aria-hidden="true">{{ code }}</p>
                <h1 class="mt-2 text-xl font-semibold">{{ title }}</h1>
                <p class="mt-2 text-muted-foreground">{{ description }}</p>

                <slot />

                <Button as-child variant="outline" class="mt-6">
                    <Link href="/">{{ t('brand.errors.home') }}</Link>
                </Button>
            </div>
        </main>
    </div>
</template>
