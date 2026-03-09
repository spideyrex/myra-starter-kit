<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField, FormFields, SettingsCard } from '@/components/admin';
import RepeaterField from '@/components/admin/RepeaterField.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { useConfirm } from '@/composables/useConfirm';
import { TextInput, Textarea, Toggle, Select } from '@/composables/useFormSchema';
import { Upload, X, Image, Globe, Check } from 'lucide-vue-next';
import { themePresets } from '@/composables/useThemeColors';

const props = defineProps<{
    general: Record<string, any>;
    seo: Record<string, any>;
    appearance: Record<string, any>;
    social: Record<string, any>;
    maintenance: Record<string, any>;
    homepage: Record<string, any>;
}>();

const generalForm = useForm({ ...props.general });
const seoForm = useForm({ ...props.seo });
const socialForm = useForm({ ...props.social });
const maintenanceForm = useForm({ ...props.maintenance });

// Appearance state (managed manually for file uploads)
const appearanceProcessing = ref(false);
const selectedTheme = ref(props.appearance.theme || 'zinc');
const logoFile = ref<File | null>(null);
const faviconFile = ref<File | null>(null);
const logoPreview = ref<string | null>(props.appearance.logo_url || null);
const faviconPreview = ref<string | null>(props.appearance.favicon_url || null);
const removeLogo = ref(false);
const removeFavicon = ref(false);
const appearanceErrors = ref<Record<string, string>>({});

const logoInput = ref<HTMLInputElement>();
const faviconInput = ref<HTMLInputElement>();

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    logoFile.value = file;
    removeLogo.value = false;
    logoPreview.value = URL.createObjectURL(file);
}

function onFaviconChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    faviconFile.value = file;
    removeFavicon.value = false;
    faviconPreview.value = URL.createObjectURL(file);
}

function clearLogo() {
    logoFile.value = null;
    logoPreview.value = null;
    removeLogo.value = true;
    if (logoInput.value) logoInput.value.value = '';
}

function clearFavicon() {
    faviconFile.value = null;
    faviconPreview.value = null;
    removeFavicon.value = true;
    if (faviconInput.value) faviconInput.value.value = '';
}

// Homepage state (managed manually for hero image upload)
const homepageProcessing = ref(false);
const homepageErrors = ref<Record<string, string>>({});
const heroImageFile = ref<File | null>(null);
const heroImagePreview = ref<string | null>(props.homepage.hero_image_url || null);
const removeHeroImage = ref(false);
const heroImageInput = ref<HTMLInputElement>();

const hpEnabled = ref(props.homepage.enabled ?? true);
const hpHeroTitle = ref(props.homepage.hero_title || '');
const hpHeroSubtitle = ref(props.homepage.hero_subtitle || '');
const hpHeroCtaText = ref(props.homepage.hero_cta_text || '');
const hpHeroCtaUrl = ref(props.homepage.hero_cta_url || '');

const hpNavbarCtaText = ref(props.homepage.navbar_cta_text || '');
const hpNavbarCtaUrl = ref(props.homepage.navbar_cta_url || '');
const hpNavbarLinks = ref<Array<{ label: string; url: string }>>(props.homepage.navbar_links || []);

const hpFeaturesEnabled = ref(props.homepage.features_enabled ?? true);
const hpFeaturesTitle = ref(props.homepage.features_title || '');
const hpFeaturesSubtitle = ref(props.homepage.features_subtitle || '');
const hpFeatures = ref<Array<{ icon: string; title: string; description: string }>>(props.homepage.features || []);

const hpTestimonialsEnabled = ref(props.homepage.testimonials_enabled ?? true);
const hpTestimonialsTitle = ref(props.homepage.testimonials_title || '');
const hpTestimonialsSubtitle = ref(props.homepage.testimonials_subtitle || '');
const hpTestimonials = ref<Array<{ name: string; role: string; quote: string }>>(props.homepage.testimonials || []);

const hpPricingEnabled = ref(props.homepage.pricing_enabled ?? true);
const hpPricingTitle = ref(props.homepage.pricing_title || '');
const hpPricingSubtitle = ref(props.homepage.pricing_subtitle || '');
const hpPricingPlans = ref<Array<Record<string, any>>>(props.homepage.pricing_plans || []);

const hpCtaEnabled = ref(props.homepage.cta_enabled ?? true);
const hpCtaTitle = ref(props.homepage.cta_title || '');
const hpCtaSubtitle = ref(props.homepage.cta_subtitle || '');
const hpCtaButtonText = ref(props.homepage.cta_button_text || '');
const hpCtaButtonUrl = ref(props.homepage.cta_button_url || '');

