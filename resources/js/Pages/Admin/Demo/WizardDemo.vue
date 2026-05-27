<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormFields from '@/components/admin/FormFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Wizard,
    WizardStep,
    TextInput,
    Select,
    Textarea,
    Checkbox,
    Toggle,
    FileUpload,
    TagsInput,
    Slider,
    Callout,
    type ValidationRule,
} from '@/composables/useFormSchema';
import { useExcelExport } from '@/composables/useExcelExport';
import {
    ArrowLeft,
    User,
    Camera,
    Settings2,
    Briefcase,
    ClipboardCheck,
    Image,
    FileSpreadsheet,
    Languages,
    ShieldCheck,
    Code2,
    Download,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    profile_photo: null as File | null,
    department: '',
    job_title: '',
    language: 'en',
    email_notifications: true,
    dark_mode: false,
    skills: [] as string[],
    experience_years: 3,
    bio: '',
    agree_terms: false,
});

const emailValidator: ValidationRule = (value) => {
    if (!value) return true;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(value) ? true : 'Please enter a valid email address';
};

const phoneValidator: ValidationRule = (value) => {
    if (!value) return true;
    const re = /^[+]?[\d\s()-]{7,}$/;
    return re.test(value) ? true : 'Please enter a valid phone number';
};

const schema = [
    Wizard.make([
        WizardStep.make('Personal Details')
            .description('Basic info')
            .icon(User)
            .schema([
                TextInput.make('first_name').label('First Name').required().placeholder('Jane'),
                TextInput.make('last_name').label('Last Name').required().placeholder('Smith'),
                TextInput.make('email').email().required().placeholder('jane@company.com'),
                TextInput.make('phone').tel().label('Phone Number').placeholder('+60 12-345 6789'),
            ])
            .validate({
                email: [emailValidator],
                phone: [phoneValidator],
            }),

        WizardStep.make('Profile Photo')
            .description('Upload & crop')
            .icon(Camera)
            .schema([
                FileUpload.make('profile_photo')
                    .label('Profile Photo')
                    .imageCrop()
                    .imageAspectRatio(1)
                    .hint('Upload a square photo. You can crop after selecting.')
                    .colSpan(2),
                Select.make('department').label('Department').required().options({
                    engineering: 'Engineering',
                    design: 'Design',
                    marketing: 'Marketing',
                    sales: 'Sales',
                    hr: 'Human Resources',
                    finance: 'Finance',
                }),
                TextInput.make('job_title').label('Job Title').placeholder('Senior Developer'),
            ]),

        WizardStep.make('Preferences')
            .description('Language & settings')
            .icon(Settings2)
            .schema([
                Select.make('language').label('Preferred Language').options({
                    en: 'English',
                    ms: 'Bahasa Melayu',
                    zh: '中文 (Chinese)',
                }).colSpan(2),
                Toggle.make('email_notifications').label('Email Notifications').hint('Receive updates about your onboarding progress'),
                Toggle.make('dark_mode').label('Dark Mode').hint('Use dark theme for the dashboard'),
                Callout.make('Internationalization')
                    .description('This application supports multiple languages. Your preference will be saved and applied across all pages. Translations are loaded on demand for optimal performance.')
                    .info(),
            ]),

        WizardStep.make('Skills & Export')
            .description('Skills & data')
            .icon(Briefcase)
            .schema([
                TagsInput.make('skills').label('Skills').maxTags(8).tagPlaceholder('Type a skill and press Enter').colSpan(2),
                Slider.make('experience_years').label('Years of Experience').min(0).max(30).step(1).showValue(),
                Textarea.make('bio').label('Short Bio').placeholder('Tell us about your background and expertise...').rows(3).colSpan(2),
            ]),

        WizardStep.make('Review & Confirm')
            .description('Final check')
            .icon(ClipboardCheck)
            .columns(1)
            .schema([
                Checkbox.make('agree_terms').label('I agree to the employee handbook, code of conduct, and data privacy policy').required(),
            ]),
    ]),
];

const { exportToXlsx, exporting } = useExcelExport();

function handleExport() {
    exportToXlsx({
        filename: 'onboarding-data',
        sheetName: 'Employee',
        columns: [
            { header: 'Field', key: 'field', width: 20 },
            { header: 'Value', key: 'value', width: 40 },
        ],
        data: [
            { field: 'First Name', value: form.first_name || '(empty)' },
            { field: 'Last Name', value: form.last_name || '(empty)' },
            { field: 'Email', value: form.email || '(empty)' },
            { field: 'Phone', value: form.phone || '(empty)' },
            { field: 'Department', value: form.department || '(empty)' },
            { field: 'Job Title', value: form.job_title || '(empty)' },
            { field: 'Language', value: form.language },
            { field: 'Email Notifications', value: form.email_notifications ? 'Yes' : 'No' },
            { field: 'Dark Mode', value: form.dark_mode ? 'Yes' : 'No' },
            { field: 'Skills', value: form.skills.join(', ') || '(none)' },
            { field: 'Experience', value: `${form.experience_years} years` },
            { field: 'Bio', value: form.bio || '(empty)' },
        ],
    });
    toast.success('Export started!', { description: 'Your .xlsx file will download shortly.' });
}

