<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { seriesColor, withAlpha, type ChartTheme } from './chartTheme';
import { formatMeasure } from './format';
import type { ReportResultPayload, ReportRow } from './types';

const props = withDefaults(defineProps<{
    theme: ChartTheme;
    result?: ReportResultPayload | null;
    measures?: string[];
    /** Overrides the measure's declared goal. */
    goal?: number | null;
}>(), { result: null, goal: null });

const emit = defineEmits<{
    (e: 'segment', row: ReportRow, measureKey: string): void;
}>();

const { t } = useI18n();

const W = 120;
const H = 72;
const R = 46;
const CX = 60;
const CY = 60;

const measure = computed(() => {
    const all = props.result?.measures ?? [];
    return all.find(m => !props.measures || props.measures.includes(m.key)) ?? all[0] ?? null;
});

const value = computed<number | null>(() => {
    const key = measure.value?.key;
    if (!key) return null;

    const total = props.result?.totals?.[key];
    if (typeof total === 'number') return total;

    const rows = props.result?.rows ?? [];
    if (rows.length === 0) return null;

    return rows.reduce<number>((sum, row) => sum + (row.values[key] ?? 0), 0);
});

const goal = computed<number | null>(() => props.goal ?? measure.value?.goal ?? null);

const ratio = computed(() => {
    if (value.value === null || !goal.value) return 0;
    return Math.max(0, Math.min(1, value.value / goal.value));
});

const met = computed(() => {
    if (value.value === null || !goal.value) return false;
    const reached = value.value >= goal.value;
    return measure.value?.invertTrend ? !reached : reached;
});

const color = computed(() => {
    if (!goal.value) return seriesColor(props.theme, 0);
    return met.value ? props.theme.good : props.theme.bad;
});

/** Semicircle from 180° to 0°. */
function point(fraction: number): [number, number] {
    const angle = Math.PI * (1 - fraction);
    return [CX + R * Math.cos(angle), CY - R * Math.sin(angle)];
}

function arc(fraction: number): string {
    const [sx, sy] = point(0);
    const [ex, ey] = point(Math.max(fraction, 0.0001));
    return `M ${sx.toFixed(2)} ${sy.toFixed(2)} A ${R} ${R} 0 0 1 ${ex.toFixed(2)} ${ey.toFixed(2)}`;
}

const trackPath = computed(() => arc(1));
const valuePath = computed(() => arc(ratio.value));

const display = computed(() => formatMeasure(value.value, measure.value?.format, measure.value?.decimals ?? 0));
const goalDisplay = computed(() =>
    goal.value === null ? null : formatMeasure(goal.value, measure.value?.format, measure.value?.decimals ?? 0),
);

const summary = computed(() => goal.value === null
    ? t('charts.a11y.gaugeNoGoal', {
        measure: measure.value ? t(measure.value.labelKey) : '',
        value: display.value,
    })
    : t('charts.a11y.gauge', {
        measure: measure.value ? t(measure.value.labelKey) : '',
        value: display.value,
        goal: goalDisplay.value ?? '',
        percent: Math.round(ratio.value * 100),
    }));

function activate(): void {
    const row = props.result?.rows?.[0];
    if (!row || !measure.value) return;
    emit('segment', row, measure.value.key);
}

const hasData = computed(() => value.value !== null);
</script>

<template>
    <svg
        v-if="hasData"
        class="myra-gauge size-full"
        :viewBox="`0 0 ${W} ${H}`"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        :aria-label="summary"
    >
        <title>{{ summary }}</title>

        <path :d="trackPath" fill="none" :stroke="withAlpha(theme.muted, 0.2)" stroke-width="10" stroke-linecap="round" />

        <g
            class="myra-segment"
            role="button"
            tabindex="0"
            :aria-label="summary"
            @click="activate"
            @keydown.enter.prevent="activate"
            @keydown.space.prevent="activate"
        >
            <path :d="valuePath" fill="none" :stroke="color" stroke-width="10" stroke-linecap="round" />
            <text :x="CX" :y="CY - 8" text-anchor="middle" font-size="15" font-weight="600" fill="currentColor">
                {{ display }}
            </text>
            <text v-if="goalDisplay" :x="CX" :y="CY + 6" text-anchor="middle" font-size="8" :fill="theme.tick">
                {{ t('charts.goalOf', { goal: goalDisplay }) }}
            </text>
        </g>
    </svg>

    <p v-else class="text-sm text-muted-foreground">{{ t('charts.empty.noData') }}</p>
</template>

<style scoped>
.myra-segment path {
    transition: stroke 150ms ease;
}

.myra-segment:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
    .myra-segment path {
        transition: none;
    }
}
</style>