const hpFooterText = ref(props.homepage.footer_text || '');
const hpFooterLinks = ref<Array<{ label: string; url: string }>>(props.homepage.footer_links || []);

function onHeroImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    heroImageFile.value = file;
    removeHeroImage.value = false;
    heroImagePreview.value = URL.createObjectURL(file);
}

function clearHeroImage() {
    heroImageFile.value = null;
    heroImagePreview.value = null;
    removeHeroImage.value = true;
    if (heroImageInput.value) heroImageInput.value.value = '';
}

// Icon options for feature select
const iconOptions = [
    { label: 'Zap', value: 'Zap' },
    { label: 'Shield', value: 'Shield' },
    { label: 'BarChart3', value: 'BarChart3' },
    { label: 'Users', value: 'Users' },
    { label: 'Lock', value: 'Lock' },
    { label: 'Globe', value: 'Globe' },
    { label: 'Rocket', value: 'Rocket' },
    { label: 'Heart', value: 'Heart' },
    { label: 'Star', value: 'Star' },
    { label: 'Code', value: 'Code' },
    { label: 'Database', value: 'Database' },
    { label: 'Cloud', value: 'Cloud' },
    { label: 'Settings', value: 'Settings' },
    { label: 'Mail', value: 'Mail' },
    { label: 'Bell', value: 'Bell' },
    { label: 'Search', value: 'Search' },
    { label: 'Layers', value: 'Layers' },
    { label: 'Layout', value: 'Layout' },
];

const featureSchema = [
    Select.make('icon').label('Icon').options(iconOptions),
    TextInput.make('title'),
    Textarea.make('description').rows(2),
];

const testimonialSchema = [
    TextInput.make('name'),
    TextInput.make('role'),
    Textarea.make('quote').rows(2),
];

const pricingSchema = [
    TextInput.make('name').label('Plan Name'),
    TextInput.make('price').placeholder('$29'),
    TextInput.make('period').placeholder('/month'),
    Textarea.make('features').hint('Comma-separated list').rows(2),
    TextInput.make('cta_text').label('CTA Text'),
    TextInput.make('cta_url').label('CTA URL'),
    Toggle.make('highlighted').label('Highlighted'),
];

const navLinkSchema = [
    TextInput.make('label'),
    TextInput.make('url'),
];

const footerLinkSchema = [
    TextInput.make('label'),
    TextInput.make('url'),
];

const generalSchema = [
    TextInput.make('site_name'),
    TextInput.make('admin_email').email(),
    Textarea.make('site_description').colSpan(2),
    TextInput.make('site_url'),
    TextInput.make('timezone'),
];

const seoSchema = [
    TextInput.make('meta_title'),
    Textarea.make('meta_description'),
    TextInput.make('meta_keywords'),
    TextInput.make('google_analytics_id').placeholder('UA-XXXXX-X'),
];

const socialSchema = [
    TextInput.make('facebook_url').label('Facebook URL'),
    TextInput.make('twitter_url').label('Twitter URL'),
    TextInput.make('instagram_url').label('Instagram URL'),
    TextInput.make('linkedin_url').label('LinkedIn URL'),
];

const maintenanceSchema = [
    Toggle.make('enabled').label('Enable maintenance mode'),
    Textarea.make('message').label('Maintenance Message'),
];

const { confirm } = useConfirm();

async function saveGroup(group: string, form: any) {
    const confirmed = await confirm({ title: 'Save Settings', description: `Are you sure you want to save the ${group} settings?`, confirmText: 'Save' });
    if (confirmed) form.put(route('admin.settings.update', group));
}

async function saveAppearance() {
    const confirmed = await confirm({ title: 'Save Settings', description: 'Are you sure you want to save the appearance settings?', confirmText: 'Save' });
    if (!confirmed) return;

    const data = new FormData();
    data.append('theme', selectedTheme.value);

    if (logoFile.value) data.append('logo', logoFile.value);
    if (faviconFile.value) data.append('favicon', faviconFile.value);
    if (removeLogo.value) data.append('remove_logo', '1');
    if (removeFavicon.value) data.append('remove_favicon', '1');

    appearanceProcessing.value = true;
    appearanceErrors.value = {};

    router.post(route('admin.settings.update-appearance'), data, {
        forceFormData: true,
        onFinish: () => {
            appearanceProcessing.value = false;
            logoFile.value = null;
            faviconFile.value = null;
            removeLogo.value = false;
            removeFavicon.value = false;
        },
        onError: (errors) => {
            appearanceErrors.value = errors;
        },
    });
}

