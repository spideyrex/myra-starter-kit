<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import LoadingButton from '@/components/LoadingButton.vue';

withDefaults(defineProps<{
    title: string;
    description?: string;
    processing?: boolean;
    submitText?: string;
    columns?: 1 | 2;
}>(), {
    processing: false,
    submitText: 'Save',
    columns: 2,
});

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">{{ description }}</CardDescription>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="emit('submit')" class="space-y-4">
                <div class="grid gap-4" :class="{
                    'sm:grid-cols-1': columns === 1,
                    'sm:grid-cols-2': columns === 2,
                }">
                    <slot />
                </div>

                <slot name="after-fields" />

                <slot name="actions">
                    <LoadingButton :loading="processing">{{ submitText }}</LoadingButton>
                </slot>
            </form>
        </CardContent>
    </Card>
</template>
