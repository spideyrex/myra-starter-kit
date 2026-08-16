<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Label } from '@/components/ui/label';
import type { TemplateSchema } from '@/types';

defineProps<{ templates: TemplateSchema[]; modelValue: string }>();
defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();
</script>

<template>
    <RadioGroup
        :model-value="modelValue"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        :aria-label="t('landing.picker.label')"
        @update:model-value="$emit('update:modelValue', String($event))"
    >
        <div
            v-for="template in templates"
            :key="template.key"
            class="relative rounded-lg border p-3 transition-colors"
            :class="modelValue === template.key ? 'border-primary ring-2 ring-primary/40' : 'hover:border-primary/40'"
        >
            <AspectRatio :ratio="16 / 10" class="overflow-hidden rounded-md bg-muted">
                <img
                    v-if="template.thumbnail"
                    :src="template.thumbnail"
                    alt=""
                    class="size-full object-cover"
                />
            </AspectRatio>

            <div class="mt-3 flex items-start gap-2">
                <RadioGroupItem
                    :id="`template-${template.key}`"
                    :value="template.key"
                    :aria-describedby="`template-${template.key}-description`"
                    class="mt-1"
                />
                <div class="min-w-0">
                    <Label :for="`template-${template.key}`" class="cursor-pointer font-medium">
                        {{ t(template.titleKey) }}
                    </Label>
                    <p :id="`template-${template.key}-description`" class="text-xs text-muted-foreground">
                        {{ t(template.descriptionKey) }}
                    </p>
                </div>
            </div>
        </div>
    </RadioGroup>
</template>
