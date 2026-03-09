<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FormFields, SettingsCard } from '@/components/admin';
import { useConfirm } from '@/composables/useConfirm';
import { useFirebaseMessaging } from '@/composables/useFirebaseMessaging';
import { TextInput, Toggle } from '@/composables/useFormSchema';
import { Upload, Send, Smartphone, CheckCircle, XCircle, Bell } from 'lucide-vue-next';

interface FirebaseSettingsData {
    enabled: boolean;
    service_account_path: string | null;
    api_key: string | null;
    auth_domain: string | null;
    project_id: string | null;
    storage_bucket: string | null;
    messaging_sender_id: string | null;
    app_id: string | null;
    vapid_key: string | null;
}

const props = defineProps<{
    settings: FirebaseSettingsData;
    hasServiceAccount: boolean;
    webConfig: Record<string, string> | null;
}>();

const { permissionStatus, currentToken, isSupported, requestPermission } = useFirebaseMessaging();

const webConfigSchema = [
    Toggle.make('enabled').label('Enable Firebase Push Notifications').hint('Enable or disable push notifications globally.'),
    TextInput.make('api_key').label('API Key').placeholder('AIzaSy...'),
    TextInput.make('auth_domain').label('Auth Domain').placeholder('your-project.firebaseapp.com'),
    TextInput.make('project_id').label('Project ID').placeholder('your-project-id'),
    TextInput.make('storage_bucket').label('Storage Bucket').placeholder('your-project.appspot.com'),
    TextInput.make('messaging_sender_id').label('Messaging Sender ID').placeholder('123456789'),
    TextInput.make('app_id').label('App ID').placeholder('1:123456789:web:abc123'),
    TextInput.make('vapid_key').label('VAPID Key').placeholder('BNx...').hint('Web Push certificate key pair from Firebase Console > Cloud Messaging.'),
];

const form = useForm({
    enabled: props.settings.enabled,
    api_key: props.settings.api_key || '',
    auth_domain: props.settings.auth_domain || '',
    project_id: props.settings.project_id || '',
    storage_bucket: props.settings.storage_bucket || '',
    messaging_sender_id: props.settings.messaging_sender_id || '',
    app_id: props.settings.app_id || '',
    vapid_key: props.settings.vapid_key || '',
    service_account: null as File | null,
});

const testForm = useForm({
    token: '',
});

const { confirm } = useConfirm();
const serviceAccountFile = ref<HTMLInputElement | null>(null);

async function saveSettings() {
    const confirmed = await confirm({
        title: 'Save Firebase Settings',
        description: 'Are you sure you want to update the Firebase configuration?',
        confirmText: 'Save',
    });
    if (!confirmed) return;

    router.post(route('admin.firebase-settings.update'), {
        _method: 'PUT',
        enabled: form.enabled,
        api_key: form.api_key,
        auth_domain: form.auth_domain,
        project_id: form.project_id,
        storage_bucket: form.storage_bucket,
        messaging_sender_id: form.messaging_sender_id,
        app_id: form.app_id,
        vapid_key: form.vapid_key,
        service_account: form.service_account,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.service_account = null;
            if (serviceAccountFile.value) {
                serviceAccountFile.value.value = '';
            }
        },
    });
}

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.service_account = target.files?.[0] || null;
}

async function enablePush() {
    const token = await requestPermission();
    if (token) {
        testForm.token = token;
    }
}

