<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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

const page = usePage();
const { t } = useI18n();
const registrationEnabled = computed(() => (page.props.siteSettings as any)?.registration_enabled ?? true);

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
        <Head :title="t('auth.loginTitle')" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold">{{ t('auth.loginTitle') }}</h2>
                <p class="text-sm text-muted-foreground">{{ t('auth.loginSubtitle') }}</p>
            </div>

            <div v-if="status" class="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <PasswordInput id="password" v-model="form.password" required autocomplete="current-password" />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox v-model="form.remember" />
                        {{ t('auth.rememberMe') }}
                    </label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-muted-foreground hover:text-foreground">
                        {{ t('auth.forgotPasswordLink') }}
                    </Link>
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    {{ t('auth.login') }}
                </LoadingButton>

                <p v-if="registrationEnabled" class="text-center text-sm text-muted-foreground">
                    {{ t('auth.noAccount') }}
                    <Link :href="route('register')" class="text-foreground underline hover:text-foreground/80">{{ t('auth.signUp') }}</Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>
