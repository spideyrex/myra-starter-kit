<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Label } from '@/components/ui/label';
import type { AuthLayoutSchema } from './types';

defineProps<{ layouts: AuthLayoutSchema[]; modelValue: string }>();
defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();
</script>

<template>
    <RadioGroup
        :model-value="modelValue"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        :aria-label="t('appearanceAdmin.fields.layout')"
        @update:model-value="$emit('update:modelValue', String($event))"
    >
        <div
            v-for="layout in layouts"
            :key="layout.key"
            class="relative rounded-lg border p-3 transition-colors"
            :class="modelValue === layout.key ? 'border-primary ring-2 ring-primary/40' : 'hover:border-primary/40'"
        >
            <AspectRatio :ratio="16 / 10" class="overflow-hidden rounded-md border bg-muted">
                <img v-if="layout.thumbnail" :src="layout.thumbnail" alt="" class="size-full object-cover" />

                <!-- A schematic, not a screenshot: it always renders and never 404s. -->
                <div v-else aria-hidden="true" class="size-full p-1.5">
                    <div v-if="layout.key === 'split'" class="flex size-full gap-1">
                        <div class="w-1/2 rounded-sm bg-primary"></div>
                        <div class="flex w-1/2 items-center justify-center">
                            <div class="h-2/3 w-5/6 rounded-sm border bg-card"></div>
                        </div>
                    </div>

                    <div v-else-if="layout.key === 'centered'" class="flex size-full items-center justify-center rounded-sm bg-background">
                        <div class="w-2/3 space-y-1">
                            <div class="mx-auto h-1.5 w-6 rounded-full bg-primary"></div>
                            <div class="h-2/3 rounded-sm border bg-card py-4"></div>
                        </div>
                    </div>

                    <div v-else-if="layout.key === 'cover'" class="flex size-full items-center justify-center rounded-sm bg-primary">
                        <div class="h-2/3 w-1/2 rounded-sm border bg-card"></div>
                    </div>

                    <div v-else class="flex size-full items-center justify-center rounded-sm bg-muted-foreground/10">
                        <div class="flex h-2/3 w-5/6 overflow-hidden rounded-sm border bg-card">
                            <div class="w-1/2"></div>
                            <div class="w-1/2 bg-primary"></div>
                        </div>
                    </div>
                </div>
            </AspectRatio>

            <div class="mt-3 flex items-start gap-2">
                <RadioGroupItem
                    :id="`auth-layout-${layout.key}`"
                    :value="layout.key"
                    :aria-describedby="`auth-layout-${layout.key}-description`"
                    class="mt-1"
                />
                <div class="min-w-0">
                    <Label :for="`auth-layout-${layout.key}`" class="cursor-pointer font-medium">
                        {{ t(layout.titleKey) }}
                    </Label>
                    <p :id="`auth-layout-${layout.key}-description`" class="text-xs text-muted-foreground">
                        {{ t(layout.descriptionKey) }}
                    </p>
                </div>
            </div>
        </div>
    </RadioGroup>
</template>
