<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import type { ScrimKey } from './types';

defineProps<{ scrims: ScrimKey[]; modelValue: ScrimKey; floored?: boolean }>();
defineEmits<{ 'update:modelValue': [value: ScrimKey] }>();

const { t } = useI18n();

const OPACITY: Record<string, string> = { none: '0', light: '0.35', medium: '0.55', strong: '0.7' };

function opacity(key: string): string {
    return OPACITY[key] ?? '0.55';
}
</script>

<template>
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">{{ t('appearanceAdmin.fields.scrim') }}</legend>
        <p class="text-xs text-muted-foreground">{{ t('appearanceAdmin.help.scrim') }}</p>

        <RadioGroup
            :model-value="modelValue"
            class="grid gap-3 sm:grid-cols-4"
            @update:model-value="$emit('update:modelValue', String($event) as ScrimKey)"
        >
            <div
                v-for="scrim in scrims"
                :key="scrim"
                class="rounded-md border p-2"
                :class="modelValue === scrim ? 'border-primary ring-2 ring-primary/40' : ''"
            >
                <div aria-hidden="true" class="relative h-8 overflow-hidden rounded-sm bg-primary">
                    <div class="absolute inset-0 bg-black" :style="{ opacity: opacity(scrim) }"></div>
                    <span class="absolute inset-0 flex items-center justify-center text-[10px] font-medium text-white">Aa</span>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <RadioGroupItem :id="`auth-scrim-${scrim}`" :value="scrim" />
                    <Label :for="`auth-scrim-${scrim}`" class="cursor-pointer text-xs">
                        {{ t('appearanceAdmin.scrims.' + scrim) }}
                    </Label>
                </div>
            </div>
        </RadioGroup>

        <p v-if="floored && modelValue === 'none'" class="text-xs text-muted-foreground">
            {{ t('appearanceAdmin.help.scrimFloor') }}
        </p>
    </fieldset>
</template>
