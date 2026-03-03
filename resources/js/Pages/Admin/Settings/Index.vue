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
import LoadingButton from '@/components/LoadingButton.vue';
import { useConfirm } from '@/composables/useConfirm';
import { TextInput, Textarea, Toggle } from '@/composables/useFormSchema';
import { Upload, X, Image, Globe } from 'lucide-vue-next';

const props = defineProps<{
    general: Record<string, any>;
    seo: Record<string, any>;
    appearance: Record<string, any>;
    social: Record<string, any>;
    maintenance: Record<string, any>;
}>();

const generalForm = useForm({ ...props.general });
const seoForm = useForm({ ...props.seo });
const socialForm = useForm({ ...props.social });
const maintenanceForm = useForm({ ...props.maintenance });

// Appearance state (managed manually for file uploads)
const appearanceProcessing = ref(false);
const primaryColor = ref(props.appearance.primary_color || '#000000');
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

const generalSchema = [
    TextInput.make('site_name'),
    TextInput.make('admin_email').email(),
];

const generalAfterSchema = [
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
    data.append('primary_color', primaryColor.value);

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
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'Settings' }]">
        <Head title="Settings" />
        <PageHeader title="Settings" description="Manage your application settings." />

        <Tabs default-value="general" class="mt-6">
            <TabsList>
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="seo">SEO</TabsTrigger>
                <TabsTrigger value="appearance">Appearance</TabsTrigger>
                <TabsTrigger value="social">Social</TabsTrigger>
                <TabsTrigger value="maintenance">Maintenance</TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <SettingsCard title="General Settings" description="Basic application configuration." :processing="generalForm.processing" @submit="saveGroup('general', generalForm)">
                    <FormFields :schema="generalSchema" :form="generalForm" />

                    <template #after-fields>
                        <FormFields :schema="generalAfterSchema" :form="generalForm" />
                    </template>
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
                                <div class="flex items-start gap-4">
                                    <div class="flex size-24 items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30">
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
                                <div class="flex items-start gap-4">
                                    <div class="flex size-16 items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30">
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

                            <!-- Primary Color -->
                            <div class="space-y-2">
                                <Label>Primary Color</Label>
                                <div class="flex items-center gap-3">
                                    <input
                                        type="color"
                                        v-model="primaryColor"
                                        class="h-10 w-14 cursor-pointer rounded border border-border bg-background p-1"
                                    />
                                    <Input
                                        v-model="primaryColor"
                                        placeholder="#000000"
                                        class="max-w-[120px]"
                                    />
                                    <div class="size-10 rounded-md border" :style="{ backgroundColor: primaryColor }" />
                                </div>
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
        </Tabs>
    </AuthenticatedLayout>
</template>
