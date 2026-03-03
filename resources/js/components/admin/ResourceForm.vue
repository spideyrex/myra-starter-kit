<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Card, CardContent } from '@/components/ui/card';

withDefaults(defineProps<{
    processing?: boolean;
    submitText?: string;
    cancelHref?: string;
    maxWidth?: string;
    columns?: 1 | 2 | 3;
}>(), {
    processing: false,
    submitText: 'Save',
    maxWidth: 'max-w-2xl',
    columns: 2,
});

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <Card :class="['mt-6', maxWidth]">
        <CardContent class="pt-6">
            <form @submit.prevent="emit('submit')" class="space-y-4">
                <slot name="header" />

                <div class="grid gap-4" :class="{
                    'sm:grid-cols-1': columns === 1,
                    'sm:grid-cols-2': columns === 2,
                    'sm:grid-cols-3': columns === 3,
                }">
                    <slot />
                </div>

                <slot name="after-fields" />

                <slot name="actions">
                    <div class="flex gap-2 pt-4">
                        <LoadingButton :loading="processing">{{ submitText }}</LoadingButton>
                        <Button v-if="cancelHref" variant="outline" as-child>
                            <Link :href="cancelHref">Cancel</Link>
                        </Button>
                    </div>
                </slot>
            </form>
        </CardContent>
    </Card>
</template>
