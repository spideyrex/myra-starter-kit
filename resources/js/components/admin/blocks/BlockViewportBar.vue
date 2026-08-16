<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';

const props = withDefaults(defineProps<{
    modelValue: string;
    dark: boolean;
    viewports?: string[];
}>(), {
    viewports: () => ['full', '1024', '768', '375'],
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'update:dark', value: boolean): void;
}>();

const { t } = useI18n();

const uid = Math.random().toString(36).slice(2, 8);
const darkId = `block-dark-${uid}`;
const darkLabelId = `block-dark-label-${uid}`;
const groupLabelId = `block-viewport-label-${uid}`;

const label = computed(() =>
    props.modelValue === 'full' ? t('blocks.preview.viewportFull') : t('blocks.preview.viewportPx', { px: props.modelValue }),
);

/** reka-ui types the payload as its own AcceptableValue union; narrow here, not in the signature. */
function pick(value: unknown) {
    const next = Array.isArray(value) ? value[0] : value;
    if (typeof next === 'string' && next !== '') emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <span :id="groupLabelId" class="text-sm text-muted-foreground">{{ t('blocks.preview.viewport') }}</span>
            <ToggleGroup
                type="single"
                variant="outline"
                :model-value="modelValue"
                :aria-labelledby="groupLabelId"
                @update:model-value="pick"
            >
                <ToggleGroupItem
                    v-for="value in viewports"
                    :key="value"
                    :value="value"
                    :aria-label="value === 'full' ? t('blocks.preview.viewportFull') : t('blocks.preview.viewportPx', { px: value })"
                >
                    {{ value === 'full' ? t('blocks.preview.viewportFull') : value }}
                </ToggleGroupItem>
            </ToggleGroup>
        </div>

        <div class="flex items-center gap-2">
            <Label :id="darkLabelId" :for="darkId">{{ t('blocks.preview.dark') }}</Label>
            <Switch
                :id="darkId"
                :model-value="dark"
                :aria-labelledby="darkLabelId"
                @update:model-value="emit('update:dark', $event === true)"
            />
        </div>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
            {{ t('blocks.preview.viewportAnnounced', { size: label }) }}
        </p>
    </div>
</template>
