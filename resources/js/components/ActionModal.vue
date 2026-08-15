<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import FormField from '@/components/admin/FormField.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { BaseField, type SchemaItem, type FieldSchema } from '@/composables/useFormSchema';

interface ModalActionConfig {
    title: string;
    schema: SchemaItem[];
    routeName: string;
    routeParams?: Record<string, any>;
    method?: 'post' | 'put' | 'patch';
    defaults?: Record<string, any>;
    submitLabel?: string;
    /** Merged into the request alongside the form values. */
    extraPayload?: Record<string, any>;
    /** When set, form values are nested under this key instead of merged at the root. */
    payloadKey?: string;
}

const props = defineProps<{
    open: boolean;
    config: ModalActionConfig | null;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const resolvedSchema = computed(() => {
    if (!props.config) return [];
    return props.config.schema.map(item => {
        if (item instanceof BaseField) {
            return item.toProps();
        }
        return item as FieldSchema;
    });
});

const form = ref<ReturnType<typeof useForm> | null>(null);

watch(() => props.config, (config) => {
    if (config) {
        const defaults: Record<string, any> = {};
        for (const field of resolvedSchema.value) {
            if ('name' in field) {
                defaults[(field as FieldSchema).name] = config.defaults?.[(field as FieldSchema).name] ?? '';
            }
        }
        form.value = useForm(defaults);
    }
}, { immediate: true });

function submit() {
    if (!form.value || !props.config) return;
    const { extraPayload = {}, payloadKey } = props.config;
    const method = props.config.method || 'post';
    const url = route(props.config.routeName, props.config.routeParams);
    form.value
        // Schema values win on a key collision — the modal is the user's edit.
        .transform((data: Record<string, any>) => (payloadKey
            ? { ...extraPayload, [payloadKey]: { ...((extraPayload as any)[payloadKey] ?? {}), ...data } }
            : { ...extraPayload, ...data }))
        [method](url, {
            onSuccess: () => {
                emit('update:open', false);
            },
        });
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Modal :open="open" :title="config?.title || ''" @update:open="close">
        <form v-if="form && config" @submit.prevent="submit" class="space-y-4">
            <template v-for="(field, index) in resolvedSchema" :key="index">
                <FormField
                    v-if="'name' in field"
                    v-bind="(field as FieldSchema)"
                    :model-value="form[(field as FieldSchema).name]"
                    :error="form.errors[(field as FieldSchema).name]"
                    @update:model-value="form[(field as FieldSchema).name] = $event"
                />
            </template>
        </form>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="close">{{ t('common.cancel') }}</Button>
                <LoadingButton v-if="form" :loading="form.processing" @click="submit">
                    {{ config?.submitLabel || t('common.save') }}
                </LoadingButton>
            </div>
        </template>
    </Modal>
</template>
