<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="space-y-6">
            <div class="flex flex-col items-center text-center">
                <div class="rounded-full bg-muted p-4">
                    <Mail class="size-8 text-muted-foreground" />
                </div>
                <h2 class="mt-4 text-2xl font-bold">Verify Email</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Thanks for signing up! Please verify your email address by clicking the link we sent you.
                </p>
            </div>

            <div v-if="status === 'verification-link-sent'" class="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                A new verification link has been sent to your email.
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <LoadingButton :loading="form.processing" class="w-full">
                    Resend Verification Email
                </LoadingButton>
            </form>

            <div class="text-center">
                <Link :href="route('logout')" method="post" as="button" class="text-sm text-muted-foreground hover:text-foreground">
                    Log Out
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>
