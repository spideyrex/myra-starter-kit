<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft } from 'lucide-vue-next';
import PlaygroundPanel from '@/components/admin/playground/PlaygroundPanel.vue';
import { useCommandScope, type Command } from '@/composables/useCommandRegistry';
import statCard from '@/demo/playgrounds/statCard';
import button from '@/demo/playgrounds/button';
import badge from '@/demo/playgrounds/badge';
import emptyState from '@/demo/playgrounds/emptyState';

const props = withDefaults(defineProps<{ playgroundsEnabled?: boolean }>(), { playgroundsEnabled: true });

const { t } = useI18n();

const specs = [statCard, button, badge, emptyState];
const active = ref(specs[0].key);

// Page-scoped commands: they leave the palette when the page does.
useCommandScope(computed<Command[]>(() =>
    props.playgroundsEnabled
        ? specs.map(spec => ({
            id: `playground:${spec.key}`,
            titleKey: spec.titleKey,
            groupKey: 'gallery.commands.group.demo',
            keywords: ['playground', spec.key],
            run: () => { active.value = spec.key; },
        }))
        : [],
));
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('gallery.title') }, { label: t('gallery.playground.title') }]">
        <Head :title="t('gallery.playground.title')" />

        <PageHeader :title="t('gallery.playground.title')" :description="t('gallery.playground.description')">
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        {{ t('gallery.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card v-if="!playgroundsEnabled" class="mt-6">
            <CardHeader>
                <CardTitle class="text-base">{{ t('gallery.playground.disabledTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-muted-foreground">
                <p>{{ t('gallery.playground.disabledDescription') }}</p>
                <code class="inline-block rounded bg-muted px-2 py-1 font-mono text-xs">MYRA_GALLERY_PLAYGROUNDS=true</code>
            </CardContent>
        </Card>

        <Tabs v-else v-model="active" class="mt-6">
            <TabsList class="flex-wrap">
                <TabsTrigger v-for="spec in specs" :key="spec.key" :value="spec.key">
                    {{ t(spec.titleKey) }}
                </TabsTrigger>
            </TabsList>

            <TabsContent v-for="spec in specs" :key="spec.key" :value="spec.key" class="mt-4">
                <PlaygroundPanel :spec="spec" />
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
