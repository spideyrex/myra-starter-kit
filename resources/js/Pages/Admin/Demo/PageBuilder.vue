<script setup lang="ts">
import { computed, onErrorCaptured, onMounted, ref, shallowRef, type Component } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import BlockViewportBar from '@/components/admin/blocks/BlockViewportBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import {
    BarChart3, BookOpen, CircleHelp, Cloud, Code, CreditCard, Database, ExternalLink, Globe, Grid3x3,
    Heart, Image as ImageIcon, Layers, Layout, LayoutTemplate, Lock, Mail, Megaphone, Minus, PackageX,
    Quote, Rocket, Search, Settings as SettingsIcon, Shield, Star, TrendingUp, Type, Users, Zap,
} from 'lucide-vue-next';
import { routeExists } from '@/lib/routeExists';

/**
 * Bundle D owns the gallery page only. The section schema, the renderer and the
 * editor all ship with other bundles, so every one of them is resolved
 * defensively: a build without them shows the empty state, never a blank page.
 *
 * The types below are LOCAL COPIES on purpose — resources/js/types/index.d.ts is
 * owned by the model bundle and must not gain a second declaration here.
 */
interface SectionFieldSchema {
    name: string;
    type: string;
    labelKey: string;
    required: boolean;
    default: unknown;
    max: number | null;
    options?: string[];
    of?: SectionFieldSchema[];
}

interface SectionSchema {
    key: string;
    labelKey: string;
    descriptionKey: string;
    icon: string;
    group: string;
    titleField: string | null;
    maxPerPage: number;
    variants: Record<string, string[] | 'bool'>;
    fields: SectionFieldSchema[];
}

interface SectionRow {
    id: string;
    type: string;
    variant: Record<string, unknown>;
    data: Record<string, unknown>;
}

const props = withDefaults(defineProps<{
    sections?: SectionSchema[];
    settings?: Record<string, unknown>;
}>(), {
    sections: () => [],
    settings: () => ({}),
});

const { t, te } = useI18n();

const SECTION_ICONS: Record<string, Component> = {
    Rocket, Grid3x3, Quote, CreditCard, Megaphone,
    Type, Image: ImageIcon, TrendingUp, CircleHelp, Minus,
    BarChart3, Cloud, Code, Database, Globe, Heart, Layers, Layout, Lock,
    Mail, Search, Settings: SettingsIcon, Shield, Star, Users, Zap,
};

const GROUP_ORDER = ['content', 'proof', 'conversion', 'layout'];

function icon(name: string): Component {
    return Object.prototype.hasOwnProperty.call(SECTION_ICONS, name) ? SECTION_ICONS[name] : LayoutTemplate;
}

/** A key C has not shipped yet resolves to the type key rather than a raw path. */
function label(section: SectionSchema): string {
    return te(section.labelKey) ? t(section.labelKey) : section.key;
}

function description(section: SectionSchema): string {
    return te(section.descriptionKey) ? t(section.descriptionKey) : '';
}

function groupLabel(group: string): string {
    const key = `pageBuilder.demo.groups.${group}`;

    return te(key) ? t(key) : group;
}

const grouped = computed(() => {
    const keys = [...GROUP_ORDER, ...props.sections.map(s => s.group)];
    const seen = new Set<string>();
    const out: Array<{ group: string; sections: SectionSchema[] }> = [];

    for (const group of keys) {
        if (seen.has(group)) continue;
        seen.add(group);

        const sections = props.sections.filter(s => s.group === group);
        if (sections.length > 0) out.push({ group, sections });
    }

    return out;
});

function sample(path: string): string {
    const key = `pageBuilder.demo.samples.${path}`;

    return te(key) ? t(key) : '';
}

