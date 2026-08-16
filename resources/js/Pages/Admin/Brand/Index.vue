<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import LoadingButton from '@/components/LoadingButton.vue';
import ColorSwatch from '@/components/admin/ColorSwatch.vue';
import BrandPreview from '@/components/brand/BrandPreview.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Upload, X, AlertTriangle } from 'lucide-vue-next';
import type { BrandContrast, BrandTokens } from '@/types';

const props = defineProps<{
    brandSettings: Record<string, any>;
    tokens: BrandTokens;
    contrast: Record<string, BrandContrast>;
    options: {
        presets: { key: string; color: string }[];
        fontSans: string[];
        fontMono: string[];
        radii: string[];
        slots: string[];
        minContrast: number;
    };
}>();

const { t } = useI18n();
const { confirm } = useConfirm();

const form = useForm({
    enabled: !!props.brandSettings.enabled,
    name: props.brandSettings.name ?? '',
    short_name: props.brandSettings.short_name ?? '',
    tagline: props.brandSettings.tagline ?? '',
    description: props.brandSettings.description ?? '',
    primary: props.brandSettings.primary ?? '#18181b',
    accent: props.brandSettings.accent ?? '',
    sidebar_background: props.brandSettings.sidebar_background ?? '',
    sidebar_foreground: props.brandSettings.sidebar_foreground ?? '',
    sidebar_accent: props.brandSettings.sidebar_accent ?? '',
    preset: props.brandSettings.preset ?? 'zinc',
    font_sans: props.brandSettings.font_sans ?? 'figtree',
    font_mono: props.brandSettings.font_mono ?? 'ui-monospace',
    radius: props.brandSettings.radius ?? '0.625rem',
    dark_default: !!props.brandSettings.dark_default,
    logo_position: props.brandSettings.logo_position ?? 'header',
});

const live = ref<BrandTokens>(props.tokens);
const liveContrast = reactive<Record<string, BrandContrast>>({ ...props.contrast });
const previewing = ref(false);

const failing = computed(() =>
    Object.entries(liveContrast).filter(([, c]) => c.ratio < props.options.minContrast),
);

const assetSlots = computed(() => [
    { slot: 'logo', label: t('brand.assets.logo'), url: props.brandSettings.logo_url },
    { slot: 'logo_dark', label: t('brand.assets.logoDark'), url: props.brandSettings.logo_dark_url },
    { slot: 'mark', label: t('brand.assets.mark'), url: props.brandSettings.mark_url },
    { slot: 'favicon', label: t('brand.assets.favicon'), url: props.brandSettings.favicon_url },
    { slot: 'og_image', label: t('brand.assets.ogImage'), url: props.brandSettings.og_image_url },
]);

/** Real server-resolved tokens for UNSAVED input — nothing is persisted. */
async function refreshPreview() {
    previewing.value = true;
    try {
        const res = await fetch(route('admin.brand.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({ ...form.data(), accent: form.accent || null }),
        });
        if (!res.ok) return;
        const payload = await res.json();
        live.value = payload.tokens as BrandTokens;
        Object.assign(liveContrast, payload.contrast);
    } finally {
        previewing.value = false;
    }
}

watch(
    () => [form.primary, form.accent, form.sidebar_background, form.sidebar_foreground, form.sidebar_accent, form.name, form.radius, form.font_sans],
    () => { void refreshPreview(); },
);

function save() {
    form
        .transform((data) => ({
            ...data,
            accent: data.accent || null,
            sidebar_background: data.sidebar_background || null,
            sidebar_foreground: data.sidebar_foreground || null,
            sidebar_accent: data.sidebar_accent || null,
            short_name: data.short_name || null,
        }))
        .put(route('admin.brand.update'), { preserveScroll: true });
}

function uploadAsset(slot: string, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    router.post(route('admin.brand.asset.store', { slot }), { [slot]: file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { input.value = ''; },
    });
}

function removeAsset(slot: string) {
    router.delete(route('admin.brand.asset.destroy', { slot }), { preserveScroll: true });
}

