<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useConfirm } from '@/composables/useConfirm';
import type { PageProps } from '@/types';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
}>();

const page = usePage<PageProps>();
const user = page.props.auth.user;

const profileForm = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

const { confirm } = useConfirm();

async function updateProfile() {
    const confirmed = await confirm({ title: 'Update Profile', description: 'Are you sure you want to save your profile changes?', confirmText: 'Save' });
    if (confirmed) profileForm.patch(route('profile.update'));
}

async function updatePassword() {
    const confirmed = await confirm({ title: 'Update Password', description: 'Are you sure you want to change your password?', confirmText: 'Update' });
    if (confirmed) passwordForm.put(route('password.update'), {
        onSuccess: () => passwordForm.reset(),
    });
}

async function deleteAccount() {
    const confirmed = await confirm({ title: 'Delete Account', description: 'This action is permanent. All your data will be deleted and cannot be recovered.', variant: 'destructive', confirmText: 'Delete Account' });
    if (confirmed) deleteForm.delete(route('profile.destroy'));
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Profile' }]">
        <Head title="Profile" />

        <div class="max-w-2xl space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Profile Information</CardTitle>
                    <CardDescription>Update your account's profile information and email address.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateProfile" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="profileForm.name" required />
                            <p v-if="profileForm.errors.name" class="text-sm text-destructive">{{ profileForm.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="profileForm.email" type="email" required />
                            <p v-if="profileForm.errors.email" class="text-sm text-destructive">{{ profileForm.errors.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="profileForm.phone" />
                            <p v-if="profileForm.errors.phone" class="text-sm text-destructive">{{ profileForm.errors.phone }}</p>
                        </div>

                        <div v-if="mustVerifyEmail && !user.email_verified_at" class="text-sm text-muted-foreground">
                            Your email address is unverified.
                        </div>

                        <LoadingButton :loading="profileForm.processing">Save</LoadingButton>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Update Password</CardTitle>
                    <CardDescription>Ensure your account is using a long, random password to stay secure.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="current_password">Current Password</Label>
                            <PasswordInput id="current_password" v-model="passwordForm.current_password" required />
                            <p v-if="passwordForm.errors.current_password" class="text-sm text-destructive">{{ passwordForm.errors.current_password }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="new_password">New Password</Label>
                            <PasswordInput id="new_password" v-model="passwordForm.password" required />
                            <p v-if="passwordForm.errors.password" class="text-sm text-destructive">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirm Password</Label>
                            <PasswordInput id="password_confirmation" v-model="passwordForm.password_confirmation" required />
                        </div>

                        <LoadingButton :loading="passwordForm.processing">Save</LoadingButton>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Delete Account</CardTitle>
                    <CardDescription>Once your account is deleted, all of its resources and data will be permanently deleted.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="deleteAccount" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="delete_password">Password</Label>
                            <PasswordInput id="delete_password" v-model="deleteForm.password" required placeholder="Enter your password to confirm" />
                            <p v-if="deleteForm.errors.password" class="text-sm text-destructive">{{ deleteForm.errors.password }}</p>
                        </div>

                        <LoadingButton variant="destructive" :loading="deleteForm.processing">
                            Delete Account
                        </LoadingButton>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
