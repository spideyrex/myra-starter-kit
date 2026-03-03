<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const passwordScore = computed(() => {
    const p = form.password;
    if (!p) return 0;
    let score = 0;
    if (p.length >= 8) score++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^a-zA-Z0-9]/.test(p)) score++;
    if (p.length >= 12) score++;
    return score;
});

const strengthLabel = computed(() => {
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    return labels[passwordScore.value] || '';
});

const strengthColor = computed(() => {
    const colors = ['', 'bg-destructive', 'bg-warning', 'bg-warning', 'bg-success', 'bg-success'];
    return colors[passwordScore.value] || '';
});

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">Create an account</h2>
                <p class="text-sm text-muted-foreground">Enter your details to get started</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required autofocus autocomplete="name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required autocomplete="username" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" required autocomplete="new-password" />
                    <div v-if="form.password" class="space-y-1">
                        <div class="flex gap-1">
                            <div
                                v-for="i in 5"
                                :key="i"
                                class="h-1.5 flex-1 rounded-full transition-colors"
                                :class="i <= passwordScore ? strengthColor : 'bg-muted'"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">{{ strengthLabel }}</p>
                    </div>
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" />
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Register
                </LoadingButton>

                <p class="text-center text-sm text-muted-foreground">
                    Already have an account?
                    <Link :href="route('login')" class="text-foreground underline hover:text-foreground/80">Log in</Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>