async function reset() {
    if (!(await confirm({ title: t('brand.reset.label'), description: t('brand.reset.confirm') }))) return;
    router.post(route('admin.brand.reset'), {}, { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: t('brand.title') }]">
        <Head :title="t('brand.title')" />
        <PageHeader :title="t('brand.title')" :description="t('brand.description')" />

        <div class="mt-6 flex items-center justify-between gap-4 rounded-lg border p-4">
            <div class="space-y-1">
                <Label for="brand-enabled" class="text-base">{{ t('brand.enable.label') }}</Label>
                <p class="text-sm text-muted-foreground">{{ t('brand.enable.help') }}</p>
            </div>
            <Switch id="brand-enabled" v-model="form.enabled" />
        </div>

        <Alert v-if="failing.length" variant="destructive" class="mt-4">
            <AlertTriangle class="size-4" />
            <AlertTitle>{{ t('brand.tabs.colour') }}</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="[key, c] in failing" :key="key">
                        {{ t('brand.fields.' + (key === 'primary' ? 'primary' : key === 'accent' ? 'accent' : 'sidebarBackground')) }}:
                        {{ t('brand.contrast.warning', { ratio: c.ratio, minimum: options.minContrast }) }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Tabs default-value="identity" class="mt-6">
            <TabsList class="flex-wrap">
                <TabsTrigger value="identity">{{ t('brand.tabs.identity') }}</TabsTrigger>
                <TabsTrigger value="colour">{{ t('brand.tabs.colour') }}</TabsTrigger>
                <TabsTrigger value="typography">{{ t('brand.tabs.typography') }}</TabsTrigger>
                <TabsTrigger value="assets">{{ t('brand.tabs.assets') }}</TabsTrigger>
                <TabsTrigger value="preview">{{ t('brand.tabs.preview') }}</TabsTrigger>
            </TabsList>

            <TabsContent value="identity" class="space-y-4 pt-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="brand-name">{{ t('brand.fields.name') }}</Label>
                        <Input id="brand-name" v-model="form.name" required />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="brand-short">{{ t('brand.fields.shortName') }}</Label>
                        <Input id="brand-short" v-model="form.short_name" maxlength="24" />
                    </div>
                    <div class="space-y-2">
                        <Label for="brand-tagline">{{ t('brand.fields.tagline') }}</Label>
                        <Input id="brand-tagline" v-model="form.tagline" maxlength="200" />
                    </div>
                    <div class="space-y-2">
                        <Label for="brand-description">{{ t('brand.fields.description') }}</Label>
                        <Input id="brand-description" v-model="form.description" maxlength="400" />
                    </div>
                </div>

                <Separator />

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">{{ t('brand.fields.logoPosition') }}</legend>
                    <RadioGroup v-model="form.logo_position" class="flex gap-6">
                        <div v-for="pos in ['sidebar', 'header']" :key="pos" class="flex items-center gap-2">
                            <RadioGroupItem :id="`logo-pos-${pos}`" :value="pos" />
                            <Label :for="`logo-pos-${pos}`">{{ t('brand.logoPositions.' + pos) }}</Label>
                        </div>
                    </RadioGroup>
                </fieldset>
            </TabsContent>

            <TabsContent value="colour" class="space-y-4 pt-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="field in ['primary', 'accent', 'sidebar_background', 'sidebar_foreground', 'sidebar_accent']" :key="field" class="space-y-2">
                        <Label :for="`brand-${field}`">
                            {{ t('brand.fields.' + (field === 'primary' ? 'primary' : field === 'accent' ? 'accent' : field === 'sidebar_background' ? 'sidebarBackground' : field === 'sidebar_foreground' ? 'sidebarForeground' : 'sidebarAccent')) }}
                        </Label>
                        <div class="flex items-center gap-2">
                            <Input :id="`brand-${field}`" v-model="(form as any)[field]" type="text" placeholder="#000000" class="font-mono" />
                            <ColorSwatch :value="(form as any)[field]" :size="24" :show-value="false" />
                        </div>
                        <p v-if="(form.errors as any)[field]" class="text-sm text-destructive">{{ (form.errors as any)[field] }}</p>
                    </div>
                </div>

                <Separator />

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">{{ t('brand.fields.preset') }}</legend>
                    <RadioGroup v-model="form.preset" class="flex flex-wrap gap-3">
                        <div v-for="preset in options.presets" :key="preset.key" class="flex items-center gap-2">
                            <RadioGroupItem :id="`preset-${preset.key}`" :value="preset.key" />
                            <Label :for="`preset-${preset.key}`" class="flex items-center gap-1.5">
                                <ColorSwatch :value="preset.color" :size="14" :show-value="false" />
                                {{ preset.key }}
                            </Label>
                        </div>
                    </RadioGroup>
                </fieldset>

                <p v-for="(c, key) in liveContrast" :key="key" class="text-xs text-muted-foreground">
                    {{ key }} —
                    {{ c.ratio >= options.minContrast
                        ? t('brand.contrast.ok', { ratio: c.ratio })
                        : t('brand.contrast.warning', { ratio: c.ratio, minimum: options.minContrast }) }}
                </p>
            </TabsContent>

            <TabsContent value="typography" class="space-y-4 pt-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="font-sans">{{ t('brand.fields.fontSans') }}</Label>
                        <select id="font-sans" v-model="form.font_sans" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option v-for="f in options.fontSans" :key="f" :value="f">{{ f }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="font-mono">{{ t('brand.fields.fontMono') }}</Label>
                        <select id="font-mono" v-model="form.font_mono" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option v-for="f in options.fontMono" :key="f" :value="f">{{ f }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="radius">{{ t('brand.fields.radius') }}</Label>
                        <select id="radius" v-model="form.radius" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option v-for="r in options.radii" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="dark-default" v-model="form.dark_default" />
                    <Label for="dark-default">{{ t('brand.fields.darkDefault') }}</Label>
                </div>
            </TabsContent>

            <TabsContent value="assets" class="space-y-4 pt-4">
                <Alert>
                    <AlertTitle>{{ t('brand.assets.upload') }}</AlertTitle>
                    <AlertDescription>
                        {{ t('brand.assets.icoAccepted') }} {{ t('brand.assets.rejectedSvg') }}
                    </AlertDescription>
                </Alert>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="asset in assetSlots" :key="asset.slot" class="space-y-2 rounded-lg border p-3">
                        <Label :for="`asset-${asset.slot}`">{{ asset.label }}</Label>

                        <AspectRatio :ratio="16 / 9" class="overflow-hidden rounded-md bg-muted">
                            <img v-if="asset.url" :src="asset.url" :alt="asset.label" class="size-full object-contain" />
                            <span v-else class="flex size-full items-center justify-center text-xs text-muted-foreground">—</span>
                        </AspectRatio>

                        <div class="flex items-center gap-2">
                            <input
                                :id="`asset-${asset.slot}`"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/x-icon,.ico"
                                class="block w-full text-xs file:mr-2 file:rounded file:border file:px-2 file:py-1"
                                @change="uploadAsset(asset.slot, $event)"
                            />
                            <Button v-if="asset.url" variant="ghost" size="icon" :aria-label="t('brand.assets.remove')" @click="removeAsset(asset.slot)">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="preview" class="pt-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :aria-busy="previewing">
                    <BrandPreview v-for="surface in ['admin', 'login', 'public', 'email', 'pdf', 'install']" :key="surface" :tokens="live" :surface="surface as any" />
                </div>
            </TabsContent>
        </Tabs>

        <div class="mt-6 flex items-center gap-3">
            <LoadingButton :loading="form.processing" @click="save">
                <Upload class="mr-2 size-4" />
                {{ t('brand.save') }}
            </LoadingButton>
            <Button variant="outline" @click="reset">{{ t('brand.reset.label') }}</Button>
        </div>
    </AuthenticatedLayout>
</template>
