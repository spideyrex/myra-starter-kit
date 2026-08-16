<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import LoadingButton from '@/components/LoadingButton.vue';
import LayoutPicker from '@/components/admin/appearance/LayoutPicker.vue';
import BackgroundPicker from '@/components/admin/appearance/BackgroundPicker.vue';
import ScrimPicker from '@/components/admin/appearance/ScrimPicker.vue';
import ContrastBadge from '@/components/admin/appearance/ContrastBadge.vue';
import SurfacePreview from '@/components/admin/appearance/SurfacePreview.vue';
import { useConfirm } from '@/composables/useConfirm';
import { AlertTriangle, ExternalLink, Upload, X } from 'lucide-vue-next';
import type {
    AppearanceOptions,
    AppearanceSettingsPayload,
    AuthAppearancePayload,
    AuthLayoutSchema,
    BackgroundTypeKey,
    ScrimKey,
} from '@/components/admin/appearance/types';

const props = defineProps<{
    appearanceSettings: AppearanceSettingsPayload;
    layouts: AuthLayoutSchema[];
    options: AppearanceOptions;
    preview: AuthAppearancePayload;
}>();

const { t } = useI18n();
const { confirm } = useConfirm();

const form = useForm({
    auth_layout: props.appearanceSettings.auth_layout ?? 'split',
    auth_flip: !!props.appearanceSettings.auth_flip,
    auth_show_tagline: props.appearanceSettings.auth_show_tagline !== false,
    auth_bg_type: (props.appearanceSettings.auth_bg_type ?? 'brand') as BackgroundTypeKey,
    auth_bg_color: props.appearanceSettings.auth_bg_color ?? '',
    auth_bg_recipe: props.appearanceSettings.auth_bg_recipe ?? '',
    auth_bg_scrim: (props.appearanceSettings.auth_bg_scrim ?? 'medium') as ScrimKey,
});

const live = ref<AuthAppearancePayload>(props.preview);
const previewing = ref(false);

const selected = computed<AuthLayoutSchema | undefined>(() =>
    props.layouts.find((layout) => layout.key === form.auth_layout),
);

const page = usePage();
const uploadError = computed(() => (page.props.errors as Record<string, string> | undefined)?.image ?? null);

const imageUrl = computed(() => props.appearanceSettings.auth_bg_image_url ?? null);
const imageBacked = computed(() => form.auth_bg_type === 'image');
const loginPreviewUrl = computed(() => `/login?authLayout=${encodeURIComponent(form.auth_layout)}`);

function payload() {
    return {
        ...form.data(),
        auth_bg_color: form.auth_bg_color || null,
        auth_bg_recipe: form.auth_bg_recipe || null,
    };
}

/** Real server resolution of UNSAVED input — the same call the save path uses. */
async function refreshPreview() {
    previewing.value = true;
    try {
        const res = await fetch(route('admin.appearance.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify(payload()),
        });
        if (!res.ok) return;
        live.value = (await res.json()).auth as AuthAppearancePayload;
    } catch {
        // The editor keeps the last good preview; nothing here can break a save.
    } finally {
        previewing.value = false;
    }
}

watch(
    () => [form.auth_layout, form.auth_flip, form.auth_show_tagline, form.auth_bg_type, form.auth_bg_color, form.auth_bg_recipe, form.auth_bg_scrim],
    () => { void refreshPreview(); },
);

/** A shell with no media panel cannot carry an image; fall back to the brand surface. */
watch(selected, (layout) => {
    if (layout && !layout.flippable) form.auth_flip = false;
    if (layout && !layout.supportsMedia && form.auth_bg_type === 'image') form.auth_bg_type = 'brand';
});

function save() {
    form.transform(() => payload()).put(route('admin.appearance.update'), { preserveScroll: true });
}

function uploadImage(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    router.post(route('admin.appearance.background.store', { surface: 'auth' }), { image: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (selected.value?.supportsMedia) form.auth_bg_type = 'image';
        },
        onFinish: () => { input.value = ''; },
    });
}

