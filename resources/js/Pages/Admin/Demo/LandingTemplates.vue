<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import TemplatePicker from '@/components/admin/TemplatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ExternalLink } from 'lucide-vue-next';
import { routeExists } from '@/lib/routeExists';
import type { TemplateSchema } from '@/types';

const props = withDefaults(defineProps<{ templates?: TemplateSchema[] }>(), { templates: () => [] });

const { t } = useI18n();

const previewed = ref(props.templates[0]?.key ?? 'classic');

const hasLandingPage = computed(() => routeExists('admin.landing.index'));
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.landingTemplates.title') }]"
    >
        <Head :title="t('gallery.demos.landingTemplates.title')" />

        <PageHeader
            :title="t('gallery.demos.landingTemplates.title')"
            :description="t('gallery.demos.landingTemplates.description')"
        >
            <template #actions>
                <Button v-if="hasLandingPage" variant="outline" as-child>
                    <Link :href="route('admin.landing.index')">{{ t('landing.title') }}</Link>
                </Button>
                <a
                    :href="`/?template=${encodeURIComponent(previewed)}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ t('landing.preview.openInNewTab') }}
                </a>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('landing.picker.label') }}</CardTitle>
                    <CardDescription>{{ t('landing.picker.help') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <TemplatePicker v-model="previewed" :templates="props.templates" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('landing.sections.label') }}</CardTitle>
                    <CardDescription>{{ t('landing.unsupported.help') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul role="list" class="divide-y">
                        <li
                            v-for="template in props.templates"
                            :key="template.key"
                            class="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ t(template.titleKey) }}</p>
                                <p class="text-xs text-muted-foreground">{{ t(template.descriptionKey) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="section in template.supports" :key="section" variant="secondary">
                                    {{ t(`landing.sections.${section}`) }}
                                </Badge>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
