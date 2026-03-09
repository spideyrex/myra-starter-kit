<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

const props = defineProps<{
    widget: WidgetSchema;
    pageProps: any;
}>();

const value = props.widget.valueFn?.(props.pageProps) ?? 0;
const description = props.widget.descriptionFn?.(props.pageProps);
const trend = props.widget.trendFn?.(props.pageProps) ?? undefined;
</script>

<template>
    <StatCard
        :title="widget.title || ''"
        :value="value"
        :icon="widget.icon"
        :description="description"
        :trend="trend"
    />
</template>
