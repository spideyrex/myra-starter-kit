<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Shield, Smartphone, Trash2 } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps<{
    twoFactorEnabled: boolean;
    sessions: Array<{
        id: string;
        ip_address: string;
        user_agent: string;
        last_activity: string;
        is_current: boolean;
    }>;
    qrCode?: {
        svg: string;
        secret: string;
        recovery_codes: string[];
    };
}>();

const showSetup = ref(false);
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });
const { confirm } = useConfirm();

async function enableTwoFactor() {
    const confirmed = await confirm({ title: 'Enable Two-Factor', description: 'Are you sure you want to enable two-factor authentication?', confirmText: 'Enable' });
    if (!confirmed) return;
    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => { showSetup.value = true; },
    });
}

async function confirmTwoFactor() {
    const confirmed = await confirm({ title: 'Verify Two-Factor', description: 'Confirm and enable two-factor authentication with the code you entered?', confirmText: 'Verify & Enable' });
    if (!confirmed) return;
    confirmForm.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onSuccess: () => { showSetup.value = false; },
    });
}

async function disableTwoFactor() {
    const confirmed = await confirm({ title: 'Disable Two-Factor', description: 'Are you sure you want to disable two-factor authentication? Your account will be less secure.', variant: 'destructive', confirmText: 'Disable' });
    if (!confirmed) return;
    disableForm.delete(route('two-factor.disable'), {
        preserveScroll: true,
    });
}

async function revokeSession(id: string) {
    const confirmed = await confirm({ title: 'Revoke Session', description: 'Are you sure you want to revoke this session?', variant: 'destructive', confirmText: 'Revoke' });
    if (!confirmed) return;
    router.delete(route('sessions.destroy', id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Profile', href: route('profile.edit') }, { label: 'Security' }]">
        <Head title="Security" />

        <div class="max-w-2xl space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="size-5" />
                                Two-Factor Authentication
                            </CardTitle>
                            <CardDescription>Add additional security to your account using two factor authentication.</CardDescription>
                        </div>
                        <Badge :variant="twoFactorEnabled ? 'default' : 'secondary'">
                            {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="!twoFactorEnabled && !showSetup">
                        <Button @click="enableTwoFactor">
                            <Smartphone class="mr-2 size-4" />
                            Enable Two-Factor
                        </Button>
                    </div>

                    <div v-if="showSetup && qrCode" class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Scan the QR code with your authenticator app, then enter the verification code.
                        </p>
                        <div class="flex justify-center rounded-lg border bg-white p-4" v-html="qrCode.svg" />
                        <div class="rounded-lg bg-muted p-3">
                            <p class="text-xs text-muted-foreground">Secret Key (manual entry):</p>
                            <p class="font-mono text-sm">{{ qrCode.secret }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="code">Verification Code</Label>
                            <Input id="code" v-model="confirmForm.code" inputmode="numeric" placeholder="Enter 6-digit code" />
                            <p v-if="confirmForm.errors.code" class="text-sm text-destructive">{{ confirmForm.errors.code }}</p>
                        </div>
                        <LoadingButton :loading="confirmForm.processing" type="button" @click="confirmTwoFactor">Verify & Enable</LoadingButton>

                        <div v-if="qrCode.recovery_codes" class="mt-4 rounded-lg bg-muted p-4">
                            <p class="mb-2 text-sm font-medium">Recovery Codes</p>
                            <p class="mb-2 text-xs text-muted-foreground">Store these codes securely. They can be used to access your account if you lose your authenticator.</p>
                            <div class="grid grid-cols-2 gap-1 font-mono text-sm">
                                <span v-for="code in qrCode.recovery_codes" :key="code">{{ code }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="twoFactorEnabled && !showSetup" class="space-y-4">
                        <p class="text-sm text-success">Two-factor authentication is currently enabled.</p>
                        <form @submit.prevent="disableTwoFactor" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="disable_password">Password (to disable)</Label>
                                <PasswordInput id="disable_password" v-model="disableForm.password" required />
                                <p v-if="disableForm.errors.password" class="text-sm text-destructive">{{ disableForm.errors.password }}</p>
                            </div>
                            <LoadingButton variant="destructive" :loading="disableForm.processing">
                                Disable Two-Factor
                            </LoadingButton>
                        </form>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Active Sessions</CardTitle>
                    <CardDescription>Manage and revoke your active sessions on other browsers and devices.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>IP Address</TableHead>
                                <TableHead>Browser</TableHead>
                                <TableHead>Last Active</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="session in sessions" :key="session.id">
                                <TableCell>{{ session.ip_address }}</TableCell>
                                <TableCell class="max-w-[200px] truncate">{{ session.user_agent }}</TableCell>
                                <TableCell>
                                    {{ session.last_activity }}
                                    <Badge v-if="session.is_current" variant="outline" class="ml-2">Current</Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        v-if="!session.is_current"
                                        variant="ghost"
                                        size="sm"
                                        @click="revokeSession(session.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
