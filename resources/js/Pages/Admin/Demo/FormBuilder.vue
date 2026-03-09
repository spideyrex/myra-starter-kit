<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormFields from '@/components/admin/FormFields.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import {
    Section,
    Grid,
    Tabs,
    Tab,
    Fieldset,
    Flex,
    Wizard,
    WizardStep,
    Callout,
    TextInput,
    Select,
    Textarea,
    Toggle,
    Checkbox,
    DatePicker,
    DateTimePicker,
    Radio,
    ColorPicker,
    FileUpload,
    RichEditor,
    Repeater,
    Slider,
    NumberField,
    PinInput,
    TagsInput,
    ToggleGroupField,
    CalendarPicker,
    type SchemaItem,
} from '@/composables/useFormSchema';
import {
    ArrowLeft,
    User,
    Settings,
    Shield,
    Palette,
    Calendar,
    FileText,
    AlertTriangle,
} from 'lucide-vue-next';

const form = ref({
    // Text inputs
    full_name: 'John Doe',
    email: 'john@example.com',
    password: '',
    phone: '+1-555-0100',
    website: 'https://example.com',
    age: '30',
    // Select
    country: 'us',
    role: 'developer',
    // Textarea
    bio: 'Full-stack developer with 10 years of experience.',
    // Toggle & Checkbox
    notifications_enabled: true,
    dark_mode: false,
    agree_terms: true,
    // Dates
    birth_date: '1995-06-15',
    meeting_time: '',
    // Radio
    gender: 'male',
    plan: 'pro',
    // Color
    brand_color: '#3b82f6',
    // File
    avatar: null,
    documents: null,
    // Rich text
    description: '<p>This is a <strong>rich text</strong> field powered by TipTap.</p>',
    // Repeater
    social_links: [
        { platform: 'twitter', url: 'https://twitter.com/johndoe' },
        { platform: 'github', url: 'https://github.com/johndoe' },
    ],
    // New field types
    volume: 50,
    quantity: 1,
    price: 0,
    otp_code: '',
    tags: [] as string[],
    text_align: 'left',
    event_date: '',
    // Wizard
    company_name: 'Acme Corp',
    company_address: '123 Main St',
    company_city: 'San Francisco',
    admin_email: 'admin@acme.com',
    admin_name: 'Admin User',
    smtp_host: 'smtp.example.com',
    smtp_port: '587',
    errors: {} as Record<string, string>,
});

