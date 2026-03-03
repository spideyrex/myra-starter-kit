<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LoadingButton from '@/components/LoadingButton.vue';
import { ShieldCheck } from 'lucide-vue-next';

const useRecovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function submit() {
    form.post(route('two-factor.verify'), {
        onFinish: () => form.reset(),
    });
}

function toggleRecovery() {
    useRecovery.value = !useRecovery.value;
    form.reset();
}
</script>

<template>
    <GuestLayout>
        <Head title="Two-Factor Authentication" />

        <div class="space-y-6">
            <div class="flex flex-col items-center text-center">
                <div class="rounded-full bg-muted p-4">
                    <ShieldCheck class="size-8 text-muted-foreground" />
                </div>
                <h2 class="mt-4 text-2xl font-bold">Two-Factor Authentication</h2>
                <p v-if="!useRecovery" class="mt-1 text-sm text-muted-foreground">
                    Enter the authentication code from your authenticator app.
                </p>
                <p v-else class="mt-1 text-sm text-muted-foreground">
                    Enter one of your emergency recovery codes.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="!useRecovery" class="space-y-2">
                    <Label for="code">Code</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        maxlength="6"
                        class="text-center text-2xl tracking-[0.5em] font-mono"
                    />
                    <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
                </div>

                <div v-else class="space-y-2">
                    <Label for="recovery_code">Recovery Code</Label>
                    <Input id="recovery_code" v-model="form.recovery_code" autofocus />
                    <p v-if="form.errors.recovery_code" class="text-sm text-destructive">{{ form.errors.recovery_code }}</p>
                </div>

                <LoadingButton :loading="form.processing" class="w-full">
                    Verify
                </LoadingButton>

                <button type="button" class="w-full text-center text-sm text-muted-foreground hover:text-foreground" @click="toggleRecovery">
                    {{ useRecovery ? 'Use authentication code' : 'Use recovery code' }}
                </button>
            </form>
        </div>
    </GuestLayout>
</template>
