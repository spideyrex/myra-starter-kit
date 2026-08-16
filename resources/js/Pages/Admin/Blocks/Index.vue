<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ArrowRight, Info, SearchX } from 'lucide-vue-next';
import { useDemoSearch } from '@/composables/useDemoSearch';
import type { DemoEntry } from '@/demo/registry';
import type { BlockSchema } from '@/types';

const props = withDefaults(defineProps<{ blocks?: BlockSchema[]; categories?: string[] }>(), {
    blocks: () => [],
    categories: () => [],
});

const { t, te } = useI18n();

const ALL = '__all__';
const category = ref(ALL);

const byKey = computed(() => new Map(props.blocks.map(block => [block.key, block])));

/** The gap notice reads off the payload, so it can never outlive the gap it describes. */
const hasSourceOnly = computed(() => props.blocks.some(block => block.available === false));

const visible = computed(() =>
    category.value === ALL ? props.blocks : props.blocks.filter(block => block.category === category.value),
);

/** Adapted to the gallery's entry shape so search, highlighting and i18n are shared, not re-implemented. */
const searchable = computed<DemoEntry[]>(() =>
    visible.value.map(block => ({
        key: block.key,
        titleKey: block.titleKey,
        descriptionKey: block.descriptionKey,
        route: 'admin.blocks.show',
        icon: 'LayoutGrid',
        badgeKey: null,
        tags: block.tags,
        since: block.since,
    })),
);

const { query, results } = useDemoSearch(searchable);

const counts = computed(() => {
    const out: Record<string, number> = {};
    for (const block of props.blocks) out[block.category] = (out[block.category] ?? 0) + 1;
    return out;
});

function categoryLabel(slug: string): string {
    return te(`blocks.categories.${slug}`) ? t(`blocks.categories.${slug}`) : slug;
}

function href(key: string): string | null {
    try {
        return route('admin.blocks.show', key) as string;
    } catch {
        return null;
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('blocks.title') }]">
        <Head :title="t('blocks.title')" />

        <PageHeader :title="t('blocks.title')" :description="t('blocks.description')" />

        <Alert class="mt-6">
            <Info class="size-4" aria-hidden="true" />
            <AlertTitle>{{ t('blocks.gaps.title') }}</AlertTitle>
            <AlertDescription>
                <span class="block">{{ t('blocks.gaps.calendar') }}</span>
                <span v-if="hasSourceOnly" class="block">{{ t('blocks.gaps.sourceOnly') }}</span>
            </AlertDescription>
        </Alert>

        <div class="mt-6 max-w-md space-y-1.5">
            <Label for="blocks-search">{{ t('blocks.search') }}</Label>
            <Input id="blocks-search" v-model="query" type="search" autocomplete="off" :placeholder="t('blocks.search')" />
        </div>

        <Tabs :model-value="category" class="mt-4" @update:model-value="category = String($event)">
            <TabsList class="flex-wrap">
                <TabsTrigger :value="ALL">
                    {{ t('blocks.allCategories') }}
                    <span class="ml-1 text-xs opacity-70">{{ blocks.length }}</span>
                </TabsTrigger>
                <TabsTrigger v-for="slug in categories" :key="slug" :value="slug">
                    {{ categoryLabel(slug) }}
                    <span class="ml-1 text-xs opacity-70">{{ counts[slug] ?? 0 }}</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ t('blocks.resultCount', { n: results.length }) }}
        </p>

        <div v-if="results.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card
                v-for="block in results"
                :key="block.key"
                class="group relative overflow-hidden transition-all hover:border-primary/50 hover:shadow-md"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <Badge variant="outline" class="text-xs">
                            {{ categoryLabel(byKey.get(block.key)?.category ?? '') }}
                        </Badge>
                        <Badge v-if="byKey.get(block.key)?.available === false" variant="secondary" class="text-xs">
                            {{ t('blocks.unavailable.badge') }}
                        </Badge>
                    </div>
                    <CardTitle class="mt-3 text-base">
                        <SearchHighlight :text="block.title" :matches="block.matches" field="title" />
                    </CardTitle>
                    <CardDescription class="text-sm">
                        <SearchHighlight :text="block.description" :matches="block.matches" field="description" />
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex items-center justify-between gap-2 pt-0">
                    <Button variant="ghost" size="sm" class="group/btn -ml-2 text-primary" as-child>
                        <Link :href="href(block.key) ?? '#'">
                            {{ t('blocks.view') }}
                            <ArrowRight class="ml-1 size-4 transition-transform group-hover/btn:translate-x-1" />
                        </Link>
                    </Button>
                    <span class="text-xs text-muted-foreground">{{ t('gallery.since', { version: block.since }) }}</span>
                </CardContent>
            </Card>
        </div>

        <EmptyState v-else class="mt-6" :title="t('blocks.noResults')" :icon="SearchX" />
    </AuthenticatedLayout>
</template>
