<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

const props = defineProps<{
    article: {
        title: string;
        body_html: string;
        excerpt?: string;
        meta?: {
            meta_title?: string;
            meta_description?: string;
        };
        published_at?: string;
        tags?: string[];
        category?: { name: string; slug: string } | null;
        author?: { name: string; avatar?: string } | null;
        featured_image_url?: string | null;
    };
    relatedArticles: Array<{
        id: number;
        title: string;
        slug: string;
        excerpt?: string;
        published_at?: string;
        category?: { name: string; slug: string } | null;
        featured_image_url?: string | null;
    }>;
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

function getInitials(name: string): string {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}
</script>

<template>
    <PublicLayout :authenticated="authenticated">
        <Head :title="article.meta?.meta_title || article.title" />

        <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Featured Image -->
            <img
                v-if="article.featured_image_url"
                :src="article.featured_image_url"
                :alt="article.title"
                class="mb-8 w-full rounded-lg object-cover"
                style="max-height: 400px"
            />

            <!-- Header -->
            <header class="mb-8">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <Badge v-if="article.category" variant="secondary">
                        <Link :href="`/blog?category=${article.category.slug}`" class="hover:underline">
                            {{ article.category.name }}
                        </Link>
                    </Badge>
                </div>

                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ article.title }}</h1>

                <div class="mt-4 flex items-center gap-4">
                    <div v-if="article.author" class="flex items-center gap-2">
                        <Avatar class="size-8">
                            <AvatarImage v-if="article.author.avatar" :src="article.author.avatar" :alt="article.author.name" />
                            <AvatarFallback>{{ getInitials(article.author.name) }}</AvatarFallback>
                        </Avatar>
                        <span class="text-sm font-medium">{{ article.author.name }}</span>
                    </div>
                    <span v-if="article.published_at" class="text-sm text-muted-foreground">
                        {{ formatDate(article.published_at) }}
                    </span>
                </div>
            </header>

            <!-- Content -->
            <div class="prose prose-neutral dark:prose-invert max-w-none" v-html="article.body_html" />

            <!-- Tags -->
            <div v-if="article.tags && article.tags.length > 0" class="mt-8 flex flex-wrap gap-2">
                <Badge v-for="tag in article.tags" :key="tag" variant="outline">{{ tag }}</Badge>
            </div>
        </article>

        <!-- Related Articles -->
        <section v-if="relatedArticles.length > 0" class="border-t bg-muted/30 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="mb-8 text-2xl font-bold">Related Articles</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link v-for="related in relatedArticles" :key="related.id" :href="`/blog/${related.slug}`" class="group">
                        <Card class="h-full overflow-hidden transition-shadow hover:shadow-md">
                            <img
                                v-if="related.featured_image_url"
                                :src="related.featured_image_url"
                                :alt="related.title"
                                class="h-48 w-full object-cover"
                            />
                            <div v-else class="flex h-48 items-center justify-center bg-muted">
                                <span class="text-4xl text-muted-foreground/30">&#9993;</span>
                            </div>
                            <CardContent class="p-4">
                                <Badge v-if="related.category" variant="secondary" class="mb-2">{{ related.category.name }}</Badge>
                                <h3 class="font-semibold group-hover:text-primary transition-colors">{{ related.title }}</h3>
                                <p v-if="related.excerpt" class="mt-1 text-sm text-muted-foreground line-clamp-2">{{ related.excerpt }}</p>
                                <p v-if="related.published_at" class="mt-2 text-xs text-muted-foreground">{{ formatDate(related.published_at) }}</p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
