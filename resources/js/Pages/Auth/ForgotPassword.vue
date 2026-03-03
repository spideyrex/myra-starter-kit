<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">Forgot Password</h2>
                <p class="text-sm text-muted-foreground">
                    Enter your email and we'll send you a password reset link.
                </p>
            </div>

            <div v-if="status" class="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required autofocus />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Email Password Reset Link
                </LoadingButton>
            </form>
        </div>
    </GuestLayout>
</template>
