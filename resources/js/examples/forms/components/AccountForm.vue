<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { useFocusFirstError } from '../composables/useFocusFirstError';
import { LANGUAGES } from '../data/options';

const { t } = useI18n();
const { formRef, focusFirstError } = useFocusFirstError();

const schema = toTypedSchema(z.object({
    name: z.string().min(2).max(30),
    dob: z.string().min(1),
    language: z.enum(LANGUAGES),
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
            :initial-values="{ name: '', dob: '', language: 'en' }"
            @submit="onSubmit"
            @invalid-submit="focusFirstError"
        >
            <FormField v-slot="{ componentField }" name="name">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.account.name') }}</FormLabel>
                    <FormControl>
                        <Input type="text" autocomplete="name" v-bind="componentField" />
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.account.nameHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="dob">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.account.dob') }}</FormLabel>
                    <FormControl>
                        <Input type="date" v-bind="componentField" />
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.account.dobHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="language">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.account.language') }}</FormLabel>
                    <FormControl>
                        <NativeSelect class="w-full" v-bind="componentField">
                            <option v-for="code in LANGUAGES" :key="code" :value="code">
                                {{ t(`examples.forms.languages.${code}`) }}
                            </option>
                        </NativeSelect>
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.account.languageHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <Button type="submit">{{ t('examples.forms.account.submit') }}</Button>
        </Form>
    </div>
</template>
