<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import ExampleSourceTabs from '@/components/admin/examples/ExampleSourceTabs.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AlertTriangle, ExternalLink } from 'lucide-vue-next';
import type { ExampleEntry, ExampleSourceFile } from '@/types';

const props = defineProps<{ example: ExampleEntry; source: ExampleSourceFile[] }>();

const { t } = useI18n();

const dark = ref(false);
const reduced = ref(false);

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
    reduced.value = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
});

const title = computed(() => t(props.example.titleKey));

/** The parent's appearance travels into the frame on the URL, not by osmosis. */
const previewUrl = computed<string>(() => {
    try {
        const base = route('admin.examples.preview', props.example.key) as string;
        const query = new URLSearchParams();
        if (dark.value) query.set('dark', '1');
        if (reduced.value) query.set('reduced', '1');
        const suffix = query.toString();

        return suffix === '' ? base : `${base}?${suffix}`;
    } catch {
        return '';
    }
});

const indexUrl = computed<string>(() => {
    try {
        return (route('admin.examples.index') as string) ?? '';
    } catch {
        return '';
    }
});
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('navGroups.demo') },
            { label: t('examples.title'), href: indexUrl || undefined },
            { label: title },
        ]"
    >
        <Head :title="title" />

        <PageHeader :title="title" :description="t(example.descriptionKey)">
            <template #badge>
                <Badge variant="secondary">{{ t(`examples.origin.${example.origin}`) }}</Badge>
            </template>
            <template #actions>
                <Button v-if="indexUrl" as-child variant="outline" size="sm">
                    <Link :href="indexUrl">{{ t('examples.back') }}</Link>
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="!example.available" variant="destructive" class="mt-6">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>{{ t('examples.unavailable.title') }}</AlertTitle>
            <AlertDescription>
                {{ t('examples.unavailable.body', { deps: example.unavailableReason ?? '' }) }}
            </AlertDescription>
        </Alert>

        <Tabs default-value="preview" class="mt-6">
            <TabsList>
                <TabsTrigger value="preview">{{ t('examples.preview.tab') }}</TabsTrigger>
                <TabsTrigger value="code">{{ t('examples.source.tab') }}</TabsTrigger>
                <TabsTrigger value="dependencies">{{ t('examples.dependencies.tab') }}</TabsTrigger>
            </TabsList>

            <TabsContent value="preview" class="mt-4 space-y-3">
                <template v-if="example.available && previewUrl">
                    <!-- Same-origin frame: the shell mounts its own layout, so it
                         must not nest inside the admin's. -->
                    <div class="overflow-hidden rounded-lg border">
                        <iframe
                            :src="previewUrl"
                            :title="t('examples.preview.frameTitle', { name: title })"
                            loading="lazy"
                            class="h-[70vh] w-full bg-background"
                        />
                    </div>

                    <a
                        :href="previewUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1 text-sm underline underline-offset-4"
                    >
                        {{ t('examples.preview.openInNewTab') }}
                        <ExternalLink class="size-3.5" aria-hidden="true" />
                    </a>
                </template>

                <p v-else class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                    {{ t('examples.unavailable.body', { deps: example.unavailableReason ?? '' }) }}
                </p>
            </TabsContent>

            <TabsContent value="code" class="mt-4">
                <ExampleSourceTabs :files="source" />
            </TabsContent>

            <TabsContent value="dependencies" class="mt-4 space-y-4">
                <section>
                    <h2 class="text-sm font-medium">{{ t('examples.dependencies.registry') }}</h2>
                    <Separator class="my-2" />
                    <ul role="list" class="flex flex-wrap gap-1">
                        <li v-for="dep in example.registryDependencies" :key="dep">
                            <Badge variant="outline" class="font-mono text-xs">{{ dep }}</Badge>
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-sm font-medium">{{ t('examples.dependencies.npm') }}</h2>
                    <Separator class="my-2" />
                    <ul role="list" class="flex flex-wrap gap-1">
                        <li v-for="dep in example.npmDependencies" :key="dep">
                            <Badge variant="outline" class="font-mono text-xs">{{ dep }}</Badge>
                        </li>
                    </ul>
                </section>

                <p v-if="example.sourceRef" class="text-xs text-muted-foreground">
                    {{ t('examples.dependencies.pinned', { sha: (example.sourceRef ?? '').slice(0, 12) }) }}
                </p>
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
