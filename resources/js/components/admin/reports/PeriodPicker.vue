<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CalendarRange, Check } from 'lucide-vue-next';
import type { PeriodPreset, PeriodSelection } from '@/types/reports';

const props = defineProps<{
    modelValue: PeriodSelection;
    presets: PeriodPreset[];
    /** Resolved window from the server, so the trigger shows real dates. */
    from: string;
    to: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: PeriodSelection] }>();

const { t } = useI18n();

const open = ref(false);
const customFrom = ref(props.from);
const customTo = ref(props.to);

const label = computed(() =>
    props.modelValue.preset === 'custom'
        ? t('reports.period.range', { from: props.from, to: props.to })
        : t(`reports.period.${props.modelValue.preset}`));

function choose(preset: PeriodPreset): void {
    if (preset === 'custom') {
        emit('update:modelValue', { preset: 'custom', from: customFrom.value, to: customTo.value });
    } else {
        emit('update:modelValue', { preset });
    }
    open.value = false;
}

function applyCustom(): void {
    if (!customFrom.value || !customTo.value) return;
    emit('update:modelValue', { preset: 'custom', from: customFrom.value, to: customTo.value });
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button variant="outline" class="justify-start gap-2" :aria-label="t('reports.toolbar.period')">
                <CalendarRange class="size-4" aria-hidden="true" />
                <span class="truncate">{{ label }}</span>
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-72 p-0" align="start">
            <div role="listbox" :aria-label="t('reports.period.label')" class="max-h-64 overflow-y-auto p-1">
                <button
                    v-for="preset in presets"
                    :key="preset"
                    type="button"
                    role="option"
                    :aria-selected="modelValue.preset === preset"
                    class="flex w-full items-center justify-between rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @click="choose(preset)"
                >
                    <span>{{ t(`reports.period.${preset}`) }}</span>
                    <Check v-if="modelValue.preset === preset" class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div class="border-t p-3">
                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <Label for="report-period-from" class="text-xs">{{ t('reports.period.from') }}</Label>
                        <Input id="report-period-from" v-model="customFrom" type="date" class="h-8" />
                    </div>
                    <div class="space-y-1">
                        <Label for="report-period-to" class="text-xs">{{ t('reports.period.to') }}</Label>
                        <Input id="report-period-to" v-model="customTo" type="date" class="h-8" />
                    </div>
                </div>
                <Button size="sm" class="mt-2 w-full" :disabled="!customFrom || !customTo" @click="applyCustom">
                    {{ t('reports.period.apply') }}
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
