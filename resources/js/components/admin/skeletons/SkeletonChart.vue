<script setup lang="ts">
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { CHART_HEIGHT } from './geometry';

const props = withDefaults(defineProps<{ height?: number; bars?: number }>(), {
    height: CHART_HEIGHT,
    bars: 9,
});

defineOptions({ inheritAttrs: false });

const reserved = computed(() => Math.max(64, Math.trunc(props.height) || CHART_HEIGHT));

// Deterministic, so the skeleton never "shuffles" between renders.
const heights = computed(() => Array.from(
    { length: Math.max(1, props.bars) },
    (_, i) => 35 + ((i * 37) % 60),
));
</script>

<template>
    <!-- Reserves the exact drawing area the chart will occupy. -->
    <div
        class="flex items-end gap-2"
        :style="{ height: `${reserved}px` }"
        data-skeleton="chart"
        v-bind="$attrs"
    >
        <Skeleton
            v-for="(h, i) in heights"
            :key="i"
            class="flex-1 rounded-t-md motion-reduce:animate-none"
            :style="{ height: `${h}%` }"
        />
    </div>
</template>
