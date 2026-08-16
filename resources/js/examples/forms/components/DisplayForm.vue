<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { toTypedSchema } from '@vee-validate/zod';
import { ErrorMessage } from 'vee-validate';
import * as z from 'zod';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Form, FormControl, FormField, FormItem, FormLabel } from '@/components/ui/form';
import { useFocusFirstError } from '../composables/useFocusFirstError';
import { DISPLAY_ITEMS } from '../data/options';

const { t } = useI18n();
const { formRef, focusFirstError } = useFocusFirstError();

const schema = toTypedSchema(z.object({
    items: z.array(z.enum(DISPLAY_ITEMS)).min(1),
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
            :initial-values="{ items: ['recents', 'home'] }"
            @submit="onSubmit"
            @invalid-submit="focusFirstError"
        >
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">{{ t('examples.forms.display.sidebar') }}</legend>
                <p class="text-sm text-muted-foreground">{{ t('examples.forms.display.sidebarHelp') }}</p>

                <!-- One vee-validate checkbox field per item, all bound to the
                     same array name — never a wrapping field that would clobber it. -->
                <FormField
                    v-for="item in DISPLAY_ITEMS"
                    :key="item"
                    v-slot="{ value, handleChange }"
                    type="checkbox"
                    :value="item"
                    :unchecked-value="false"
                    name="items"
                >
                    <FormItem class="flex flex-row items-center gap-2">
                        <FormControl>
                            <Checkbox :model-value="value?.includes(item)" @update:model-value="handleChange" />
                        </FormControl>
                        <FormLabel class="font-normal">
                            {{ t(`examples.forms.displayItems.${item}`) }}
                        </FormLabel>
                    </FormItem>
                </FormField>

                <ErrorMessage name="items" as="p" class="text-sm text-destructive" />
            </fieldset>

            <Button type="submit">{{ t('examples.forms.display.submit') }}</Button>
        </Form>
    </div>
</template>
