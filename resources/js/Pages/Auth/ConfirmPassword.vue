<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Lock } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="space-y-6">
            <div class="flex flex-col items-center text-center">
                <div class="rounded-full bg-muted p-4">
                    <Lock class="size-8 text-muted-foreground" />
                </div>
                <h2 class="mt-4 text-2xl font-bold">Confirm Password</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    This is a secure area. Please confirm your password before continuing.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" required autofocus autocomplete="current-password" />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Confirm
                </LoadingButton>
            </form>
        </div>
    </GuestLayout>
</template>
