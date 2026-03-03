<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { FormFields, ResourceForm } from '@/components/admin';
import { useResourceForm } from '@/composables/useResourceForm';
import { TextInput, Select } from '@/composables/useFormSchema';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        status: string;
        roles: string[];
    };
    roles: string[];
}>();

const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email().required(),
    TextInput.make('password').password().label('Password (leave blank to keep)'),
    TextInput.make('phone').tel(),
    Select.make('role').placeholder('Select role')
        .options(props.roles.map(r => ({ label: r, value: r }))),
    Select.make('status').options({ active: 'Active', suspended: 'Suspended', pending: 'Pending' }),
];

const { form, submit } = useResourceForm({
    data: {
        name: props.user.name,
        email: props.user.email,
        password: '',
        phone: props.user.phone || '',
        status: props.user.status,
        role: props.user.roles[0] || '',
    },
    updateRoute: 'admin.users.update',
    updateRouteParams: props.user.id,
    confirm: { title: 'Update User', description: 'Are you sure you want to save these changes?', confirmText: 'Save' },
});
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Users', href: route('admin.users.index') }, { label: 'Edit' }]">
        <Head :title="`Edit ${user.name}`" />

        <PageHeader :title="`Edit ${user.name}`" description="Update user account details." />

        <ResourceForm :processing="form.processing" submit-text="Update User" :cancel-href="route('admin.users.index')" @submit="submit">
            <FormFields :schema="schema" :form="form" />
        </ResourceForm>
    </AuthenticatedLayout>
</template>