async function removeImage() {
    if (!(await confirm({ title: t('appearanceAdmin.media.remove'), description: t('appearanceAdmin.media.removeConfirm') }))) return;

    router.delete(route('admin.appearance.background.destroy', { surface: 'auth' }), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.system') }, { label: t('appearanceAdmin.title') }]">
        <Head :title="t('appearanceAdmin.title')" />

        <PageHeader :title="t('appearanceAdmin.title')" :description="t('appearanceAdmin.description')">
            <template #actions>
                <a
                    :href="loginPreviewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ t('appearanceAdmin.preview.openLogin') }}
                </a>
            </template>
        </PageHeader>

        <Alert v-if="live.warning" class="mt-4">
            <AlertTriangle class="size-4" />
            <AlertTitle>{{ t('appearanceAdmin.contrast.title') }}</AlertTitle>
            <AlertDescription>
                {{ t('appearanceAdmin.contrast.advisory', { ratio: live.contrast.toFixed(2), minimum: options.minContrast }) }}
            </AlertDescription>
        </Alert>

        <Tabs default-value="layout" class="mt-6">
            <TabsList class="flex-wrap">
                <TabsTrigger value="layout">{{ t('appearanceAdmin.tabs.layout') }}</TabsTrigger>
                <TabsTrigger value="background">{{ t('appearanceAdmin.tabs.background') }}</TabsTrigger>
                <TabsTrigger value="media">{{ t('appearanceAdmin.tabs.media') }}</TabsTrigger>
                <TabsTrigger value="preview">{{ t('appearanceAdmin.tabs.preview') }}</TabsTrigger>
            </TabsList>

            <TabsContent value="layout" class="space-y-4 pt-4">
                <LayoutPicker v-model="form.auth_layout" :layouts="layouts" />
                <p v-if="form.errors.auth_layout" class="text-sm text-destructive">{{ form.errors.auth_layout }}</p>

                <Separator />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-start justify-between gap-4 rounded-lg border p-4">
                        <div class="space-y-1">
                            <Label for="auth-flip" class="text-base">{{ t('appearanceAdmin.fields.flip') }}</Label>
                            <p class="text-sm text-muted-foreground">
                                {{ selected?.flippable ? t('appearanceAdmin.help.flip') : t('appearanceAdmin.help.flipUnsupported') }}
                            </p>
                        </div>
                        <Switch id="auth-flip" v-model="form.auth_flip" :disabled="!selected?.flippable" />
                    </div>

                    <div class="flex items-start justify-between gap-4 rounded-lg border p-4">
                        <div class="space-y-1">
                            <Label for="auth-tagline" class="text-base">{{ t('appearanceAdmin.fields.showTagline') }}</Label>
                            <p class="text-sm text-muted-foreground">{{ t('appearanceAdmin.help.showTagline') }}</p>
                        </div>
                        <Switch id="auth-tagline" v-model="form.auth_show_tagline" />
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="background" class="space-y-4 pt-4">
                <BackgroundPicker
                    v-model:type="form.auth_bg_type"
                    v-model:color="form.auth_bg_color"
                    v-model:recipe="form.auth_bg_recipe"
                    :options="options"
                    :supports-media="selected?.supportsMedia ?? true"
                    :errors="form.errors as Record<string, string>"
                />

                <Separator />

                <ScrimPicker v-model="form.auth_bg_scrim" :scrims="options.scrims" :floored="imageBacked" />
                <p v-if="form.errors.auth_bg_scrim" class="text-sm text-destructive">{{ form.errors.auth_bg_scrim }}</p>

                <Separator />

                <ContrastBadge :ratio="live.contrast" :minimum="options.minContrast" :image-backed="imageBacked" />
            </TabsContent>

            <TabsContent value="media" class="space-y-4 pt-4">
                <Alert>
                    <AlertTitle>{{ t('appearanceAdmin.media.title') }}</AlertTitle>
                    <AlertDescription>{{ t('appearanceAdmin.media.help') }}</AlertDescription>
                </Alert>

                <div class="max-w-md space-y-2 rounded-lg border p-3">
                    <Label for="auth-bg-image">{{ t('appearanceAdmin.media.current') }}</Label>

                    <AspectRatio :ratio="16 / 9" class="overflow-hidden rounded-md bg-muted">
                        <img v-if="imageUrl" :src="imageUrl" alt="" class="size-full object-cover" />
                        <span v-else class="flex size-full items-center justify-center text-xs text-muted-foreground">
                            {{ t('appearanceAdmin.media.none') }}
                        </span>
                    </AspectRatio>

                    <div class="flex items-center gap-2">
                        <input
                            id="auth-bg-image"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-xs file:mr-2 file:rounded file:border file:px-2 file:py-1"
                            @change="uploadImage"
                        />
                        <Button v-if="imageUrl" variant="ghost" size="icon" :aria-label="t('appearanceAdmin.media.remove')" @click="removeImage">
                            <X class="size-4" />
                        </Button>
                    </div>

                    <p v-if="uploadError" class="text-sm text-destructive">{{ uploadError }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('appearanceAdmin.media.formats') }}</p>
                </div>
            </TabsContent>

            <TabsContent value="preview" class="pt-4">
                <div class="grid gap-6 sm:grid-cols-2" :aria-busy="previewing">
                    <SurfacePreview :auth="live" />
                    <SurfacePreview :auth="live" dark />
                </div>
                <p class="mt-4 text-xs text-muted-foreground">{{ t('appearanceAdmin.preview.help') }}</p>
            </TabsContent>
        </Tabs>

        <div class="mt-6 flex items-center gap-3">
            <LoadingButton :loading="form.processing" @click="save">
                <Upload class="mr-2 size-4" />
                {{ t('appearanceAdmin.save') }}
            </LoadingButton>
        </div>
    </AuthenticatedLayout>
</template>