async function saveHomepage() {
    const confirmed = await confirm({ title: 'Save Settings', description: 'Are you sure you want to save the homepage settings?', confirmText: 'Save' });
    if (!confirmed) return;

    const data = new FormData();

    // Boolean fields
    data.append('enabled', hpEnabled.value ? '1' : '0');
    data.append('features_enabled', hpFeaturesEnabled.value ? '1' : '0');
    data.append('testimonials_enabled', hpTestimonialsEnabled.value ? '1' : '0');
    data.append('pricing_enabled', hpPricingEnabled.value ? '1' : '0');
    data.append('cta_enabled', hpCtaEnabled.value ? '1' : '0');

    // String fields
    data.append('hero_title', hpHeroTitle.value);
    data.append('hero_subtitle', hpHeroSubtitle.value);
    data.append('hero_cta_text', hpHeroCtaText.value);
    data.append('hero_cta_url', hpHeroCtaUrl.value);

    data.append('navbar_cta_text', hpNavbarCtaText.value);
    data.append('navbar_cta_url', hpNavbarCtaUrl.value);

    data.append('features_title', hpFeaturesTitle.value);
    data.append('features_subtitle', hpFeaturesSubtitle.value);

    data.append('testimonials_title', hpTestimonialsTitle.value);
    data.append('testimonials_subtitle', hpTestimonialsSubtitle.value);

    data.append('pricing_title', hpPricingTitle.value);
    data.append('pricing_subtitle', hpPricingSubtitle.value);

    data.append('cta_title', hpCtaTitle.value);
    data.append('cta_subtitle', hpCtaSubtitle.value);
    data.append('cta_button_text', hpCtaButtonText.value);
    data.append('cta_button_url', hpCtaButtonUrl.value);

    data.append('footer_text', hpFooterText.value);

    // Array fields as JSON
    data.append('features', JSON.stringify(hpFeatures.value));
    data.append('testimonials', JSON.stringify(hpTestimonials.value));
    data.append('pricing_plans', JSON.stringify(hpPricingPlans.value));
    data.append('navbar_links', JSON.stringify(hpNavbarLinks.value));
    data.append('footer_links', JSON.stringify(hpFooterLinks.value));

    // Hero image
    if (heroImageFile.value) data.append('hero_image', heroImageFile.value);
    if (removeHeroImage.value) data.append('remove_hero_image', '1');

    homepageProcessing.value = true;
    homepageErrors.value = {};

    router.post(route('admin.settings.update-homepage'), data, {
        forceFormData: true,
        onFinish: () => {
            homepageProcessing.value = false;
            heroImageFile.value = null;
            removeHeroImage.value = false;
        },
        onError: (errors) => {
            homepageErrors.value = errors;
        },
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'Settings' }]">
        <Head title="Settings" />
        <PageHeader title="Settings" description="Manage your application settings." />

        <Tabs default-value="general" class="mt-6">
            <TabsList class="flex-wrap">
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="seo">SEO</TabsTrigger>
                <TabsTrigger value="appearance">Appearance</TabsTrigger>
                <TabsTrigger value="social">Social</TabsTrigger>
                <TabsTrigger value="maintenance">Maintenance</TabsTrigger>
                <TabsTrigger value="homepage">Homepage</TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <SettingsCard title="General Settings" description="Basic application configuration." :processing="generalForm.processing" @submit="saveGroup('general', generalForm)">
                    <FormFields :schema="generalSchema" :form="generalForm" />
                </SettingsCard>
            </TabsContent>

            <TabsContent value="seo">
                <SettingsCard title="SEO Settings" :processing="seoForm.processing" :columns="1" @submit="saveGroup('seo', seoForm)">
                    <FormFields :schema="seoSchema" :form="seoForm" />
                </SettingsCard>
            </TabsContent>

            <TabsContent value="appearance">
                <Card>
                    <CardHeader>
                        <CardTitle>Appearance Settings</CardTitle>
                        <CardDescription>Customize your application's branding and look.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="saveAppearance" class="space-y-6">
                            <!-- Logo Upload -->
                            <div class="space-y-2">
                                <Label>Site Logo</Label>
                                <div class="flex flex-col items-start gap-4 sm:flex-row">
                                    <div class="flex size-24 shrink-0 items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30">
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="Logo preview"
                                            class="size-full rounded-lg object-contain p-1"
                                        />
                                        <Image v-else class="size-8 text-muted-foreground" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="logoInput?.click()">
                                                <Upload class="mr-2 size-4" />
                                                Upload Logo
                                            </Button>
                                            <Button v-if="logoPreview" type="button" variant="ghost" size="sm" @click="clearLogo">
                                                <X class="mr-2 size-4" />
                                                Remove
                                            </Button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">Recommended: PNG or SVG, max 2MB</p>
                                        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                                        <p v-if="appearanceErrors.logo" class="text-sm text-destructive">{{ appearanceErrors.logo }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon Upload -->
                            <div class="space-y-2">
                                <Label>Favicon</Label>
                                <div class="flex flex-col items-start gap-4 sm:flex-row">
                                    <div class="flex size-16 shrink-0 items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30">
                                        <img
                                            v-if="faviconPreview"
                                            :src="faviconPreview"
                                            alt="Favicon preview"
                                            class="size-full rounded-lg object-contain p-1"
                                        />
                                        <Globe v-else class="size-6 text-muted-foreground" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="faviconInput?.click()">
                                                <Upload class="mr-2 size-4" />
                                                Upload Favicon
                                            </Button>
                                            <Button v-if="faviconPreview" type="button" variant="ghost" size="sm" @click="clearFavicon">
                                                <X class="mr-2 size-4" />
                                                Remove
                                            </Button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">Recommended: ICO or PNG, max 1MB</p>
                                        <input ref="faviconInput" type="file" accept="image/*" class="hidden" @change="onFaviconChange" />
                                        <p v-if="appearanceErrors.favicon" class="text-sm text-destructive">{{ appearanceErrors.favicon }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Color Theme -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold">Color Theme</Label>
                                <p class="text-sm text-muted-foreground">Choose a primary color theme for your application.</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="(preset, key) in themePresets"
                                        :key="key"
                                        type="button"
                                        class="group relative flex size-10 items-center justify-center rounded-full border-2 transition-all hover:scale-110"
                                        :class="selectedTheme === key ? 'border-foreground' : 'border-transparent'"
                                        :style="{ backgroundColor: preset.color }"
                                        :title="preset.label"
                                        @click="selectedTheme = key as string"
                                    >
                                        <Check v-if="selectedTheme === key" class="size-4 text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                    </button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Selected: <span class="font-medium text-foreground">{{ themePresets[selectedTheme]?.label || 'Zinc' }}</span>
                                </p>
                            </div>

                            <LoadingButton :loading="appearanceProcessing">Save</LoadingButton>
                        </form>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="social">
                <SettingsCard title="Social Links" :processing="socialForm.processing" @submit="saveGroup('social', socialForm)">
                    <FormFields :schema="socialSchema" :form="socialForm" />
                </SettingsCard>
            </TabsContent>

            <TabsContent value="maintenance">
                <SettingsCard title="Maintenance Mode" :processing="maintenanceForm.processing" :columns="1" @submit="saveGroup('maintenance', maintenanceForm)">
                    <FormFields :schema="maintenanceSchema" :form="maintenanceForm" />
                </SettingsCard>
            </TabsContent>

            <TabsContent value="homepage">
                <form @submit.prevent="saveHomepage" class="space-y-6">
                    <!-- General -->
                    <Card>
                        <CardHeader>
                            <CardTitle>General</CardTitle>
                            <CardDescription>Enable or disable the public homepage.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="hp-enabled" v-model="hpEnabled" class="size-4 rounded border-border" />
                                <Label for="hp-enabled">Enable homepage (when disabled, "/" redirects to login)</Label>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Hero Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Hero Section</CardTitle>
                            <CardDescription>The main banner area of your homepage.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-hero-title">Hero Title</Label>
                                    <Input id="hp-hero-title" v-model="hpHeroTitle" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="hp-hero-subtitle">Hero Subtitle</Label>
                                    <textarea id="hp-hero-subtitle" v-model="hpHeroSubtitle" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="hp-hero-cta-text">CTA Button Text</Label>
                                    <Input id="hp-hero-cta-text" v-model="hpHeroCtaText" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="hp-hero-cta-url">CTA Button URL</Label>
                                    <Input id="hp-hero-cta-url" v-model="hpHeroCtaUrl" />
                                </div>
                            </div>

                            <!-- Hero Image Upload -->
                            <div class="space-y-2">
                                <Label>Hero Image</Label>
                                <div class="flex flex-col items-start gap-4 sm:flex-row">
                                    <div class="flex h-24 w-40 shrink-0 items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30">
                                        <img
                                            v-if="heroImagePreview"
                                            :src="heroImagePreview"
                                            alt="Hero image preview"
                                            class="size-full rounded-lg object-cover"
                                        />
                                        <Image v-else class="size-8 text-muted-foreground" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="heroImageInput?.click()">
                                                <Upload class="mr-2 size-4" />
                                                Upload Image
                                            </Button>
                                            <Button v-if="heroImagePreview" type="button" variant="ghost" size="sm" @click="clearHeroImage">
                                                <X class="mr-2 size-4" />
                                                Remove
                                            </Button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">Recommended: 1920x1080, max 4MB</p>
                                        <input ref="heroImageInput" type="file" accept="image/*" class="hidden" @change="onHeroImageChange" />
                                        <p v-if="homepageErrors.hero_image" class="text-sm text-destructive">{{ homepageErrors.hero_image }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Navbar -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Navbar</CardTitle>
                            <CardDescription>Navigation bar links and CTA button.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-navbar-cta-text">CTA Button Text</Label>
                                    <Input id="hp-navbar-cta-text" v-model="hpNavbarCtaText" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="hp-navbar-cta-url">CTA Button URL</Label>
                                    <Input id="hp-navbar-cta-url" v-model="hpNavbarCtaUrl" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Navigation Links</Label>
                                <RepeaterField
                                    v-model="hpNavbarLinks"
                                    :sub-schema="navLinkSchema"
                                    add-label="Add Link"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Features -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Features Section</CardTitle>
                            <CardDescription>Highlight your product's key features.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="hp-features-enabled" v-model="hpFeaturesEnabled" class="size-4 rounded border-border" />
                                <Label for="hp-features-enabled">Show features section</Label>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-features-title">Section Title</Label>
                                    <Input id="hp-features-title" v-model="hpFeaturesTitle" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="hp-features-subtitle">Section Subtitle</Label>
                                    <textarea id="hp-features-subtitle" v-model="hpFeaturesSubtitle" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Features</Label>
                                <RepeaterField
                                    v-model="hpFeatures"
                                    :sub-schema="featureSchema"
                                    add-label="Add Feature"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Testimonials -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Testimonials Section</CardTitle>
                            <CardDescription>Social proof from your users.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="hp-testimonials-enabled" v-model="hpTestimonialsEnabled" class="size-4 rounded border-border" />
                                <Label for="hp-testimonials-enabled">Show testimonials section</Label>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-testimonials-title">Section Title</Label>
                                    <Input id="hp-testimonials-title" v-model="hpTestimonialsTitle" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="hp-testimonials-subtitle">Section Subtitle</Label>
                                    <textarea id="hp-testimonials-subtitle" v-model="hpTestimonialsSubtitle" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Testimonials</Label>
                                <RepeaterField
                                    v-model="hpTestimonials"
                                    :sub-schema="testimonialSchema"
                                    add-label="Add Testimonial"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Pricing -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Pricing Section</CardTitle>
                            <CardDescription>Display your pricing plans.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="hp-pricing-enabled" v-model="hpPricingEnabled" class="size-4 rounded border-border" />
                                <Label for="hp-pricing-enabled">Show pricing section</Label>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-pricing-title">Section Title</Label>
                                    <Input id="hp-pricing-title" v-model="hpPricingTitle" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="hp-pricing-subtitle">Section Subtitle</Label>
                                    <textarea id="hp-pricing-subtitle" v-model="hpPricingSubtitle" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Pricing Plans</Label>
                                <RepeaterField
                                    v-model="hpPricingPlans"
                                    :sub-schema="pricingSchema"
                                    add-label="Add Plan"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- CTA Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle>CTA Section</CardTitle>
                            <CardDescription>Call-to-action banner near the footer.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="hp-cta-enabled" v-model="hpCtaEnabled" class="size-4 rounded border-border" />
                                <Label for="hp-cta-enabled">Show CTA section</Label>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="hp-cta-title">Title</Label>
                                    <Input id="hp-cta-title" v-model="hpCtaTitle" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="hp-cta-subtitle">Subtitle</Label>
                                    <textarea id="hp-cta-subtitle" v-model="hpCtaSubtitle" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="hp-cta-btn-text">Button Text</Label>
                                    <Input id="hp-cta-btn-text" v-model="hpCtaButtonText" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="hp-cta-btn-url">Button URL</Label>
                                    <Input id="hp-cta-btn-url" v-model="hpCtaButtonUrl" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Footer -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Footer</CardTitle>
                            <CardDescription>Footer text and links.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="hp-footer-text">Footer Text</Label>
                                <textarea id="hp-footer-text" v-model="hpFooterText" rows="2" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            </div>
                            <div class="space-y-2">
                                <Label>Footer Links</Label>
                                <RepeaterField
                                    v-model="hpFooterLinks"
                                    :sub-schema="footerLinkSchema"
                                    add-label="Add Link"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <LoadingButton :loading="homepageProcessing">Save Homepage Settings</LoadingButton>
                </form>
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
