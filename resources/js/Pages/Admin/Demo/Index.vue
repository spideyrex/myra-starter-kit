<script setup lang="ts">
import { computed } from 'vue';
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
import { ArrowRight, SearchX } from 'lucide-vue-next';
import { resolveDemoIcon, type DemoEntry } from '@/demo/registry';
import { useDemoSearch } from '@/composables/useDemoSearch';
// >>> MYRA v2.6 [D] START
import { routeExists } from '@/lib/routeExists';
// <<< MYRA v2.6 [D] END

const props = withDefaults(defineProps<{ demos?: DemoEntry[] }>(), { demos: () => [] });

const { t, te } = useI18n();

/**
 * FAIL-SOFT: if the registry failed to seed at boot the server sends `[]`, and
 * the gallery falls back to the routes it can still name. This page must never
 * render blank.
 */
const FALLBACK: DemoEntry[] = [
    { key: 'formBuilder', titleKey: 'gallery.demos.formBuilder.title', descriptionKey: 'gallery.demos.formBuilder.description', route: 'admin.demo.form-builder', icon: 'FormInput', badgeKey: 'gallery.demos.formBuilder.badge', tags: ['forms'], since: '1.0.0' },
    { key: 'bulkActions', titleKey: 'gallery.demos.bulkActions.title', descriptionKey: 'gallery.demos.bulkActions.description', route: 'admin.demo.bulk-actions', icon: 'CheckSquare', badgeKey: 'gallery.demos.bulkActions.badge', tags: ['tables'], since: '1.0.0' },
    { key: 'globalSearch', titleKey: 'gallery.demos.globalSearch.title', descriptionKey: 'gallery.demos.globalSearch.description', route: 'admin.demo.global-search', icon: 'Search', badgeKey: 'gallery.demos.globalSearch.badge', tags: ['navigation'], since: '1.0.0' },
];

const entries = computed<DemoEntry[]>(() => (props.demos.length > 0 ? props.demos : FALLBACK));

const { query, tag, tags, results } = useDemoSearch(entries);

function href(entry: DemoEntry): string | null {
    try {
        return route(entry.route) as string;
    } catch {
        return null;
    }
}

function badgeLabel(entry: DemoEntry): string | null {
    if (!entry.badgeKey) return null;

    return te(entry.badgeKey) ? t(entry.badgeKey) : null;
}

// >>> MYRA v2.6 [D] START
/**
 * Cross-bundle discoverability. Each reference catalogue is shipped by a
 * different bundle, so a link is rendered only when that bundle's route
 * actually exists — an isolated worktree simply shows fewer links.
 */
const REFERENCES = [
    { name: 'admin.blocks.index', labelKey: 'gallery.references.blocks' },
    { name: 'admin.examples.index', labelKey: 'gallery.references.examples' },
    { name: 'admin.brand.index', labelKey: 'gallery.references.brand' },
    { name: 'admin.landing.index', labelKey: 'gallery.references.landing' },
] as const;

const references = computed(() =>
    REFERENCES.filter(entry => routeExists(entry.name) && te(entry.labelKey)),
);
// <<< MYRA v2.6 [D] END
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.title') }]">
        <Head :title="t('gallery.title')" />

        <PageHeader :title="t('gallery.title')" :description="t('gallery.subtitle')" />

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1 space-y-1.5">
                <Label for="gallery-search">{{ t('gallery.search') }}</Label>
                <Input
                    id="gallery-search"
                    v-model="query"
                    type="search"
                    autocomplete="off"
                    :placeholder="t('gallery.search')"
                />
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2" role="group" :aria-label="t('gallery.allTags')">
            <Button
                type="button"
                size="sm"
                :variant="tag === null ? 'default' : 'outline'"
                :aria-pressed="tag === null"
                @click="tag = null"
            >
                {{ t('gallery.allTags') }}
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
                {{ te(`gallery.tags.${entry.id}`) ? t(`gallery.tags.${entry.id}`) : entry.id }}
                <span class="ml-1 text-xs opacity-70">{{ entry.count }}</span>
            </Button>
        </div>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ t('gallery.resultCount', { n: results.length }) }}
        </p>

        <!-- >>> MYRA v2.6 [D] START -->
        <nav
            v-if="references.length"
            class="mt-4 flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 p-3"
            :aria-label="t('gallery.references.label')"
        >
            <span class="text-xs font-medium text-muted-foreground">{{ t('gallery.references.label') }}</span>
            <Link
                v-for="reference in references"
                :key="reference.name"
                :href="route(reference.name)"
                class="rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:border-primary/50"
            >
                {{ t(reference.labelKey) }}
            </Link>
        </nav>
        <!-- <<< MYRA v2.6 [D] END -->

        <div v-if="results.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card
                v-for="demo in results"
                :key="demo.key"
                class="group relative overflow-hidden transition-all hover:border-primary/50 hover:shadow-md"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <!-- Icons resolve by NAME through one allow-list map, not 27 inline imports. -->
                            <component :is="resolveDemoIcon(demo.icon)" class="size-5" aria-hidden="true" />
                        </div>
                        <Badge v-if="badgeLabel(demo)" variant="secondary" class="text-xs">
                            {{ badgeLabel(demo) }}
                        </Badge>
                    </div>
                    <CardTitle class="mt-3 text-base">
                        <SearchHighlight :text="demo.title" :matches="demo.matches" field="title" />
                    </CardTitle>
                    <CardDescription class="text-sm">
                        <SearchHighlight :text="demo.description" :matches="demo.matches" field="description" />
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex items-center justify-between gap-2 pt-0">
                    <Button variant="ghost" size="sm" class="group/btn -ml-2 text-primary" as-child>
                        <Link :href="href(demo) ?? '#'">
                            {{ t('gallery.viewDemo') }}
                            <ArrowRight class="ml-1 size-4 transition-transform group-hover/btn:translate-x-1" />
                        </Link>
                    </Button>
                    <span class="text-xs text-muted-foreground">{{ t('gallery.since', { version: demo.since }) }}</span>
                </CardContent>
            </Card>
        </div>

        <EmptyState v-else class="mt-6" :title="t('gallery.noResults')" :icon="SearchX" />
    </AuthenticatedLayout>
</template>
