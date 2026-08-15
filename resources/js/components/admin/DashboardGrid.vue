<script setup lang="ts">
import { computed, defineAsyncComponent, h, provide, type Component } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    DASHBOARD_SEGMENT, resolveWidgets, spanClasses,
    type SegmentHandler, type WidgetInput, type WidgetSchema, type WidgetType,
} from '@/composables/useDashboardWidgets';
import type { ReportResultPayload, ReportRow, StatResultPayload } from './charts/types';
import LazyMount from './charts/LazyMount.vue';
import StatWidgetVue from './widgets/StatWidget.vue';
import TableWidgetVue from './widgets/TableWidget.vue';

const props = withDefaults(defineProps<{
    widgets: WidgetInput[];
    pageProps: any;
    /** Server results keyed by widget key. Absent until a report backs the widget. */
    results?: Record<string, ReportResultPayload | StatResultPayload>;
    loading?: Record<string, boolean>;
    /** Override the permission check; defaults to Inertia's shared auth.user. */
    can?: (ability: string) => boolean;
    /** Bundle D supplies cross-filter / drill-through. Default is a no-op. */
    onSegment?: SegmentHandler;
}>(), {
    results: () => ({}),
    loading: () => ({}),
});

/**
 * Adding a widget type no longer edits this file, and a stat-only dashboard
 * never loads chart.js — `chart` resolves lazily.
 */
const CustomWidgetHost: Component = {
    name: 'CustomWidgetHost',
    inheritAttrs: false,
    props: { widget: { type: Object, required: true }, pageProps: { type: null, default: null } },
    setup(hostProps: any) {
        return () => hostProps.widget.component
            ? h(hostProps.widget.component, hostProps.widget.propsFn?.(hostProps.pageProps) ?? {})
            : null;
    },
};

const widgetComponents: Record<WidgetType, Component> = {
    stat: StatWidgetVue,
    chart: defineAsyncComponent(() => import('./widgets/ChartWidget.vue')),
    table: TableWidgetVue,
    custom: CustomWidgetHost,
};

// Identity comes from Inertia's shared props, never from the caller-supplied
// `pageProps` — that is the page's own data and carries no auth.
const page = usePage<any>();

function canDefault(ability: string): boolean {
    const user = page.props?.auth?.user;
    if (!user) return false;
    if (user.roles?.includes('super-admin')) return true;
    return user.permissions?.includes(ability) ?? false;
}

const resolved = computed<WidgetSchema[]>(() =>
    resolveWidgets(props.widgets, props.pageProps, props.can ?? canDefault),
);

provide<SegmentHandler>(DASHBOARD_SEGMENT, (widgetKey, row, measureKey) => {
    props.onSegment?.(widgetKey, row, measureKey);
});

function widgetProps(widget: WidgetSchema): Record<string, any> {
    return {
        widget,
        pageProps: props.pageProps,
        result: props.results?.[widget.key] ?? null,
        loading: props.loading?.[widget.key] ?? false,
    };
}

function onSegmentFrom(widget: WidgetSchema, row: ReportRow, measureKey: string): void {
    props.onSegment?.(widget.key, row, measureKey);
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <template v-for="widget in resolved" :key="widget.key">
            <div :class="spanClasses(widget.colSpan, widget.rowSpan)">
                <LazyMount :enabled="widget.lazy !== false" :min-height="widget.height ?? 160">
                    <component
                        :is="widgetComponents[widget.type]"
                        v-bind="widgetProps(widget)"
                        @segment="(row: ReportRow, measureKey: string) => onSegmentFrom(widget, row, measureKey)"
                    />
                </LazyMount>
            </div>
        </template>
    </div>
</template>
