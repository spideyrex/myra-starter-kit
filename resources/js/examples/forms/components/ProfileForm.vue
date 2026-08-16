<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toTypedSchema } from '@vee-validate/zod';
import * as z from 'zod';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import FormNativeSelect from './FormNativeSelect.vue';
import { useFocusFirstError } from '../composables/useFocusFirstError';
import { LANGUAGES } from '../data/options';

const { t } = useI18n();
const { formRef, focusFirstError } = useFocusFirstError();

const schema = toTypedSchema(z.object({
    username: z.string().min(2).max(30),
    email: z.string().email(),
    bio: z.string().max(160).optional(),
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
            :initial-values="{ username: '', email: '', bio: '', language: 'en' }"
            @submit="onSubmit"
            @invalid-submit="focusFirstError"
        >
            <FormField v-slot="{ componentField }" name="username">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.profile.username') }}</FormLabel>
                    <FormControl>
                        <Input type="text" autocomplete="username" v-bind="componentField" />
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.profile.usernameHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="email">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.profile.email') }}</FormLabel>
                    <FormControl>
                        <Input type="email" autocomplete="email" v-bind="componentField" />
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.profile.emailHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="bio">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.profile.bio') }}</FormLabel>
                    <FormControl>
                        <Textarea class="resize-none" v-bind="componentField" />
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.profile.bioHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <FormField v-slot="{ value, handleChange }" name="language">
                <FormItem>
                    <FormLabel>{{ t('examples.forms.profile.language') }}</FormLabel>
                    <FormControl>
                        <FormNativeSelect class="w-full" :model-value="value" @update:model-value="handleChange">
                            <option v-for="code in LANGUAGES" :key="code" :value="code">
                                {{ t(`examples.forms.languages.${code}`) }}
                            </option>
                        </FormNativeSelect>
                    </FormControl>
                    <FormDescription>{{ t('examples.forms.profile.languageHelp') }}</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

            <Button type="submit">{{ t('examples.forms.profile.submit') }}</Button>
        </Form>
    </div>
</template>
