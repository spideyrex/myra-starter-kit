<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormFields from '@/components/admin/FormFields.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    TextInput, Select, Toggle, Checkbox, Textarea, Section, Grid, Callout,
} from '@/composables/useFormSchema';
import { ArrowLeft, Eye, EyeOff } from 'lucide-vue-next';

const form = useForm({
    account_type: 'personal',
    name: '',
    company_name: '',
    tax_id: '',
    country: '',
    state: '',
    enable_notifications: false,
    notification_email: '',
    notification_frequency: 'daily',
    payment_method: 'card',
    card_number: '',
    bank_name: '',
    crypto_wallet: '',
    amount: 0,
    discount_code: '',
    notes: '',
    agree_terms: false,
});

const schema = [
    Section.make('Account Type').description('Choose your account type to see relevant fields').schema([
        Select.make('account_type').label('Account Type').required()
            .options({ personal: 'Personal', business: 'Business', nonprofit: 'Non-Profit' }),

        TextInput.make('name').label('Full Name').required(),

        TextInput.make('company_name').label('Company Name').required()
            .visibleWhen('account_type:business|nonprofit'),

        TextInput.make('tax_id').label('Tax ID / EIN').placeholder('XX-XXXXXXX')
            .visibleWhen('account_type:business'),
    ]),

    Section.make('Location').schema([
        Select.make('country').label('Country').options({
            us: 'United States', ca: 'Canada', uk: 'United Kingdom', de: 'Germany',
        }),

        Select.make('state').label('State / Province')
            .visibleWhen('country:us|ca')
            .options({
                ny: 'New York', ca_state: 'California', tx: 'Texas',
                on: 'Ontario', bc: 'British Columbia', qc: 'Quebec',
            }),
    ]),

    Section.make('Notifications').schema([
        Toggle.make('enable_notifications').label('Enable Email Notifications'),

        TextInput.make('notification_email').label('Notification Email').email()
            .placeholder('alerts@example.com')
            .visibleWhen('enable_notifications:true'),

        Select.make('notification_frequency').label('Frequency')
            .options({ instant: 'Instant', daily: 'Daily Digest', weekly: 'Weekly Summary' })
            .visibleWhen('enable_notifications:true'),
    ]),

    Section.make('Payment').description('Payment fields change based on method').schema([
        Select.make('payment_method').label('Payment Method')
            .options({ card: 'Credit Card', bank: 'Bank Transfer', crypto: 'Cryptocurrency' }),

        TextInput.make('card_number').label('Card Number').placeholder('4242 4242 4242 4242')
            .visibleWhen('payment_method:card'),

        TextInput.make('bank_name').label('Bank Name').placeholder('Enter your bank name')
            .visibleWhen('payment_method:bank'),

        TextInput.make('crypto_wallet').label('Wallet Address').placeholder('0x...')
            .visibleWhen('payment_method:crypto'),
    ]),

    Section.make('Order Details').schema([
        TextInput.make('amount').label('Order Amount ($)').numeric(),

        TextInput.make('discount_code').label('Discount Code').placeholder('SAVE20')
            .hint('Only available for orders over $100')
            .visibleWhen((form: Record<string, any>) => Number(form.amount) > 100),

        Textarea.make('notes').label('Special Instructions').rows(3)
            .visibleWhen('account_type:business'),
    ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Conditional Fields' },
    ]">
        <Head title="Conditional Fields Demo" />

        <PageHeader title="Conditional Field Visibility">
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
            <div class="lg:col-span-2 space-y-4">
                <div class="grid gap-4">
                    <FormFields :schema="schema" :form="form" />
                </div>
            </div>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">How It Works</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm text-muted-foreground">
                        <p>Fields use <code class="rounded bg-muted px-1 py-0.5 text-xs">.visibleWhen()</code> and <code class="rounded bg-muted px-1 py-0.5 text-xs">.hiddenWhen()</code> to show/hide based on form values.</p>
                        <div class="space-y-2">
                            <p class="font-medium text-foreground">String conditions:</p>
                            <ul class="list-disc pl-4 space-y-1">
                                <li><code class="text-xs">'type:business'</code> — equals</li>
                                <li><code class="text-xs">'!type:personal'</code> — not equals</li>
                                <li><code class="text-xs">'status:active|pending'</code> — OR values</li>
                                <li><code class="text-xs">'type:biz,country:us'</code> — AND</li>
                            </ul>
                        </div>
                        <div class="space-y-2">
                            <p class="font-medium text-foreground">Function conditions:</p>
                            <p><code class="text-xs">(form) =&gt; form.amount &gt; 100</code></p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Current Form Values</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre class="overflow-auto rounded-lg bg-muted p-3 text-xs">{{ JSON.stringify(form.data(), null, 2) }}</pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
