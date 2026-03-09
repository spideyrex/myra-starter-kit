<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormFields, SettingsCard } from '@/components/admin';
import { useConfirm } from '@/composables/useConfirm';
import { TextInput, Select } from '@/composables/useFormSchema';
import { Send } from 'lucide-vue-next';

const props = defineProps<{
    settings: {
        mail_mailer: string;
        mail_host: string | null;
        mail_port: number | null;
        mail_username: string | null;
        mail_encryption: string | null;
        mail_from_address: string | null;
        mail_from_name: string | null;
    };
}>();

const smtpSchema = [
    Select.make('mail_mailer').label('Mail Driver').options([
        { label: 'SMTP', value: 'smtp' },
        { label: 'Sendmail', value: 'sendmail' },
        { label: 'Mailgun', value: 'mailgun' },
        { label: 'Amazon SES', value: 'ses' },
        { label: 'Log (testing)', value: 'log' },
    ]),
    Select.make('mail_encryption').label('Encryption').options([
        { label: 'TLS', value: 'tls' },
        { label: 'SSL', value: 'ssl' },
        { label: 'None', value: '' },
    ]),
    TextInput.make('mail_host').label('SMTP Host').placeholder('smtp.example.com'),
    TextInput.make('mail_port').label('SMTP Port').numeric().placeholder('587'),
    TextInput.make('mail_username').label('Username'),
    TextInput.make('mail_password').label('Password').password().placeholder('Leave blank to keep current'),
    TextInput.make('mail_from_address').label('From Address').email().placeholder('noreply@example.com'),
    TextInput.make('mail_from_name').label('From Name').placeholder('App Name'),
];

const form = useForm({
    mail_mailer: props.settings.mail_mailer || 'smtp',
    mail_host: props.settings.mail_host || '',
    mail_port: props.settings.mail_port || 587,
    mail_username: props.settings.mail_username || '',
    mail_password: '',
    mail_encryption: props.settings.mail_encryption || 'tls',
    mail_from_address: props.settings.mail_from_address || '',
    mail_from_name: props.settings.mail_from_name || '',
});

const testForm = useForm({ email: '' });
const { confirm } = useConfirm();

async function saveSettings() {
    const confirmed = await confirm({ title: 'Save Email Settings', description: 'Are you sure you want to update the email configuration?', confirmText: 'Save' });
    if (confirmed) form.put(route('admin.email-settings.update'));
}

async function sendTest() {
    const confirmed = await confirm({ title: 'Send Test Email', description: `Send a test email to ${testForm.email || 'the specified address'}?`, confirmText: 'Send' });
    if (confirmed) testForm.post(route('admin.email-settings.test'));
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Email' }, { label: 'Settings' }]">
        <Head title="Email Settings" />
        <PageHeader title="Email Settings" description="Configure SMTP settings and send test emails." />

        <div class="mt-6 max-w-2xl space-y-6">
            <SettingsCard title="SMTP Configuration" description="Configure how your application sends emails." :processing="form.processing" submit-text="Save Settings" @submit="saveSettings">
                <FormFields :schema="smtpSchema" :form="form" />
            </SettingsCard>

            <Card>
                <CardHeader><CardTitle>Test Email</CardTitle></CardHeader>
                <CardContent>
                    <form @submit.prevent="sendTest" class="flex flex-col gap-2 sm:flex-row">
                        <Input v-model="testForm.email" type="email" placeholder="recipient@example.com" required class="sm:max-w-sm" />
                        <LoadingButton :loading="testForm.processing">
                            <Send class="mr-2 size-4" />Send Test
                        </LoadingButton>
                    </form>
                    <p v-if="testForm.errors.email" class="mt-2 text-sm text-destructive">{{ testForm.errors.email }}</p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
