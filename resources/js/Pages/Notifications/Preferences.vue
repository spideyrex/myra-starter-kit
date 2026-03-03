<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';

interface Preference {
    type: string;
    label: string;
    email: boolean;
    database: boolean;
}

const props = defineProps<{
    preferences: Preference[];
}>();

const form = useForm({
    preferences: props.preferences.map(p => ({ ...p })),
});

const { confirm } = useConfirm();

async function submit() {
    const confirmed = await confirm({ title: 'Save Preferences', description: 'Are you sure you want to update your notification preferences?', confirmText: 'Save' });
    if (confirmed) form.put(route('notifications.preferences.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Notifications', href: route('notifications.index') },
        { label: 'Preferences' },
    ]">
        <Head title="Notification Preferences" />
        <PageHeader title="Notification Preferences" description="Choose how you want to receive notifications." />

        <form @submit.prevent="submit" class="mt-6 max-w-2xl space-y-4">
            <Card v-for="(pref, index) in form.preferences" :key="pref.type">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">{{ pref.label }}</CardTitle>
                </CardHeader>
                <CardContent class="flex items-center gap-8">
                    <div class="flex items-center gap-2">
                        <Switch :id="'email-' + pref.type" v-model="form.preferences[index].email" />
                        <Label :for="'email-' + pref.type">Email</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch :id="'db-' + pref.type" v-model="form.preferences[index].database" />
                        <Label :for="'db-' + pref.type">In-App</Label>
                    </div>
                </CardContent>
            </Card>

            <LoadingButton :loading="form.processing">Save Preferences</LoadingButton>
        </form>
    </AuthenticatedLayout>
</template>
