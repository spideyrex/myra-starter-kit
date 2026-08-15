<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { TrendingDown, TrendingUp, Minus } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { formatDeltaPercent, formatMeasure } from './format';
import type { Delta, MeasureFormat } from './types';

const props = withDefaults(defineProps<{
    delta: Delta | null | undefined;
    format?: MeasureFormat;
    decimals?: number;
    /** Rendered inside a coloured stat card, which owns its own contrast. */
    onCard?: boolean;
    class?: string;
}>(), { format: 'number', decimals: 0, onCard: false });

const { t } = useI18n();

/**
 * Colour follows delta.good, NOT delta.direction — that is what makes an
 * invertTrend measure (churn rising) read as bad rather than as growth.
 */
const tone = computed(() => {
    if (!props.delta) return 'neutral';
    if (props.delta.direction === 'flat') return 'neutral';
    return props.delta.good ? 'good' : 'bad';
});

const icon = computed(() => {
    if (!props.delta || props.delta.direction === 'flat') return Minus;
    return props.delta.direction === 'up' ? TrendingUp : TrendingDown;
});

const percentLabel = computed(() => formatDeltaPercent(props.delta?.percent ?? null));

const absoluteLabel = computed(() => {
    if (!props.delta) return '—';
    const sign = props.delta.absolute > 0 ? '+' : '';
    return `${sign}${formatMeasure(props.delta.absolute, props.format, props.decimals)}`;
});

const toneClass = computed(() => {
    if (props.onCard) return 'bg-white/20 text-white';
    if (tone.value === 'good') return 'text-success';
    if (tone.value === 'bad') return 'text-destructive';
    return 'text-muted-foreground';
});

const ariaLabel = computed(() => t('charts.a11y.delta', {
    direction: t(`charts.delta.${props.delta?.direction ?? 'flat'}`),
    percent: percentLabel.value,
    absolute: absoluteLabel.value,
    verdict: t(tone.value === 'bad' ? 'charts.delta.worse' : 'charts.delta.better'),
}));
</script>

<template>
    <span
        v-if="delta"
        :class="cn('inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-medium', toneClass, props.class)"
        :aria-label="ariaLabel"
        :title="absoluteLabel"
    >
        <component :is="icon" class="size-3 shrink-0" aria-hidden="true" />
        <span>{{ percentLabel }}</span>
    </span>

    <span v-else :class="cn('text-xs text-muted-foreground', props.class)">
        {{ t('charts.delta.none') }}
    </span>
</template>