const schema: SchemaItem[] = [
    // --- Section: All Text Field Types ---
    Section.make('Text Input Variants')
        .description('All text-based field types available in the form builder.')
        .icon(FileText)
        .columns(3)
        .schema([
            TextInput.make('full_name')
                .label('Full Name')
                .placeholder('Enter your name')
                .required()
                .hint('Your legal name'),
            TextInput.make('email')
                .email()
                .required()
                .placeholder('you@example.com'),
            TextInput.make('password')
                .password()
                .placeholder('Create a password')
                .hint('Minimum 8 characters'),
            TextInput.make('phone')
                .tel()
                .placeholder('+1-555-0000'),
            TextInput.make('website')
                .url()
                .placeholder('https://'),
            TextInput.make('age')
                .numeric()
                .placeholder('0')
                .hint('Must be 18 or older'),
        ]),

    // --- Section: Select & Choice Fields ---
    Section.make('Selection Fields')
        .description('Dropdowns, radio buttons, and toggle switches.')
        .icon(Settings)
        .columns(2)
        .collapsible()
        .schema([
            Select.make('country')
                .label('Country')
                .options({
                    us: 'United States',
                    uk: 'United Kingdom',
                    ca: 'Canada',
                    au: 'Australia',
                    de: 'Germany',
                    fr: 'France',
                    jp: 'Japan',
                })
                .required(),
            Select.make('role')
                .options({
                    developer: 'Developer',
                    designer: 'Designer',
                    manager: 'Manager',
                    qa: 'QA Engineer',
                    devops: 'DevOps',
                }),
            Radio.make('gender')
                .options({
                    male: 'Male',
                    female: 'Female',
                    other: 'Other',
                    prefer_not: 'Prefer not to say',
                })
                .inline(),
            Radio.make('plan')
                .label('Subscription Plan')
                .options({
                    free: 'Free',
                    pro: 'Pro ($9/mo)',
                    enterprise: 'Enterprise ($29/mo)',
                }),
        ]),

    // --- Tabs: Toggle & Date Fields ---
    Tabs.make([
        Tab.make('Toggles & Checkboxes')
            .icon(Shield)
            .schema([
                Toggle.make('notifications_enabled').label('Enable Notifications'),
                Toggle.make('dark_mode').label('Dark Mode'),
                Checkbox.make('agree_terms').label('I agree to the Terms of Service').required(),
            ]),
        Tab.make('Date & Time')
            .icon(Calendar)
            .badge('2')
            .schema([
                DatePicker.make('birth_date')
                    .label('Date of Birth')
                    .minDate('1900-01-01')
                    .maxDate('2010-12-31'),
                DateTimePicker.make('meeting_time')
                    .label('Meeting Time')
                    .placeholder('Select date and time'),
            ]),
        Tab.make('Color & Files')
            .icon(Palette)
            .schema([
                ColorPicker.make('brand_color').label('Brand Color'),
                FileUpload.make('avatar')
                    .label('Profile Photo')
                    .accept('image/*')
                    .maxSize(5)
                    .hint('PNG, JPG up to 5MB'),
                FileUpload.make('documents')
                    .label('Documents')
                    .multiple()
                    .accept('.pdf,.doc,.docx')
                    .hint('PDF or Word documents'),
            ]),
    ]),

    // --- Textarea ---
    Fieldset.make('Text Areas')
        .columns(1)
        .schema([
            Textarea.make('bio')
                .label('Biography')
                .rows(3)
                .placeholder('Tell us about yourself...'),
        ]),

    // --- Rich Text Editor ---
    Grid.make(1).schema([
        RichEditor.make('description')
            .label('Rich Text Description')
            .toolbar(['bold', 'italic', 'strike', '|', 'h1', 'h2', '|', 'bulletList', 'orderedList', '|', 'blockquote', 'code', '|', 'link', '|', 'undo', 'redo'])
            .editorPlaceholder('Write a rich description...')
            .colSpan(1),
    ]),

    // --- Repeater ---
    Section.make('Repeater Field')
        .description('Dynamic rows with add/remove, reordering, and collapsible support.')
        .columns(1)
        .schema([
            Repeater.make('social_links')
                .label('Social Links')
                .schema([
                    Select.make('platform').options({
                        twitter: 'Twitter / X',
                        github: 'GitHub',
                        linkedin: 'LinkedIn',
                        facebook: 'Facebook',
                        instagram: 'Instagram',
                        youtube: 'YouTube',
                    }).required(),
                    TextInput.make('url').url().placeholder('https://...').required(),
                ])
                .minItems(1)
                .maxItems(6)
                .collapsible()
                .addLabel('Add Social Link'),
        ]),

    // --- New Field Types (shadcn-vue) ---
    Section.make('New Field Types (shadcn-vue)')
        .description('Slider, NumberField, PinInput, TagsInput, ToggleGroup, CalendarPicker')
        .columns(2)
        .schema([
            Slider.make('volume')
                .label('Volume')
                .min(0)
                .max(100)
                .step(5)
                .showValue()
                .hint('Drag to adjust volume'),
            NumberField.make('quantity')
                .label('Quantity')
                .min(1)
                .max(99),
            NumberField.make('price')
                .label('Price')
                .min(0)
                .step(0.01)
                .currency('USD'),
            PinInput.make('otp_code')
                .label('OTP Code')
                .otp()
                .length(6)
                .hint('Enter the 6-digit code')
                .colSpan(2),
            TagsInput.make('tags')
                .label('Tags')
                .maxTags(5)
                .tagPlaceholder('Type and press Enter')
                .hint('Up to 5 tags'),
            ToggleGroupField.make('text_align')
                .label('Text Alignment')
                .options({
                    left: 'Left',
                    center: 'Center',
                    right: 'Right',
                }),
            CalendarPicker.make('event_date')
                .label('Event Date')
                .hint('Pick a date from the calendar'),
        ]),

    // --- Callout ---
    Callout.make('Important Notice')
        .description('Callout blocks can include fields and come in 4 variants: info, warning, success, and danger.')
        .warning()
        .icon(AlertTriangle),

    // --- Wizard ---
    Wizard.make([
        WizardStep.make('Company Info')
            .description('Basic details')
            .icon(User)
            .schema([
                TextInput.make('company_name').label('Company Name').required(),
                TextInput.make('company_address').label('Address'),
                TextInput.make('company_city').label('City'),
            ]),
        WizardStep.make('Admin Account')
            .description('First user')
            .icon(Shield)
            .schema([
                TextInput.make('admin_name').label('Admin Name').required(),
                TextInput.make('admin_email').label('Admin Email').email().required(),
            ]),
        WizardStep.make('Email Config')
            .description('SMTP settings')
            .icon(Settings)
            .schema([
                TextInput.make('smtp_host').label('SMTP Host'),
                TextInput.make('smtp_port').label('SMTP Port').numeric(),
            ]),
    ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Form Builder' }]">
        <Head title="Form Builder Demo" />

        <PageHeader title="Form Builder" description="Complete showcase of all field types and layout components.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form class="mt-6 space-y-6" @submit.prevent>
            <FormFields :schema="schema" :form="form" />

            <div class="flex justify-end">
                <LoadingButton :loading="false">
                    Submit (Demo)
                </LoadingButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
