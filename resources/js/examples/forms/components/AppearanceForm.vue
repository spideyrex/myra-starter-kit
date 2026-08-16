<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import FormNativeSelect from './FormNativeSelect.vue';
import { useFocusFirstError } from '../composables/useFocusFirstError';
import { FONTS, THEMES } from '../data/options';

const { t } = useI18n();
const { formRef, focusFirstError } = useFocusFirstError();

const schema = toTypedSchema(z.object({
    font: z.enum(FONTS),
    theme: z.enum(THEMES),
}));

function onSubmit() {
    toast.success(t('examples.forms.saved'));
}
</script>

<template>
    <div ref="formRef">
        <Form
            class="space-y-8"
            :validation-schema="schema"
            :initial-values="{ font: 'inter', theme: 'light' }"
            @submit="onSubmit"
            @invalid-submit="focusFirstError"
        >
            <FormField v-slot="{ value, handleChange }" name="font">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.appearance.font') }}</FormLabel>
                    <FormControl>
                        <FormNativeSelect
                            class="w-full max-sm:w-full sm:w-[200px]"
                            :model-value="value"
                            @update:model-value="handleChange"
                        >
                            <option v-for="font in FONTS" :key="font" :value="font">
                                {{ t(`examples.forms.fonts.${font}`) }}
                            </option>
                        </FormNativeSelect>
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.appearance.fontHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="theme">
                <FormItem class="space-y-3">
                    <FormLabel>{{ t('examples.forms.appearance.theme') }}</FormLabel>
                    <FormDescription>{{ t('examples.forms.appearance.themeHelp') }}</FormDescription>
                    <FormControl>
                        <RadioGroup class="grid max-w-md grid-cols-2 gap-8 pt-2" v-bind="componentField">
                            <div v-for="theme in THEMES" :key="theme" class="flex items-center gap-2">
                                <RadioGroupItem :id="`appearance-theme-${theme}`" :value="theme" />
                                <Label :for="`appearance-theme-${theme}`">
                                    {{ t(`examples.forms.themes.${theme}`) }}
                                </Label>
                            </div>
                        </RadioGroup>
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>

            <Button type="submit">{{ t('examples.forms.appearance.submit') }}</Button>
        </Form>
    </div>
</template>
