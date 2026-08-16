<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Separator } from '@/components/ui/separator';
import FormsSidebarNav from './components/FormsSidebarNav.vue';
import ProfileForm from './components/ProfileForm.vue';
import AccountForm from './components/AccountForm.vue';
import AppearanceForm from './components/AppearanceForm.vue';
import NotificationsForm from './components/NotificationsForm.vue';
import DisplayForm from './components/DisplayForm.vue';

const { t } = useI18n();

const PANES = {
    profile: ProfileForm,
    account: AccountForm,
    appearance: AppearanceForm,
    notifications: NotificationsForm,
    display: DisplayForm,
} as const;

const keys = Object.keys(PANES);
const pane = ref<string>('profile');

const active = computed(() => PANES[pane.value as keyof typeof PANES] ?? ProfileForm);
</script>

<template>
    <div class="space-y-6 p-10 pb-16">
        <header class="space-y-0.5">
            <h1 class="text-2xl font-bold tracking-tight">{{ t('examples.forms.title') }}</h1>
            <p class="text-muted-foreground">{{ t('examples.forms.description') }}</p>
        </header>

        <Separator class="my-6" />

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
            <aside class="lg:w-1/5">
                <FormsSidebarNav v-model="pane" :panes="keys" />
            </aside>

            <main id="content" class="flex-1 lg:max-w-2xl">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-medium">{{ t(`examples.forms.panes.${pane}.title`) }}</h2>
                        <p class="text-sm text-muted-foreground">{{ t(`examples.forms.panes.${pane}.description`) }}</p>
                    </div>
                    <Separator />
                    <component :is="active" :key="pane" />
                </div>
            </main>
        </div>
    </div>
</template>
