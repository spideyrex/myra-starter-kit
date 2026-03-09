<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FormFields from '@/components/admin/FormFields.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Section,
    TextInput,
    Select,
    Textarea,
    Repeater,
    type SchemaItem,
} from '@/composables/useFormSchema';
import { ArrowLeft, Users, ShoppingCart } from 'lucide-vue-next';

// Basic Contacts Repeater
const contactsForm = ref({
    contacts: [
        { name: 'John Doe', email: 'john@example.com', phone: '+1-555-0100' },
        { name: 'Jane Smith', email: 'jane@example.com', phone: '+1-555-0200' },
    ],
    errors: {} as Record<string, string>,
});

const contactsSchema: SchemaItem[] = [
    Section.make('Basic Contacts')
        .description('Simple repeater for managing contact information.')
        .icon(Users)
        .columns(1)
        .schema([
            Repeater.make('contacts')
                .label('Contact List')
                .schema([
                    TextInput.make('name').placeholder('Full Name').required(),
                    TextInput.make('email').email().placeholder('email@example.com'),
                    TextInput.make('phone').tel().placeholder('+1-555-0000'),
                ])
                .addLabel('Add Contact'),
        ]),
];

// Constrained & Collapsible Repeater
const teamForm = ref({
    members: [
        { name: 'Alice Johnson', role: 'developer', notes: 'Backend lead' },
        { name: 'Bob Williams', role: 'designer', notes: 'UI/UX specialist' },
        { name: 'Carol Davis', role: 'developer', notes: 'Frontend engineer' },
    ],
    errors: {} as Record<string, string>,
});

const teamSchema: SchemaItem[] = [
    Section.make('Team Members')
        .description('Constrained repeater with min 1, max 5, collapsible rows.')
        .columns(1)
        .schema([
            Repeater.make('members')
                .label('Team')
                .schema([
                    TextInput.make('name').placeholder('Member Name').required(),
                    Select.make('role').options({
                        developer: 'Developer',
                        designer: 'Designer',
                        manager: 'Manager',
                        qa: 'QA Engineer',
                    }).required(),
                    Textarea.make('notes').placeholder('Notes about this member...').rows(2),
                ])
                .minItems(1)
                .maxItems(5)
                .collapsible()
                .addLabel('Add Team Member'),
        ]),
];

// Invoice Line Items
const invoiceForm = ref({
    items: [
        { description: 'Web Development Services', quantity: '40', unit_price: '150', tax_rate: '10' },
        { description: 'UI Design Package', quantity: '1', unit_price: '2500', tax_rate: '10' },
    ],
    errors: {} as Record<string, string>,
});

const invoiceSchema: SchemaItem[] = [
    Section.make('Invoice Line Items')
        .description('Repeater with numeric fields — great for invoices, orders, and similar use cases.')
        .icon(ShoppingCart)
        .columns(1)
        .schema([
            Repeater.make('items')
                .label('Line Items')
                .schema([
                    TextInput.make('description').placeholder('Item description').required(),
                    TextInput.make('quantity').numeric().placeholder('0'),
                    TextInput.make('unit_price').numeric().placeholder('0.00'),
                    Select.make('tax_rate').options({
                        '0': 'No Tax',
                        '5': '5%',
                        '10': '10%',
                        '20': '20%',
                    }),
                ])
                .minItems(1)
                .maxItems(20)
                .reorderable()
                .addLabel('Add Line Item'),
        ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Repeater Field' }]">
        <Head title="Repeater Field Demo" />

        <PageHeader title="Repeater Field" description="Dynamic add/remove rows with reordering and collapsible items.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <!-- Basic Contacts -->
            <FormFields :schema="contactsSchema" :form="contactsForm" />

            <!-- Constrained & Collapsible -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <CardTitle>Constraints & Collapsible</CardTitle>
                        <Badge variant="secondary">min: 1, max: 5</Badge>
                        <Badge variant="outline">Collapsible</Badge>
                    </div>
                    <CardDescription>Click the row header to expand/collapse. Min 1, max 5 items.</CardDescription>
                </CardHeader>
            </Card>
            <FormFields :schema="teamSchema" :form="teamForm" />

            <!-- Invoice Line Items -->
            <FormFields :schema="invoiceSchema" :form="invoiceForm" />
        </div>
    </AuthenticatedLayout>
</template>
