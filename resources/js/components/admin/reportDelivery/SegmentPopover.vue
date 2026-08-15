<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ArrowUpRight, Filter, GitCompareArrows } from 'lucide-vue-next';
import { useDrillThrough } from '@/composables/useDrillThrough';
import type { ReportRow } from '@/types/report-delivery';

/**
 * A single click on a segment does not navigate. Navigating away from a
 * dashboard on one click is a manager's most expensive misclick, so the click
 * offers the three things they might have meant instead.
 */
const props = withDefaults(defineProps<{
    row: ReportRow;
    measureKey: string;
    measureLabel?: string;
    canCompare?: boolean;
}>(), { measureLabel: '', canCompare: true });

const emit = defineEmits<{ filter: [row: ReportRow]; compare: [] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();
const { go } = useDrillThrough();
const triggerEl = ref<HTMLElement | null>(null);

const count = computed(() => props.row.values?.[props.measureKey] ?? null);
const canDrill = computed(() => props.row.drill !== null && !props.row.isOther);

function show(): void {
    open.value = true;
}

async function close(): Promise<void> {
    open.value = false;
    await nextTick();
    triggerEl.value?.focus();
}

function drill(): void {
    go(props.row.drill);
    open.value = false;
}

function filter(): void {
    emit('filter', props.row);
    open.value = false;
}

function compare(): void {
    emit('compare');
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverAnchor as-child>
            <button
                ref="triggerEl"
                type="button"
                class="rounded-sm text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-expanded="open"
                aria-haspopup="dialog"
                :aria-label="t('reportDelivery.segment.title', { label: row.label })"
                @click="show"
                @keydown.enter.prevent="show"
                @keydown.space.prevent="show"
            >
                <slot :row="row">{{ row.label }}</slot>
            </button>
        </PopoverAnchor>

        <PopoverContent class="w-64 p-1" @keydown.esc.prevent="close">
            <p class="px-2 py-1.5 text-xs font-medium text-muted-foreground">{{ row.label }}</p>

            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span class="block">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :disabled="!canDrill"
                                data-testid="segment-view"
                                @click="drill"
                            >
                                <ArrowUpRight class="size-4 shrink-0" aria-hidden="true" />
                                <span>{{ t('reportDelivery.segment.view', { count: count ?? 0 }) }}</span>
                            </button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent v-if="!canDrill">
                        {{ t('reportDelivery.segment.viewUnavailable') }}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <button
                type="button"
                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                data-testid="segment-filter"
                @click="filter"
            >
                <Filter class="size-4 shrink-0" aria-hidden="true" />
                <span>{{ t('reportDelivery.segment.filter', { label: row.label }) }}</span>
            </button>

            <button
                v-if="canCompare"
                type="button"
                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                data-testid="segment-compare"
                @click="compare"
            >
                <GitCompareArrows class="size-4 shrink-0" aria-hidden="true" />
                <span>{{ t('reportDelivery.segment.compare') }}</span>
            </button>
        </PopoverContent>
    </Popover>
</template>
