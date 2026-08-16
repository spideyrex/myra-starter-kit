<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { useFocusFirstError } from '../composables/useFocusFirstError';
import { NOTIFY_MODES } from '../data/options';

const { t } = useI18n();
const { formRef, focusFirstError } = useFocusFirstError();

const schema = toTypedSchema(z.object({
    mode: z.enum(NOTIFY_MODES),
    communication: z.boolean(),
    marketing: z.boolean(),
    security: z.boolean(),
}));

const toggles = ['communication', 'marketing', 'security'] as const;

function onSubmit() {
    toast.success(t('examples.forms.saved'));
}
</script>

<template>
    <div ref="formRef">
        <Form
            class="space-y-8"
            :validation-schema="schema"
            :initial-values="{ mode: 'all', communication: false, marketing: false, security: true }"
            @submit="onSubmit"
            @invalid-submit="focusFirstError"
        >
            <FormField v-slot="{ componentField }" name="mode">
                <FormItem class="space-y-3">
                    <FormLabel>{{ t('examples.forms.notifications.mode') }}</FormLabel>
                    <FormControl>
                        <RadioGroup class="space-y-1" v-bind="componentField">
                            <div v-for="mode in NOTIFY_MODES" :key="mode" class="flex items-center gap-2">
                                <RadioGroupItem :id="`notify-mode-${mode}`" :value="mode" />
                                <Label :for="`notify-mode-${mode}`" class="font-normal">
                                    {{ t(`examples.forms.notifyModes.${mode}`) }}
                                </Label>
                            </div>
                        </RadioGroup>
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>

            <fieldset class="space-y-4">
                <legend class="mb-4 text-lg font-medium">{{ t('examples.forms.notifications.emailLegend') }}</legend>

                <FormField v-for="key in toggles" :key="key" v-slot="{ value, handleChange }" :name="key">
                    <FormItem class="flex flex-row items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5 pr-4">
                            <FormLabel class="text-base">{{ t(`examples.forms.notifications.${key}.label`) }}</FormLabel>
                            <FormDescription>{{ t(`examples.forms.notifications.${key}.help`) }}</FormDescription>
                        </div>
                        <FormControl>
                            <Switch :model-value="value" @update:model-value="handleChange" />
                        </FormControl>
                    </FormItem>
                </FormField>
            </fieldset>

            <Button type="submit">{{ t('examples.forms.notifications.submit') }}</Button>
        </Form>
    </div>
</template>
