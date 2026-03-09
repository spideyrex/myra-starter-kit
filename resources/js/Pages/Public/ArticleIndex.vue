<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    articles: PaginatedData<{
        id: number;
        title: string;
        slug: string;
        excerpt?: string;
        published_at?: string;
        category?: { name: string; slug: string } | null;
        author?: { name: string } | null;
        featured_image_url?: string | null;
    }>;
    categories: Array<{ id: number; name: string; slug: string; articles_count?: number }>;
    currentCategory?: string;
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

function filterByCategory(slug?: string) {
    router.get('/blog', slug ? { category: slug } : {}, { preserveState: true });
}

function decodePaginationLabel(label: string): string {
    const el = document.createElement('span');
    el.innerHTML = label;
    return el.textContent || label;
}
</script>

<template>
    <PublicLayout :authenticated="authenticated">
        <Head title="Blog" />

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-10 text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Blog</h1>
                <p class="mt-3 text-lg text-muted-foreground">Latest articles and updates.</p>
            </div>

            <!-- Category Tabs -->
            <div v-if="categories.length > 0" class="mb-8 flex flex-wrap justify-center gap-2">
                <Button
                    :variant="!currentCategory ? 'default' : 'outline'"
                    size="sm"
                    @click="filterByCategory()"
                >
                    All
                </Button>
                <Button
                    v-for="cat in categories"
                    :key="cat.id"
                    :variant="currentCategory === cat.slug ? 'default' : 'outline'"
                    size="sm"
                    @click="filterByCategory(cat.slug)"
                >
                    {{ cat.name }}
                    <Badge v-if="cat.articles_count" variant="secondary" class="ml-1.5">{{ cat.articles_count }}</Badge>
                </Button>
            </div>

            <!-- Articles Grid -->
            <div v-if="articles.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="article in articles.data"
                    :key="article.id"
                    :href="`/blog/${article.slug}`"
                    class="group"
                >
                    <Card class="h-full overflow-hidden transition-shadow hover:shadow-md">
                        <img
                            v-if="article.featured_image_url"
                            :src="article.featured_image_url"
                            :alt="article.title"
                            class="h-48 w-full object-cover"
                        />
                        <div v-else class="flex h-48 items-center justify-center bg-muted">
                            <span class="text-4xl text-muted-foreground/30">&#9993;</span>
                        </div>
                        <CardContent class="p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <Badge v-if="article.category" variant="secondary">{{ article.category.name }}</Badge>
                            </div>
                            <h2 class="text-lg font-semibold group-hover:text-primary transition-colors">{{ article.title }}</h2>
                            <p v-if="article.excerpt" class="mt-1 text-sm text-muted-foreground line-clamp-2">{{ article.excerpt }}</p>
                            <div class="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                                <span v-if="article.author">{{ article.author.name }}</span>
                                <span v-if="article.published_at">{{ formatDate(article.published_at) }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="py-16 text-center text-muted-foreground">
                <p class="text-lg">No articles found.</p>
            </div>

            <!-- Pagination -->
            <div v-if="articles.meta.last_page > 1" class="mt-10 flex justify-center gap-1">
                <Button
                    v-for="link in articles.meta.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-8 px-2 text-xs sm:px-3 sm:text-sm"
                    :disabled="!link.url || link.active"
                    @click="link.url ? router.get(link.url) : undefined"
                >{{ decodePaginationLabel(link.label) }}</Button>
            </div>
        </div>
    </PublicLayout>
</template>
