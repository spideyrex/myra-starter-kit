<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import TemplatePicker from '@/components/admin/TemplatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ArrowDown, ArrowUp, ExternalLink, LayoutTemplate, Paintbrush } from 'lucide-vue-next';
import { routeExists } from '@/lib/routeExists';
import type { TemplateSchema } from '@/types';
import { adminPath } from '@/lib/adminPath';

// >>> MYRA v2.8 [C] START
interface PageSurfaceForm {
    page_bg_type: string;
    page_bg_color: string | null;
    page_bg_recipe: string | null;
    page_bg_scrim: string;
    page_navbar_translucent: boolean;
}

interface SurfaceOptions {
    types: string[];
    gradients: string[];
    patterns: string[];
    scrims: string[];
}
// <<< MYRA v2.8 [C] END

const props = withDefaults(defineProps<{
    templates: TemplateSchema[];
    current: string;
    sectionOrder: string[];
    sections: string[];
    sectionsEnabled: Record<string, boolean>;
    // >>> MYRA v2.8 [C] START
    // Optional on purpose: a missing surface prop must not white-screen the
    // template chooser, which shipped long before page backgrounds existed.
    surface?: PageSurfaceForm;
    surfaceOptions?: SurfaceOptions;
    // <<< MYRA v2.8 [C] END
}>(), {
    surface: () => ({
        page_bg_type: 'none',
        page_bg_color: null,
        page_bg_recipe: null,
        page_bg_scrim: 'medium',
        page_navbar_translucent: false,
    }),
    surfaceOptions: () => ({ types: [], gradients: [], patterns: [], scrims: [] }),
});

const { t } = useI18n();

const form = useForm({
    template: props.current,
    section_order: [...props.sectionOrder],
    // >>> MYRA v2.8 [C] START
    page_bg_type: props.surface.page_bg_type,
    page_bg_color: props.surface.page_bg_color ?? '#111827',
    page_bg_recipe: props.surface.page_bg_recipe ?? '',
    page_bg_scrim: props.surface.page_bg_scrim,
    page_navbar_translucent: props.surface.page_navbar_translucent,
    // <<< MYRA v2.8 [C] END
});

// >>> MYRA v2.8 [C] START
const recipeOptions = computed<string[]>(() => {
    if (form.page_bg_type === 'gradient') return props.surfaceOptions.gradients;
    if (form.page_bg_type === 'pattern') return props.surfaceOptions.patterns;

    return [];
});

/** A recipe belongs to exactly one family, so a type change re-picks one. */
watch(
    () => form.page_bg_type,
    () => {
        const options = recipeOptions.value;

        if (options.length === 0) {
            form.page_bg_recipe = '';

            return;
        }

        if (!options.includes(form.page_bg_recipe)) {
            form.page_bg_recipe = options[0];
        }
    },
);

const usesColor = computed(() => ['solid', 'gradient', 'pattern', 'image'].includes(form.page_bg_type));
const usesImage = computed(() => form.page_bg_type === 'image');
const hasSurface = computed(() => form.page_bg_type !== 'none');
// <<< MYRA v2.8 [C] END

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
    builderRouteName.value ? route(builderRouteName.value) : adminPath('landing/builder'),
);
// <<< MYRA v2.7 [D] END

function submit() {
    // >>> MYRA v2.8 [C] START
    // '' is not a valid stored recipe, and a base colour is only meaningful for
    // the types that paint one. Both go over the wire as null instead.
    form
        .transform(data => ({
            ...data,
            page_bg_recipe: data.page_bg_recipe === '' ? null : data.page_bg_recipe,
            page_bg_color: usesColor.value ? data.page_bg_color : null,
        }))
        .put(route('admin.landing.update'), { preserveScroll: true });
    // <<< MYRA v2.8 [C] END
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

            <!-- >>> MYRA v2.8 [C] START -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Paintbrush class="size-4 text-muted-foreground" aria-hidden="true" />
                        {{ t('appearancePage.background.title') }}
                    </CardTitle>
                    <CardDescription>{{ t('appearancePage.background.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="page-bg-type">{{ t('appearancePage.background.type') }}</Label>
                            <select
                                id="page-bg-type"
                                v-model="form.page_bg_type"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                :aria-describedby="'page-bg-type-help'"
                            >
                                <option v-for="type in surfaceOptions.types" :key="type" :value="type">
                                    {{ t(`appearancePage.background.types.${type}`) }}
                                </option>
                            </select>
                            <p id="page-bg-type-help" class="text-xs text-muted-foreground">
                                {{ t('appearancePage.background.typeHelp') }}
                            </p>
                        </div>

                        <div v-if="recipeOptions.length" class="space-y-2">
                            <Label for="page-bg-recipe">{{ t('appearancePage.background.recipe') }}</Label>
                            <select
                                id="page-bg-recipe"
                                v-model="form.page_bg_recipe"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option v-for="recipe in recipeOptions" :key="recipe" :value="recipe">
                                    {{ t(`appearancePage.background.recipes.${recipe}`) }}
                                </option>
                            </select>
                        </div>

                        <div v-if="usesColor" class="space-y-2">
                            <Label for="page-bg-color">{{ t('appearancePage.background.color') }}</Label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="page-bg-color"
                                    v-model="form.page_bg_color"
                                    type="color"
                                    class="h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-background"
                                    :aria-describedby="'page-bg-color-help'"
                                />
                                <Input v-model="form.page_bg_color" class="font-mono" spellcheck="false" />
                            </div>
                            <p id="page-bg-color-help" class="text-xs text-muted-foreground">
                                {{ t('appearancePage.background.colorHelp') }}
                            </p>
                        </div>

                        <div v-if="usesImage" class="space-y-2">
                            <Label for="page-bg-scrim">{{ t('appearancePage.background.scrim') }}</Label>
                            <select
                                id="page-bg-scrim"
                                v-model="form.page_bg_scrim"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                :aria-describedby="'page-bg-scrim-help'"
                            >
                                <option v-for="scrim in surfaceOptions.scrims" :key="scrim" :value="scrim">
                                    {{ t(`appearancePage.background.scrims.${scrim}`) }}
                                </option>
                            </select>
                            <p id="page-bg-scrim-help" class="text-xs text-muted-foreground">
                                {{ t('appearancePage.background.scrimHelp') }}
                            </p>
                        </div>
                    </div>

                    <p v-if="usesImage" class="text-xs text-muted-foreground">
                        {{ t('appearancePage.background.imageNote') }}
                    </p>

                    <div v-if="hasSurface" class="flex items-start justify-between gap-4 rounded-md border p-3">
                        <div class="min-w-0">
                            <Label for="page-navbar-translucent">{{ t('appearancePage.background.navbar') }}</Label>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('appearancePage.background.navbarHelp') }}
                            </p>
                        </div>
                        <Switch id="page-navbar-translucent" v-model="form.page_navbar_translucent" />
                    </div>
                </CardContent>
            </Card>
            <!-- <<< MYRA v2.8 [C] END -->

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
