<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import BlockViewportBar from '@/components/admin/blocks/BlockViewportBar.vue';
import BlockSourceTabs from '@/components/admin/blocks/BlockSourceTabs.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { ExternalLink, PackageX } from 'lucide-vue-next';
import type { BlockSchema, BlockSourceFile } from '@/types';

const props = withDefaults(defineProps<{
    block: BlockSchema;
    source?: BlockSourceFile[];
    viewports?: string[];
}>(), {
    source: () => [],
    viewports: () => ['full', '1024', '768', '375'],
});

const { t, te } = useI18n();

const viewport = ref(props.block.viewport ?? 'full');
const dark = ref(false);
const reduced = ref(false);

// The frame is a separate document: the shell's theme and motion preference
// have to be carried across explicitly or the preview lies about both.
onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
    reduced.value = typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
});

const title = computed(() => (te(props.block.titleKey) ? t(props.block.titleKey) : props.block.key));
const description = computed(() => (te(props.block.descriptionKey) ? t(props.block.descriptionKey) : ''));

const previewUrl = computed(() => {
    try {
        const base = route('admin.blocks.preview', props.block.key) as string;
        const params = new URLSearchParams();
        if (dark.value) params.set('dark', '1');
        if (reduced.value) params.set('reduced', '1');
        const query = params.toString();
        return query ? `${base}?${query}` : base;
    } catch {
        return '';
    }
});

const frameStyle = computed(() => (viewport.value === 'full' ? { width: '100%' } : { width: `${viewport.value}px` }));

const dependencyList = computed(() => ({
    registry: props.block.registryDependencies ?? [],
    npm: props.block.npmDependencies ?? [],
}));
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('navGroups.demo') },
            { label: t('blocks.title'), href: route('admin.blocks.index') },
            { label: title },
        ]"
    >
        <Head :title="title" />

        <PageHeader :title="title" :description="description">
            <template #badge>
                <Badge variant="outline">{{ te(`blocks.categories.${block.category}`) ? t(`blocks.categories.${block.category}`) : block.category }}</Badge>
            </template>
            <template #actions>
                <Button v-if="block.available && previewUrl" variant="outline" as-child>
                    <a :href="previewUrl" target="_blank" rel="noopener">
                        <ExternalLink class="mr-1 size-4" aria-hidden="true" />
                        {{ t('blocks.preview.openInNewTab') }}
                    </a>
                </Button>
            </template>
        </PageHeader>

        <Tabs default-value="preview" class="mt-6">
            <TabsList>
                <TabsTrigger value="preview">{{ t('blocks.preview.tab') }}</TabsTrigger>
                <TabsTrigger value="code">{{ t('blocks.source.tab') }}</TabsTrigger>
                <TabsTrigger value="dependencies">{{ t('blocks.dependencies.tab') }}</TabsTrigger>
            </TabsList>

            <TabsContent value="preview" class="space-y-4">
                <template v-if="block.available">
                    <BlockViewportBar
                        v-model="viewport"
                        v-model:dark="dark"
                        :viewports="viewports"
                    />

                    <div class="flex justify-center overflow-x-auto rounded-lg border bg-muted/30 p-2">
                        <iframe
                            :src="previewUrl"
                            :title="t('blocks.preview.frameTitle', { name: title })"
                            :style="frameStyle"
                            class="h-[42rem] max-w-full rounded-md border bg-background"
                            loading="lazy"
                        />
                    </div>
                </template>

                <Empty v-else class="border">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <PackageX class="size-6" aria-hidden="true" />
                        </EmptyMedia>
                        <EmptyTitle>{{ t('blocks.unavailable.title') }}</EmptyTitle>
                        <EmptyDescription>
                            {{ t('blocks.unavailable.body', { deps: block.unavailableReason ?? '' }) }}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </TabsContent>

            <TabsContent value="code">
                <BlockSourceTabs :files="source" />
            </TabsContent>

            <TabsContent value="dependencies" class="space-y-4">
                <section>
                    <h2 class="text-sm font-semibold">{{ t('blocks.dependencies.registry') }}</h2>
                    <ul v-if="dependencyList.registry.length" class="mt-2 flex flex-wrap gap-2">
                        <li v-for="dep in dependencyList.registry" :key="dep">
                            <Badge variant="secondary">{{ dep }}</Badge>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">{{ t('blocks.dependencies.none') }}</p>
                </section>

                <section>
                    <h2 class="text-sm font-semibold">{{ t('blocks.dependencies.npm') }}</h2>
                    <ul v-if="dependencyList.npm.length" class="mt-2 flex flex-wrap gap-2">
                        <li v-for="dep in dependencyList.npm" :key="dep">
                            <Badge variant="outline">{{ dep }}</Badge>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">{{ t('blocks.dependencies.none') }}</p>
                </section>
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
