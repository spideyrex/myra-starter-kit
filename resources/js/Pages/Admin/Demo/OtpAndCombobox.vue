<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Check, ChevronsUpDown } from 'lucide-vue-next';

interface Timezone { value: string; label: string }

const props = withDefaults(defineProps<{ timezones?: Timezone[] }>(), { timezones: () => [] });

const { t } = useI18n();

const code = ref('');
const timezone = ref<Timezone | null>(null);

const complete = computed(() => code.value.length === 6);
const verified = ref(false);

function verify() {
    verified.value = complete.value;
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.otpAndCombobox.title') }]"
    >
        <Head :title="t('gallery.demos.otpAndCombobox.title')" />

        <PageHeader
            :title="t('gallery.demos.otpAndCombobox.title')"
            :description="t('gallery.demos.otpAndCombobox.description')"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.otpAndCombobox.otpTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.otpAndCombobox.otpDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label id="otp-label" for="otp-input">
                            {{ t('gallery.componentDemos.otpAndCombobox.otpLabel') }}
                        </Label>
                        <InputOTP
                            id="otp-input"
                            v-model="code"
                            :maxlength="6"
                            aria-labelledby="otp-label"
                            aria-describedby="otp-hint"
                        >
                            <InputOTPGroup>
                                <InputOTPSlot v-for="i in [0, 1, 2]" :key="i" :index="i" />
                            </InputOTPGroup>
                            <InputOTPSeparator />
                            <InputOTPGroup>
                                <InputOTPSlot v-for="i in [3, 4, 5]" :key="i" :index="i" />
                            </InputOTPGroup>
                        </InputOTP>
                        <p id="otp-hint" class="text-xs text-muted-foreground">
                            {{ t('gallery.componentDemos.otpAndCombobox.otpHint') }}
                        </p>
                    </div>

                    <Button :disabled="!complete" @click="verify">
                        {{ t('gallery.componentDemos.otpAndCombobox.verify') }}
                    </Button>

                    <p role="status" aria-live="polite" class="text-sm">
                        <span v-if="verified" class="text-primary">
                            {{ t('gallery.componentDemos.otpAndCombobox.verified') }}
                        </span>
                        <span v-else class="text-muted-foreground">
                            {{ t('gallery.componentDemos.otpAndCombobox.entered', { n: code.length }) }}
                        </span>
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.otpAndCombobox.comboTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.otpAndCombobox.comboDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label id="timezone-label">
                            {{ t('gallery.componentDemos.otpAndCombobox.comboLabel') }}
                        </Label>
                        <Combobox v-model="timezone" by="value">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child>
                                    <Button
                                        variant="outline"
                                        class="w-full justify-between"
                                        aria-labelledby="timezone-label"
                                    >
                                        {{ timezone?.label ?? t('gallery.componentDemos.otpAndCombobox.comboPlaceholder') }}
                                        <ChevronsUpDown class="ml-2 size-4 shrink-0 opacity-50" aria-hidden="true" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxList>
                                <ComboboxInput :placeholder="t('gallery.componentDemos.otpAndCombobox.comboSearch')" />
                                <ComboboxEmpty>
                                    {{ t('gallery.componentDemos.otpAndCombobox.comboEmpty') }}
                                </ComboboxEmpty>
                                <ComboboxGroup>
                                    <ComboboxItem v-for="zone in props.timezones" :key="zone.value" :value="zone">
                                        {{ zone.label }}
                                        <ComboboxItemIndicator>
                                            <Check class="size-4" aria-hidden="true" />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                    </div>

                    <p role="status" aria-live="polite" class="text-sm text-muted-foreground">
                        {{
                            timezone
                                ? t('gallery.componentDemos.otpAndCombobox.comboSelected', { zone: timezone.label })
                                : t('gallery.componentDemos.otpAndCombobox.comboNone')
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
