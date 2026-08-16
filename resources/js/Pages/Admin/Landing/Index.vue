<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import TemplatePicker from '@/components/admin/TemplatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowDown, ArrowUp, ExternalLink, LayoutTemplate } from 'lucide-vue-next';
import { routeExists } from '@/lib/routeExists';
import type { TemplateSchema } from '@/types';

const props = defineProps<{
    templates: TemplateSchema[];
    current: string;
    sectionOrder: string[];
    sections: string[];
    sectionsEnabled: Record<string, boolean>;
}>();

const { t } = useI18n();

const form = useForm({
    template: props.current,
    section_order: [...props.sectionOrder],
});

/** Announced after a move; read by the aria-live region below. */
const announcement = ref('');

const selected = computed<TemplateSchema | undefined>(() =>
    props.templates.find(item => item.key === form.template),
);

function supported(section: string): boolean {
    return selected.value?.supports.includes(section) ?? true;
}

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= form.section_order.length) return;

    const next = [...form.section_order];
    [next[index], next[target]] = [next[target], next[index]];
    form.section_order = next;

    announcement.value = t('landing.sectionOrder.reordered', {
        section: t(`landing.sections.${next[target]}`),
        position: target + 1,
        total: next.length,
    });
}

const previewUrl = computed(() => `/?template=${encodeURIComponent(form.template)}`);

// >>> MYRA v2.7 [D] START
// The builder ships with the editor bundle. Ziggy is asked for the name first so
// a renamed route still resolves; the path is the spec's, and is the fallback.
const builderRouteName = computed(() =>
    ['admin.landing.builder', 'admin.landing.builder.index'].find(routeExists) ?? null,
);

const builderHref = computed(() =>
    builderRouteName.value ? route(builderRouteName.value) : '/admin/landing/builder',
);
// <<< MYRA v2.7 [D] END

function submit() {
    form.put(route('admin.landing.update'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.system') }, { label: t('landing.title') }]">
        <Head :title="t('landing.title')" />

        <PageHeader :title="t('landing.title')" :description="t('landing.description')">
            <template #actions>
                <a
                    :href="previewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ t('landing.preview.openInNewTab') }}
                </a>
            </template>
        </PageHeader>

        <!-- >>> MYRA v2.7 [D] START -->
        <Card class="mt-6">
            <CardHeader>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <LayoutTemplate class="size-4 text-muted-foreground" aria-hidden="true" />
                            {{ t('landing.builder.title') }}
                            <Badge variant="secondary">{{ t('landing.builder.badge') }}</Badge>
                        </CardTitle>
                        <CardDescription class="mt-1">{{ t('landing.builder.description') }}</CardDescription>
                    </div>
                    <Button as-child>
                        <Link :href="builderHref">{{ t('landing.builder.open') }}</Link>
                    </Button>
                </div>
            </CardHeader>
        </Card>
        <!-- <<< MYRA v2.7 [D] END -->

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('landing.picker.label') }}</CardTitle>
                    <CardDescription>{{ t('landing.picker.help') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <TemplatePicker v-model="form.template" :templates="templates" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('landing.sectionOrder.label') }}</CardTitle>
                    <CardDescription>{{ t('landing.sectionOrder.help') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li
                            v-for="(section, index) in form.section_order"
                            :key="section"
                            class="flex items-center justify-between gap-3 rounded-md border p-3"
                            :class="supported(section) ? '' : 'opacity-60'"
                            :aria-disabled="!supported(section)"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ t(`landing.sections.${section}`) }}</p>
                                <p v-if="!supported(section)" class="text-xs text-muted-foreground">
                                    {{ t('landing.unsupported.help') }}
                                </p>
                                <p v-else-if="sectionsEnabled[section] === false" class="text-xs text-muted-foreground">
                                    {{ t('landing.unsupported.disabled') }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :disabled="index === 0"
                                    :aria-label="t('landing.sectionOrder.moveUp', { section: t(`landing.sections.${section}`) })"
                                    @click="move(index, -1)"
                                >
                                    <ArrowUp class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :disabled="index === form.section_order.length - 1"
                                    :aria-label="t('landing.sectionOrder.moveDown', { section: t(`landing.sections.${section}`) })"
                                    @click="move(index, 1)"
                                >
                                    <ArrowDown class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