const formDataPreview = computed(() => {
    const { first_name, last_name, email, phone, department, job_title, language, email_notifications, dark_mode, skills, experience_years, bio, agree_terms } = form;
    return JSON.stringify({ first_name, last_name, email, phone, department, job_title, language, email_notifications, dark_mode, skills, experience_years, bio, agree_terms }, null, 2);
});

const reviewFields = computed(() => [
    { label: 'Name', value: [form.first_name, form.last_name].filter(Boolean).join(' ') || '-' },
    { label: 'Email', value: form.email || '-' },
    { label: 'Phone', value: form.phone || '-' },
    { label: 'Department', value: form.department || '-' },
    { label: 'Job Title', value: form.job_title || '-' },
    { label: 'Language', value: { en: 'English', ms: 'Bahasa Melayu', zh: '中文' }[form.language] || form.language },
    { label: 'Notifications', value: form.email_notifications ? 'Enabled' : 'Disabled' },
    { label: 'Dark Mode', value: form.dark_mode ? 'Enabled' : 'Disabled' },
    { label: 'Skills', value: form.skills.length > 0 ? form.skills.join(', ') : '-' },
    { label: 'Experience', value: `${form.experience_years} years` },
    { label: 'Bio', value: form.bio || '-' },
]);

function handleSubmit() {
    if (!form.agree_terms) {
        form.errors.agree_terms = 'You must agree to the terms';
        return;
    }
    toast.success('Onboarding complete!', {
        description: `Welcome aboard, ${form.first_name}! Your profile has been set up.`,
    });
}

const codeSnippet = `WizardStep.make('Personal Details')
  .schema([
    TextInput.make('email')
      .email().required(),
  ])
  .validate({
    email: [(value) => {
      const re = /^[^\\s@]+@[^\\s@]+$/;
      return re.test(value)
        ? true
        : 'Invalid email';
    }],
  })`;
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Wizard Demo' }]">
        <Head title="Wizard Demo — Employee Onboarding" />

        <PageHeader title="Employee Onboarding Wizard" description="A 5-step wizard showcasing per-step validation, image editor, i18n awareness, XLSX export, and review gating.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Main wizard column -->
            <div class="lg:col-span-2">
                <Card>
                    <CardContent class="pt-6">
                        <form @submit.prevent="handleSubmit">
                            <FormFields :schema="schema" :form="form" />

                            <!-- Review summary (shown inline for the last step) -->
                            <div class="mt-4 space-y-4">
                                <!-- Review grid - only visible on final step via manual check -->
                                <template v-if="form.agree_terms !== undefined">
                                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 rounded-lg border bg-muted/30 p-4">
                                        <div v-for="field in reviewFields" :key="field.label">
                                            <p class="text-xs font-medium text-muted-foreground">{{ field.label }}</p>
                                            <p class="text-sm font-medium">{{ field.value }}</p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Export + Submit row -->
                            <div class="mt-6 flex items-center justify-between">
                                <Button type="button" variant="outline" :disabled="exporting" @click="handleExport">
                                    <Download class="mr-2 size-4" />
                                    {{ exporting ? 'Exporting...' : 'Export My Data' }}
                                </Button>
                                <Button type="submit">
                                    <ClipboardCheck class="mr-2 size-4" />
                                    Complete Onboarding
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Right sidebar -->
            <div class="space-y-4">
                <!-- Features Demonstrated -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm">Features Demonstrated</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2 pt-0">
                        <Badge variant="secondary" class="gap-1">
                            <ShieldCheck class="size-3" />
                            Per-Step Validation
                        </Badge>
                        <Badge variant="secondary" class="gap-1">
                            <Image class="size-3" />
                            Image Editor
                        </Badge>
                        <Badge variant="secondary" class="gap-1">
                            <FileSpreadsheet class="size-3" />
                            XLSX Export
                        </Badge>
                        <Badge variant="secondary" class="gap-1">
                            <Languages class="size-3" />
                            i18n Awareness
                        </Badge>
                    </CardContent>
                </Card>

                <!-- How It Works -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm flex items-center gap-1.5">
                            <Code2 class="size-4" />
                            How It Works
                        </CardTitle>
                        <CardDescription class="text-xs">Use <code class="rounded bg-muted px-1 font-mono">.validate()</code> to add custom rules per step</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <pre class="overflow-x-auto rounded-md bg-muted p-3 text-xs leading-relaxed"><code>{{ codeSnippet }}</code></pre>
                    </CardContent>
                </Card>

                <!-- Live Form Data -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm">Live Form Data</CardTitle>
                        <CardDescription class="text-xs">Updates as you fill in the wizard</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <pre class="max-h-72 overflow-auto rounded-md bg-muted p-3 text-xs leading-relaxed"><code>{{ formDataPreview }}</code></pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
