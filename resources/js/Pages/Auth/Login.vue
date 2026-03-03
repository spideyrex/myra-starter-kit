<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';

defineProps<{
    canResetPassword: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">Log in</h2>
                <p class="text-sm text-muted-foreground">Enter your credentials to access your account</p>
            </div>

            <div v-if="status" class="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" required autocomplete="current-password" />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="form.remember" />
                        Remember me
                    </label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-muted-foreground hover:text-foreground">
                        Forgot password?
                    </Link>
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Log in
                </LoadingButton>

                <p class="text-center text-sm text-muted-foreground">
                    Don't have an account?
                    <Link :href="route('register')" class="text-foreground underline hover:text-foreground/80">Sign up</Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>
