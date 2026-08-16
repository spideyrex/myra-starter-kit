<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowRight, SearchX } from 'lucide-vue-next';
import { useDemoSearch } from '@/composables/useDemoSearch';
import type { DemoEntry } from '@/demo/registry';
import type { ExampleEntry } from '@/types';

const props = withDefaults(defineProps<{ examples?: ExampleEntry[] }>(), { examples: () => [] });

const { t } = useI18n();

const byKey = computed(() => new Map(props.examples.map(entry => [entry.key, entry])));

/** useDemoSearch is registry-shaped; the example payload adopts that shape. */
const searchable = computed<DemoEntry[]>(() => props.examples.map(entry => ({
    key: entry.key,
    titleKey: entry.titleKey,
    descriptionKey: entry.descriptionKey,
    route: 'admin.examples.show',
    icon: 'LayoutGrid',
    tags: entry.tags,
    since: entry.since,
})));

const { query, tag, tags, results } = useDemoSearch(searchable);

/** Empty when Ziggy has no such route, so the card degrades instead of throwing. */
function href(key: string): string {
    try {
        return (route('admin.examples.show', key) as string) ?? '';
    } catch {
        return '';
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('examples.title') }]">
        <Head :title="t('examples.title')" />

        <PageHeader :title="t('examples.title')" :description="t('examples.description')" />

        <div class="mt-6 max-w-md space-y-1.5">
            <Label for="examples-search">{{ t('examples.search') }}</Label>
            <Input
                id="examples-search"
                v-model="query"
                type="search"
                autocomplete="off"
                :placeholder="t('examples.search')"
            />
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2" role="group" :aria-label="t('examples.allTags')">
            <Button
                type="button"
                size="sm"
                :variant="tag === null ? 'default' : 'outline'"
                :aria-pressed="tag === null"
                @click="tag = null"
            >
                {{ t('examples.allTags') }}
            </Button>
            <Button
                v-for="entry in tags"
                :key="entry.id"
                type="button"
                size="sm"
                :variant="tag === entry.id ? 'default' : 'outline'"
                :aria-pressed="tag === entry.id"
                @click="tag = tag === entry.id ? null : entry.id"
            >
                {{ entry.id }}
                <span class="ml-1 text-xs opacity-70">{{ entry.count }}</span>
            </Button>
        </div>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ t('examples.resultCount', { n: results.length }) }}
        </p>

        <p class="mt-6 text-sm text-muted-foreground">{{ t('examples.gaps.note') }}</p>

        <div v-if="results.length" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card v-for="item in results" :key="item.key" class="flex flex-col">
                <CardHeader>
                    <div class="flex items-start gap-2">
                        <CardTitle class="text-base">
                            <SearchHighlight :text="item.title" :matches="item.matches" field="title" />
                        </CardTitle>
                        <Badge variant="secondary" class="ml-auto shrink-0">
                            {{ t(`examples.origin.${byKey.get(item.key)?.origin ?? 'fetched'}`) }}
                        </Badge>
                    </div>
                    <CardDescription>
                        <SearchHighlight :text="item.description" :matches="item.matches" field="description" />
                    </CardDescription>
                </CardHeader>

                <CardContent class="mt-auto space-y-3">
                    <p v-if="!byKey.get(item.key)?.available" class="text-xs text-muted-foreground">
                        {{ t('examples.unavailable.body', { deps: byKey.get(item.key)?.unavailableReason ?? '' }) }}
                    </p>

                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="surface in byKey.get(item.key)?.surfaces ?? []" :key="surface" variant="outline">
                            {{ t(`examples.surfaces.${surface}`) }}
                        </Badge>
                    </div>

                    <Button v-if="href(item.key)" as-child variant="outline" size="sm" class="w-full">
                        <Link :href="href(item.key)">
                            {{ t('examples.view') }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>

        <div v-else class="mt-4 flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center">
            <SearchX class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm text-muted-foreground">{{ t('examples.noResults') }}</p>
        </div>
    </AuthenticatedLayout>
</template>