/** One translated example per built-in type; an unknown type gets the defaults. */
function sampleData(type: string): Record<string, unknown> {
    switch (type) {
        case 'hero':
            return {
                title: sample('hero.title'),
                subtitle: sample('hero.subtitle'),
                cta_text: sample('hero.cta_text'),
                cta_url: '#',
                image_path: '',
                image_url: null,
            };
        case 'features':
            return {
                title: sample('features.title'),
                subtitle: sample('features.subtitle'),
                items: [
                    { icon: 'Layers', title: sample('features.first.title'), description: sample('features.first.description') },
                    { icon: 'Shield', title: sample('features.second.title'), description: sample('features.second.description') },
                    { icon: 'Zap', title: sample('features.third.title'), description: sample('features.third.description') },
                ],
            };
        case 'testimonials':
            return {
                title: sample('testimonials.title'),
                subtitle: sample('testimonials.subtitle'),
                items: [
                    { name: sample('testimonials.first.name'), role: sample('testimonials.first.role'), quote: sample('testimonials.first.quote') },
                    { name: sample('testimonials.second.name'), role: sample('testimonials.second.role'), quote: sample('testimonials.second.quote') },
                ],
            };
        case 'pricing':
            return {
                title: sample('pricing.title'),
                subtitle: sample('pricing.subtitle'),
                plans: [
                    {
                        name: sample('pricing.first.name'), price: sample('pricing.first.price'),
                        period: sample('pricing.first.period'), features: sample('pricing.first.features'),
                        cta_text: sample('pricing.first.cta_text'), cta_url: '#', highlighted: false,
                    },
                    {
                        name: sample('pricing.second.name'), price: sample('pricing.second.price'),
                        period: sample('pricing.second.period'), features: sample('pricing.second.features'),
                        cta_text: sample('pricing.second.cta_text'), cta_url: '#', highlighted: true,
                    },
                ],
            };
        case 'cta':
            return {
                title: sample('cta.title'),
                subtitle: sample('cta.subtitle'),
                button_text: sample('cta.button_text'),
                button_url: '#',
            };
        case 'rich_text':
            return { heading: sample('richText.heading'), body: sample('richText.body') };
        case 'image':
            return {
                image_path: '', image_url: null,
                alt: sample('image.alt'), caption: sample('image.caption'), link_url: '',
            };
        case 'stats':
            return {
                title: sample('stats.title'),
                items: [
                    { value: sample('stats.first.value'), label: sample('stats.first.label') },
                    { value: sample('stats.second.value'), label: sample('stats.second.label') },
                    { value: sample('stats.third.value'), label: sample('stats.third.label') },
                ],
            };
        case 'faq':
            return {
                title: sample('faq.title'),
                subtitle: sample('faq.subtitle'),
                items: [
                    { question: sample('faq.first.question'), answer: sample('faq.first.answer') },
                    { question: sample('faq.second.question'), answer: sample('faq.second.answer') },
                ],
            };
        case 'divider':
            return { style: 'rule', size: 'md' };
        default:
            return {};
    }
}

const rows = computed<SectionRow[]>(() =>
    props.sections.map(section => ({
        id: `demo-${section.key}`,
        type: section.key,
        variant: {},
        data: sampleData(section.key),
    })),
);

/**
 * The public renderer ships with the model bundle. Glob rather than a static
 * import so an unmerged build resolves to nothing instead of failing to compile.
 */
const rendererModules = import.meta.glob('../../Public/Templates/_shared/PageSections.vue');
const renderer = shallowRef<Component | null>(null);
const renderFailed = ref(false);

onMounted(async () => {
    const load = Object.values(rendererModules)[0] as (() => Promise<{ default: Component }>) | undefined;

    if (!load) return;

    try {
        renderer.value = (await load()).default ?? null;
    } catch {
        renderer.value = null;
    }
});

onErrorCaptured(() => {
    renderFailed.value = true;

    return false;
});

const viewport = ref('full');
const dark = ref(false);

const frameStyle = computed(() =>
    viewport.value === 'full' ? {} : { maxWidth: `${viewport.value}px` },
);

const builderRouteName = computed(() =>
    ['admin.landing.builder', 'admin.landing.builder.index'].find(routeExists) ?? null,
);

