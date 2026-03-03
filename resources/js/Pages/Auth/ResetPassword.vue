<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">Reset Password</h2>
                <p class="text-sm text-muted-foreground">Enter your new password below.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required autofocus />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" required />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required />
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Reset Password
                </LoadingButton>
            </form>
        </div>
    </GuestLayout>
</template>
