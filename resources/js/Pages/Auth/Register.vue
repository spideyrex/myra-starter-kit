<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';
import PasswordInput from '@/components/PasswordInput.vue';

const { t } = useI18n();

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
    const keys = ['', 'weak', 'fair', 'good', 'strong', 'veryStrong'];
    const key = keys[passwordScore.value];
    return key ? t(`auth.strength.${key}`) : '';
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
        <Head :title="t('auth.register')" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">{{ t('auth.registerTitle') }}</h2>
                <p class="text-sm text-muted-foreground">{{ t('auth.registerSubtitle') }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">{{ t('auth.name') }}</Label>
                    <Input id="name" v-model="form.name" required autofocus autocomplete="name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input id="email" v-model="form.email" type="email" required autocomplete="username" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password">{{ t('auth.password') }}</Label>
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
                    <Label for="password_confirmation">{{ t('auth.confirmPasswordLabel') }}</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" />
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    {{ t('auth.register') }}
                </LoadingButton>

                <p class="text-center text-sm text-muted-foreground">
                    {{ t('auth.haveAccount') }}
                    <Link :href="route('login')" class="text-foreground underline hover:text-foreground/80">{{ t('auth.login') }}</Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>
