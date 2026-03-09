<script setup lang="ts">
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useConfirm } from '@/composables/useConfirm';
import type { PageProps } from '@/types';
import { ref } from 'vue';
import { User, Shield, Monitor, Camera, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    sessions?: Array<{
        id: string;
        ip_address: string;
        user_agent: string;
        last_activity: string;
        is_current: boolean;
    }>;
    twoFactorEnabled?: boolean;
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

const avatarInput = ref<HTMLInputElement | null>(null);

const { confirm } = useConfirm();

function initials(name: string) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

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

function uploadAvatar(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const form = useForm({ avatar: file as any });
    form.post(route('profile.avatar.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

async function removeAvatar() {
    const confirmed = await confirm({ title: 'Remove Avatar', description: 'Are you sure you want to remove your profile photo?', confirmText: 'Remove' });
    if (confirmed) router.delete(route('profile.avatar.destroy'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Profile' }]">
        <Head title="Profile" />

        <div class="max-w-4xl">
            <!-- Profile Header -->
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:gap-6 sm:text-left mb-8">
                <div class="relative group shrink-0">
                    <Avatar class="size-20">
                        <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                        <AvatarFallback class="text-xl">{{ initials(user.name) }}</AvatarFallback>
                    </Avatar>
                    <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" @click="avatarInput?.click()">
                        <Camera class="size-6 text-white" />
                    </div>
                    <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="uploadAvatar" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold sm:text-2xl">{{ user.name }}</h1>
                    <p class="text-sm text-muted-foreground truncate">{{ user.email }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-2 mt-1 sm:justify-start">
                        <Badge v-for="role in user.roles" :key="role" variant="secondary">{{ role }}</Badge>
                        <Badge v-if="user.avatar" variant="outline" class="cursor-pointer hover:bg-destructive/10" @click="removeAvatar">
                            <Trash2 class="mr-1 size-3" /> Remove photo
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs default-value="info">
                <TabsList>
                    <TabsTrigger value="info">
                        <User class="mr-2 size-4" />
                        Profile Info
                    </TabsTrigger>
                    <TabsTrigger value="security">
                        <Shield class="mr-2 size-4" />
                        Security
                    </TabsTrigger>
                    <TabsTrigger value="sessions">
                        <Monitor class="mr-2 size-4" />
                        Sessions
                    </TabsTrigger>
                </TabsList>

                <!-- Info Tab -->
                <TabsContent value="info" class="space-y-6 mt-6">
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
                                <LoadingButton :loading="profileForm.processing">Save Changes</LoadingButton>
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
                </TabsContent>

                <!-- Security Tab -->
                <TabsContent value="security" class="space-y-6 mt-6">
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
                                <LoadingButton :loading="passwordForm.processing">Update Password</LoadingButton>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Two-Factor Authentication</CardTitle>
                            <CardDescription>Add additional security to your account using two-factor authentication.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <Badge :variant="twoFactorEnabled ? 'default' : 'secondary'">
                                        {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                                    </Badge>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ twoFactorEnabled ? 'Two-factor authentication is active on your account.' : 'Enable 2FA for additional security.' }}
                                    </p>
                                </div>
                                <Button variant="outline" as-child class="shrink-0">
                                    <Link :href="route('profile.security')">
                                        Manage 2FA
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Sessions Tab -->
                <TabsContent value="sessions" class="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Browser Sessions</CardTitle>
                            <CardDescription>Manage and log out your active sessions on other browsers and devices.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="sessions && sessions.length > 0" class="space-y-3">
                                <div v-for="session in sessions" :key="session.id" class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <Monitor class="size-5 text-muted-foreground shrink-0 mt-0.5" />
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium">
                                                {{ session.ip_address }}
                                                <Badge v-if="session.is_current" variant="outline" class="ml-2">Current</Badge>
                                            </p>
                                            <p class="text-xs text-muted-foreground truncate">{{ session.user_agent }} &mdash; Last active {{ session.last_activity }}</p>
                                        </div>
                                    </div>
                                    <Button v-if="!session.is_current" variant="ghost" size="sm" class="shrink-0 self-end sm:self-auto" @click="router.delete(route('sessions.destroy', session.id))">
                                        Revoke
                                    </Button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">No active sessions found.</p>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
