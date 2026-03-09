<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    page: {
        title: string;
        body_html: string;
        excerpt?: string;
        meta?: {
            meta_title?: string;
            meta_description?: string;
        };
        published_at?: string;
        featured_image_url?: string | null;
    };
    authenticated: boolean;
}>();

function formatDate(dateStr?: string): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}
</script>

<template>
    <PublicLayout :authenticated="authenticated">
        <Head :title="page.meta?.meta_title || page.title" />

        <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Featured Image -->
            <img
                v-if="page.featured_image_url"
                :src="page.featured_image_url"
                :alt="page.title"
                class="mb-8 w-full rounded-lg object-cover"
                style="max-height: 400px"
            />

            <!-- Header -->
            <header class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ page.title }}</h1>
                <p v-if="page.published_at" class="mt-2 text-sm text-muted-foreground">
                    {{ formatDate(page.published_at) }}
                </p>
            </header>

            <!-- Content -->
            <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="page.body_html" />
        </article>
    </PublicLayout>
</template>
