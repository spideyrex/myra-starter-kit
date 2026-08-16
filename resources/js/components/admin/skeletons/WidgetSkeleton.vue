<script setup lang="ts">
import SkeletonStat from './SkeletonStat.vue';
import SkeletonChart from './SkeletonChart.vue';
import SkeletonTable from './SkeletonTable.vue';
import SkeletonForm from './SkeletonForm.vue';
import type { WidgetType } from '@/composables/useDashboardWidgets';

withDefaults(defineProps<{
    type: WidgetType | 'form' | 'list' | 'text';
    height?: number;
    rows?: number;
}>(), { rows: 5 });
</script>

<template>
    <SkeletonStat v-if="type === 'stat'" />
    <SkeletonChart v-else-if="type === 'chart'" :height="height" />
    <SkeletonTable v-else-if="type === 'table' || type === 'list'" :rows="rows" />
    <SkeletonForm v-else-if="type === 'form'" />
    <div v-else class="space-y-2" data-skeleton="text">
        <SkeletonForm :fields="1" />
    </div>
</template>
