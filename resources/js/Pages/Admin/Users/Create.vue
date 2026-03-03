<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { FormFields, ResourceForm } from '@/components/admin';
import { useResourceForm } from '@/composables/useResourceForm';
import { TextInput, Select } from '@/composables/useFormSchema';

const props = defineProps<{
    roles: string[];
}>();

const schema = [
    TextInput.make('name').required(),
    TextInput.make('email').email().required(),
    TextInput.make('password').password().required(),
    TextInput.make('phone').tel(),
    Select.make('role').placeholder('Select role')
        .options(props.roles.map(r => ({ label: r, value: r }))),
    Select.make('status').options({ active: 'Active', suspended: 'Suspended', pending: 'Pending' }),
];

const { form, submit } = useResourceForm({
    data: { name: '', email: '', password: '', phone: '', status: 'active', role: '' },
    storeRoute: 'admin.users.store',
    confirm: { title: 'Create User', description: 'Are you sure you want to create this user?', confirmText: 'Create' },
});
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Users', href: route('admin.users.index') }, { label: 'Create' }]">
        <Head title="Create User" />

        <PageHeader title="Create User" description="Add a new user to the system." />

        <ResourceForm :processing="form.processing" submit-text="Create User" :cancel-href="route('admin.users.index')" @submit="submit">
            <FormFields :schema="schema" :form="form" />
        </ResourceForm>
    </AuthenticatedLayout>
</template>
