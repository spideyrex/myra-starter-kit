<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemSeparator, ItemTitle } from '@/components/ui/item';
import { FolderOpen, PackageOpen, Trash2 } from 'lucide-vue-next';

interface Project {
    id: string;
    name: string;
    meta: string;
    size: string;
    status: string;
}

const props = withDefaults(defineProps<{ projects?: Project[] }>(), { projects: () => [] });

const { t } = useI18n();

const removed = ref<string[]>([]);

const visible = computed(() => props.projects.filter(p => !removed.value.includes(p.id)));

function remove(id: string) {
    removed.value = [...removed.value, id];
}

function restore() {
    removed.value = [];
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.emptyAndItem.title') }]"
    >
        <Head :title="t('gallery.demos.emptyAndItem.title')" />

        <PageHeader
            :title="t('gallery.demos.emptyAndItem.title')"
            :description="t('gallery.demos.emptyAndItem.description')"
        >
            <template #actions>
                <Button variant="outline" :disabled="removed.length === 0" @click="restore">
                    {{ t('gallery.componentDemos.emptyAndItem.restore') }}
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.emptyAndItem.listTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.emptyAndItem.listDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ItemGroup v-if="visible.length" class="rounded-md border">
                        <template v-for="(project, index) in visible" :key="project.id">
                            <ItemSeparator v-if="index > 0" />
                            <Item>
                                <ItemMedia variant="icon">
                                    <FolderOpen aria-hidden="true" />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>{{ project.name }}</ItemTitle>
                                    <ItemDescription>{{ project.meta }} · {{ project.size }}</ItemDescription>
                                </ItemContent>
                                <ItemActions>
                                    <Badge variant="secondary">
                                        {{ t(`gallery.componentDemos.emptyAndItem.status.${project.status}`) }}
                                    </Badge>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="t('gallery.componentDemos.emptyAndItem.remove', { name: project.name })"
                                        @click="remove(project.id)"
                                    >
                                        <Trash2 class="size-4" aria-hidden="true" />
                                    </Button>
                                </ItemActions>
                            </Item>
                        </template>
                    </ItemGroup>

                    <Empty v-else class="border border-dashed">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PackageOpen aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('gallery.componentDemos.emptyAndItem.emptyTitle') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('gallery.componentDemos.emptyAndItem.emptyDescription') }}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button @click="restore">{{ t('gallery.componentDemos.emptyAndItem.restore') }}</Button>
                        </EmptyContent>
                    </Empty>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.emptyAndItem.variantsTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.emptyAndItem.variantsDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Item variant="outline">
                        <ItemContent>
                            <ItemTitle>{{ t('gallery.componentDemos.emptyAndItem.variant.outline') }}</ItemTitle>
                            <ItemDescription>{{ t('gallery.componentDemos.emptyAndItem.variantsDescription') }}</ItemDescription>
                        </ItemContent>
                    </Item>
                    <Item variant="muted" size="sm">
                        <ItemContent>
                            <ItemTitle>{{ t('gallery.componentDemos.emptyAndItem.variant.muted') }}</ItemTitle>
                        </ItemContent>
                    </Item>

                    <Empty class="border border-dashed">
                        <EmptyHeader>
                            <EmptyTitle>{{ t('gallery.componentDemos.emptyAndItem.bareEmptyTitle') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('gallery.componentDemos.emptyAndItem.bareEmptyDescription') }}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