const builderHref = computed(() =>
    builderRouteName.value ? route(builderRouteName.value) : '/admin/landing/builder',
);
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('pageBuilder.demo.title') }]"
    >
        <Head :title="t('pageBuilder.demo.title')" />

        <PageHeader :title="t('pageBuilder.demo.title')" :description="t('pageBuilder.demo.description')">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="builderHref">
                        <LayoutTemplate class="size-4" aria-hidden="true" />
                        {{ t('pageBuilder.demo.openBuilder') }}
                    </Link>
                </Button>
                <a
                    href="/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ t('landing.preview.openInNewTab') }}
                </a>
            </template>
        </PageHeader>

        <p class="mt-4 rounded-md border border-dashed bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            {{ t('pageBuilder.demo.readOnly') }}
        </p>

        <div class="mt-6 space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('pageBuilder.demo.catalogue.title') }}</CardTitle>
                    <CardDescription>{{ t('pageBuilder.demo.catalogue.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Empty v-if="sections.length === 0" class="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PackageX class="size-6" aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('pageBuilder.demo.catalogue.title') }}</EmptyTitle>
                            <EmptyDescription>{{ t('pageBuilder.demo.catalogue.empty') }}</EmptyDescription>
                        </EmptyHeader>
                    </Empty>

                    <div v-else class="space-y-8">
                        <section v-for="bucket in grouped" :key="bucket.group">
                            <h3 class="text-sm font-semibold text-muted-foreground">
                                {{ groupLabel(bucket.group) }}
                            </h3>

                            <ul role="list" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <li
                                    v-for="section in bucket.sections"
                                    :key="section.key"
                                    class="rounded-lg border p-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                            <component :is="icon(section.icon)" class="size-4" aria-hidden="true" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium">{{ label(section) }}</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">{{ description(section) }}</p>
                                        </div>
                                    </div>

                                    <dl class="mt-3 space-y-2 text-xs">
                                        <div>
                                            <dt class="text-muted-foreground">{{ t('pageBuilder.demo.catalogue.fields') }}</dt>
                                            <dd class="mt-1 flex flex-wrap gap-1">
                                                <Badge v-for="field in section.fields" :key="field.name" variant="secondary">
                                                    {{ field.name }}<span aria-hidden="true"> · </span>{{ field.type }}
                                                </Badge>
                                                <span v-if="section.fields.length === 0" class="text-muted-foreground">
                                                    {{ t('pageBuilder.demo.catalogue.none') }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-muted-foreground">{{ t('pageBuilder.demo.catalogue.presentation') }}</dt>
                                            <dd class="mt-1 flex flex-wrap gap-1">
                                                <Badge v-for="(_spec, name) in section.variants" :key="name" variant="outline">
                                                    {{ name }}
                                                </Badge>
                                                <span
                                                    v-if="Object.keys(section.variants ?? {}).length === 0"
                                                    class="text-muted-foreground"
                                                >
                                                    {{ t('pageBuilder.demo.catalogue.none') }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div class="flex gap-4">
                                            <div>
                                                <dt class="text-muted-foreground">{{ t('pageBuilder.demo.catalogue.maxPerPage') }}</dt>
                                                <dd>
                                                    {{ section.maxPerPage > 0 ? section.maxPerPage : t('pageBuilder.demo.catalogue.unlimited') }}
                                                </dd>
                                            </div>
                                        </div>
                                    </dl>
                                </li>
                            </ul>
                        </section>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('pageBuilder.demo.preview.title') }}</CardTitle>
                    <CardDescription>{{ t('pageBuilder.demo.preview.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <BlockViewportBar v-model="viewport" v-model:dark="dark" />

                    <p v-if="renderFailed" class="rounded-md border border-dashed px-4 py-6 text-sm text-muted-foreground">
                        {{ t('pageBuilder.demo.preview.failed') }}
                    </p>

                    <p
                        v-else-if="!renderer || rows.length === 0"
                        class="rounded-md border border-dashed px-4 py-6 text-sm text-muted-foreground"
                    >
                        {{ t('pageBuilder.demo.preview.unavailable') }}
                    </p>

                    <div v-else class="mx-auto overflow-hidden rounded-lg border" :style="frameStyle">
                        <div :class="dark ? 'dark' : ''">
                            <div class="bg-background text-foreground">
                                <component
                                    :is="renderer"
                                    :blocks="rows"
                                    :settings="settings"
                                    :variants="{}"
                                    :overrides="{}"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('pageBuilder.docs.title') }}</CardTitle>
                    <CardDescription>{{ t('pageBuilder.docs.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="flex items-center gap-2 text-sm">
                        <BookOpen class="size-4 text-muted-foreground" aria-hidden="true" />
                        <span>{{ t('pageBuilder.docs.link') }}</span>
                        <code class="rounded bg-muted px-1.5 py-0.5 text-xs">docs/page-builder.md</code>
                    </p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
