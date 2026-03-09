<script setup lang="ts">
import { computed } from 'vue';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';
import StatWidgetVue from './widgets/StatWidget.vue';
import ChartWidgetVue from './widgets/ChartWidget.vue';
import TableWidgetVue from './widgets/TableWidget.vue';

type WidgetInput = WidgetSchema | { toSchema(): WidgetSchema };

const props = defineProps<{
    widgets: WidgetInput[];
    pageProps: any;
}>();

const resolvedWidgets = computed(() => {
    return props.widgets.map(w => {
        if ('toSchema' in w && typeof w.toSchema === 'function') {
            return w.toSchema();
        }
        return w as WidgetSchema;
    });
});
</script>

<template>
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
        <template v-for="widget in resolvedWidgets" :key="widget.key">
            <div :style="widget.colSpan > 1 ? `grid-column: span ${widget.colSpan}` : ''">
                <!-- Stat widget -->
                <StatWidgetVue
                    v-if="widget.type === 'stat'"
                    :widget="widget"
                    :page-props="pageProps"
                />

                <!-- Chart widget -->
                <ChartWidgetVue
                    v-else-if="widget.type === 'chart'"
                    :widget="widget"
                    :page-props="pageProps"
                />

                <!-- Table widget -->
                <TableWidgetVue
                    v-else-if="widget.type === 'table'"
                    :widget="widget"
                    :page-props="pageProps"
                />

                <!-- Custom widget -->
                <component
                    v-else-if="widget.type === 'custom' && widget.component"
                    :is="widget.component"
                    v-bind="widget.propsFn?.(pageProps) ?? {}"
                />
            </div>
        </template>
    </div>
</template>
