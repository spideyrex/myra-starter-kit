<script setup lang="ts">
import { defineAsyncComponent, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import {
    ArrowLeft,
    Search,
    Command,
    Keyboard,
    Shield,
    ListOrdered,
    Info,
} from 'lucide-vue-next';

// Loaded on first open, not on page load.
const CommandPalette = defineAsyncComponent(() => import('@/components/admin/CommandPalette.vue'));

const { t } = useI18n();
const paletteMounted = ref(false);
const paletteOpen = ref(false);

function openPalette() {
    paletteMounted.value = true;
    paletteOpen.value = true;
}

const features = [
    {
        icon: ListOrdered,
        title: 'Weighted ranking',
        description: 'score = max(weight × matchKind) + recency boost. Exact beats prefix beats word-boundary beats substring — lowest id no longer wins.',
    },
    {
        icon: Shield,
        title: 'Per-resource scoping',
        description: 'Every source declares its own ownership scope, and the OR set is always wrapped so a match can never escape it.',
    },
    {
        icon: Keyboard,
        title: 'Combobox pattern',
        description: 'role="combobox" + aria-activedescendant, arrow-key navigation, Escape returns focus to the input.',
    },
    {
        icon: Search,
        title: 'Race-safe',
        description: 'Each keystroke aborts the in-flight request; a sequence guard stops a slow early response clobbering a newer one.',
    },
];

const sampleMatches = [
    { field: 'title' as const, start: 0, length: 4 },
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Global Search' }]">
        <Head title="Global Search Demo" />

        <PageHeader title="Global Search" description="Ranked, permission-scoped command palette (Cmd+K).">
            <template #actions>
                <Button @click="openPalette">
                    <Command class="mr-2 size-4" aria-hidden="true" />
                    {{ t('search.placeholder') }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- The layout already owns Cmd+K, so the demo palette opens from the button. -->
        <component :is="CommandPalette" v-if="paletteMounted" v-model:open="paletteOpen" :bind-shortcut="false" />

        <div class="mt-6 space-y-6">
            <Alert>
                <Info class="size-4" aria-hidden="true" />
                <AlertDescription>
                    Press <kbd class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">Cmd+K</kbd> or
                    <kbd class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">Ctrl+K</kbd> to open the
                    palette. Results are grouped by source, ordered by their best match, and capped globally.
                </AlertDescription>
            </Alert>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="feature in features" :key="feature.title">
                    <CardContent class="pt-6">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="feature.icon" class="size-5" aria-hidden="true" />
                        </div>
                        <h3 class="mt-3 font-semibold">{{ feature.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ feature.description }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Highlighting without v-html</CardTitle>
                    <CardDescription>
                        The server returns offset ranges, never markup. A record whose name is hostile renders as
                        literal text with the matched run wrapped in a real &lt;mark&gt; element.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="rounded-lg border p-3 text-sm">
                        <SearchHighlight text="&lt;img src=x onerror=alert(1)&gt;" :matches="sampleMatches" />
                    </div>
                    <div class="rounded-lg border p-3 text-sm">
                        <SearchHighlight text="Alice Anderson" :matches="sampleMatches" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Registered sources</CardTitle>
                    <CardDescription>Declared in App\Admin\Search\Sources and registered from GlobalSearchServiceProvider.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                            <span class="font-medium">{{ t('search.groups.users') }}</span>
                            <Badge variant="secondary">name ×3 · email ×2 · phone ×0.5</Badge>
                            <Badge variant="outline">users.view</Badge>
                            <Badge variant="outline">owner-scoped</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                            <span class="font-medium">{{ t('search.groups.roles') }}</span>
                            <Badge variant="secondary">name ×3</Badge>
                            <Badge variant="outline">roles.view</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                            <span class="font-medium">{{ t('search.groups.activity') }}</span>
                            <Badge variant="secondary">description ×2 · subject_type ×0.5</Badge>
                            <Badge variant="outline">activity-log.view</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                            <span class="font-medium">{{ t('search.groups.pages') }}</span>
                            <Badge variant="secondary">{{ t('search.commands') }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
