<script setup lang="ts">
import { computed, ref, type Component } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { TriangleAlert } from 'lucide-vue-next';
import type { AuthAppearancePayload, SurfacePayload } from '@/Layouts/Guest/authTypes';
import { STOCK_SURFACE, normalizeSurface } from '@/Layouts/Guest/authTypes';

/**
 * The guest-shell gallery. Every shell is resolved through the SAME eager glob
 * the dispatcher uses, so a shell that is not in this build simply does not
 * appear — the page never imports a component another bundle owns.
 */
interface LayoutSchema {
    key: string;
    component: string;
    titleKey: string;
    descriptionKey: string;
    thumbnail: string | null;
    flippable: boolean;
    supportsMedia: boolean;
    since: string;
}

interface SurfaceOption {
    key: string;
    labelKey: string;
    live: boolean;
    surface: SurfacePayload;
}

const props = withDefaults(defineProps<{
    layouts?: LayoutSchema[];
    surfaces?: SurfaceOption[];
}>(), {
    layouts: () => [],
    surfaces: () => [],
});

const { t, te } = useI18n();

const MODULES = import.meta.glob<{ default: Component }>('../../../Layouts/Guest/*Layout.vue', { eager: true });

const BY_NAME: Record<string, Component> = {};
for (const [path, mod] of Object.entries(MODULES)) {
    BY_NAME[path.split('/').pop()!.replace('.vue', '')] = mod.default;
}

const shown = computed(() => props.layouts.filter(layout => BY_NAME[layout.component] !== undefined));
const missing = computed(() => props.layouts.filter(layout => BY_NAME[layout.component] === undefined));

const options = computed<SurfaceOption[]>(() =>
    props.surfaces.length > 0
        ? props.surfaces
        : [{ key: 'brand', labelKey: 'appearanceDemo.surfaces.brand', live: false, surface: STOCK_SURFACE }],
);

const selected = ref(0);
const flip = ref(false);
const showTagline = ref(true);
const dark = ref(false);

const surface = computed(() => normalizeSurface(options.value[selected.value]?.surface));
const degraded = computed(() => options.value.some(option => !option.live));

function payload(layout: LayoutSchema): AuthAppearancePayload {
    return {
        layout: layout.key,
        component: layout.component,
        flip: layout.flippable && flip.value,
        show_tagline: showTagline.value,
        supports_media: layout.supportsMedia,
        surface: surface.value,
    };
}

/** The registry's own keys when the admin bundle is merged, ours otherwise. */
function label(key: string, fallback: string): string {
    return te(key) ? t(key) : t(fallback);
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('appearanceDemo.title') }]"
    >
        <Head :title="t('appearanceDemo.title')" />

        <PageHeader :title="t('appearanceDemo.title')" :description="t('appearanceDemo.description')" />

        <div class="mt-6 space-y-6">
            <Alert v-if="degraded">
                <TriangleAlert class="size-4" aria-hidden="true" />
                <AlertTitle>{{ t('appearanceDemo.degraded.title') }}</AlertTitle>
                <AlertDescription>{{ t('appearanceDemo.degraded.description') }}</AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('appearanceDemo.controls.title') }}</CardTitle>
                    <CardDescription>{{ t('appearanceDemo.controls.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2" role="group" :aria-label="t('appearanceDemo.controls.surface')">
                        <Button
                            v-for="(option, index) in options"
                            :key="option.key"
                            type="button"
                            size="sm"
                            :variant="index === selected ? 'default' : 'outline'"
                            :aria-pressed="index === selected"
                            @click="selected = index"
                        >
                            {{ t(option.labelKey) }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <div class="flex items-center gap-2">
                            <Switch id="demo-flip" v-model="flip" />
                            <Label for="demo-flip">{{ t('appearanceDemo.controls.flip') }}</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch id="demo-tagline" v-model="showTagline" />
                            <Label for="demo-tagline">{{ t('appearanceDemo.controls.tagline') }}</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch id="demo-dark" v-model="dark" />
                            <Label for="demo-dark">{{ t('appearanceDemo.controls.dark') }}</Label>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="missing.length > 0">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('appearanceDemo.missing.title') }}</CardTitle>
                    <CardDescription>{{ t('appearanceDemo.missing.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge v-for="layout in missing" :key="layout.key" variant="secondary">
                        {{ layout.component }}
                    </Badge>
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card v-for="layout in shown" :key="layout.key" data-slot="shell-preview">
                    <CardHeader>
                        <div class="flex flex-wrap items-center gap-2">
                            <CardTitle class="text-base">
                                {{ label(layout.titleKey, `appearanceDemo.layouts.${layout.key}.title`) }}
                            </CardTitle>
                            <Badge variant="secondary">{{ layout.key }}</Badge>
                            <Badge v-if="layout.flippable" variant="outline">
                                {{ t('appearanceDemo.badges.flippable') }}
                            </Badge>
                            <Badge v-if="layout.supportsMedia" variant="outline">
                                {{ t('appearanceDemo.badges.media') }}
                            </Badge>
                        </div>
                        <CardDescription>
                            {{ label(layout.descriptionKey, `appearanceDemo.layouts.${layout.key}.description`) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <!-- The shell asks for the viewport; the wrapper scales it down instead. -->
                        <div
                            class="h-[380px] overflow-hidden rounded-lg border"
                            :class="dark ? 'dark' : ''"
                            role="img"
                            :aria-label="label(layout.titleKey, `appearanceDemo.layouts.${layout.key}.title`)"
                        >
                            <div
                                class="pointer-events-none h-[1520px] w-[400%] origin-top-left scale-[0.25]"
                                aria-hidden="true"
                                inert
                            >
                                <component :is="BY_NAME[layout.component]" :auth="payload(layout)">
                                    <div class="space-y-6">
                                        <div class="space-y-1">
                                            <h2 class="text-2xl font-bold">{{ t('appearanceDemo.form.title') }}</h2>
                                            <p class="text-sm text-muted-foreground">
                                                {{ t('appearanceDemo.form.subtitle') }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <Label :for="`demo-email-${layout.key}`">{{ t('appearanceDemo.form.email') }}</Label>
                                            <Input :id="`demo-email-${layout.key}`" type="email" readonly tabindex="-1" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label :for="`demo-password-${layout.key}`">{{ t('appearanceDemo.form.password') }}</Label>
                                            <Input :id="`demo-password-${layout.key}`" type="password" readonly tabindex="-1" />
                                        </div>
                                        <Button class="w-full" type="button" tabindex="-1">
                                            {{ t('appearanceDemo.form.submit') }}
                                        </Button>
                                    </div>
                                </component>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