async function sendTestPush() {
    const confirmed = await confirm({
        title: 'Send Test Push',
        description: 'Send a test push notification to this browser?',
        confirmText: 'Send',
    });
    if (confirmed) testForm.post(route('admin.firebase-settings.test'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Push Notifications' }, { label: 'Firebase Settings' }]">
        <Head title="Firebase Settings" />
        <PageHeader title="Firebase Settings" description="Configure Firebase Cloud Messaging for push notifications." />

        <div class="mt-6 max-w-2xl space-y-6">
            <!-- Status Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Status</CardTitle>
                    <CardDescription>Current Firebase push notification configuration status.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Firebase</span>
                            <StatusBadge :status="settings.enabled ? 'active' : 'suspended'" :label="settings.enabled ? 'Enabled' : 'Disabled'" />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Service Account</span>
                            <div class="flex items-center gap-1.5">
                                <CheckCircle v-if="hasServiceAccount" class="size-4 text-success" />
                                <XCircle v-else class="size-4 text-muted-foreground" />
                                <span class="text-sm" :class="hasServiceAccount ? 'text-success' : 'text-muted-foreground'">
                                    {{ hasServiceAccount ? 'Uploaded' : 'Not configured' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Web Config</span>
                            <div class="flex items-center gap-1.5">
                                <CheckCircle v-if="webConfig" class="size-4 text-success" />
                                <XCircle v-else class="size-4 text-muted-foreground" />
                                <span class="text-sm" :class="webConfig ? 'text-success' : 'text-muted-foreground'">
                                    {{ webConfig ? 'Configured' : 'Not configured' }}
                                </span>
                            </div>
                        </div>
                        <div v-if="isSupported" class="flex items-center justify-between">
                            <span class="text-sm font-medium">Browser Permission</span>
                            <div class="flex items-center gap-1.5">
                                <CheckCircle v-if="permissionStatus === 'granted'" class="size-4 text-success" />
                                <XCircle v-else-if="permissionStatus === 'denied'" class="size-4 text-destructive" />
                                <Bell v-else class="size-4 text-muted-foreground" />
                                <span class="text-sm capitalize" :class="{
                                    'text-success': permissionStatus === 'granted',
                                    'text-destructive': permissionStatus === 'denied',
                                    'text-muted-foreground': permissionStatus === 'default',
                                }">
                                    {{ permissionStatus }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Service Account Upload Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Service Account</CardTitle>
                    <CardDescription>Upload your Firebase service account JSON file. Download it from Firebase Console > Project Settings > Service Accounts.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <Label for="service_account">Service Account JSON</Label>
                        <Input
                            id="service_account"
                            ref="serviceAccountFile"
                            type="file"
                            accept=".json"
                            @change="handleFileChange"
                        />
                        <p v-if="settings.service_account_path" class="text-sm text-muted-foreground">
                            Current: {{ settings.service_account_path }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Web Configuration Card -->
            <SettingsCard
                title="Web Configuration"
                description="Configure Firebase web SDK settings for browser push notifications."
                :processing="form.processing"
                submit-text="Save Settings"
                @submit="saveSettings"
            >
                <FormFields :schema="webConfigSchema" :form="form" />
            </SettingsCard>

            <!-- Test Push Card -->
            <Card v-if="settings.enabled && hasServiceAccount">
                <CardHeader>
                    <CardTitle>Test Push Notification</CardTitle>
                    <CardDescription>Send a test push notification to verify your configuration.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="isSupported && permissionStatus !== 'granted'">
                        <Button type="button" @click="enablePush" variant="outline">
                            <Smartphone class="mr-2 size-4" />
                            Enable Push for This Browser
                        </Button>
                    </div>

                    <div v-if="permissionStatus === 'denied'" class="text-sm text-destructive">
                        Push notifications are blocked. Please enable them in your browser settings.
                    </div>

                    <form v-if="permissionStatus === 'granted'" @submit.prevent="sendTestPush" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="test_token">FCM Token</Label>
                            <Input
                                id="test_token"
                                v-model="testForm.token"
                                placeholder="FCM token (auto-filled when push is enabled)"
                                required
                            />
                        </div>
                        <LoadingButton :loading="testForm.processing">
                            <Send class="mr-2 size-4" />
                            Send Test
                        </LoadingButton>
                        <p v-if="testForm.errors.token" class="text-sm text-destructive">{{ testForm.errors.token }}</p>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
