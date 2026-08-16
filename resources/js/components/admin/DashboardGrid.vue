<script setup lang="ts">
import { computed, defineAsyncComponent, h, provide, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DASHBOARD_SEGMENT, applyLayout, resolveWidgets, spanClasses,
    type ColSpan, type DashboardLayoutPayload, type SegmentHandler,
    type WidgetInput, type WidgetSchema, type WidgetType,
} from '@/composables/useDashboardWidgets';
import { useCan } from '@/composables/useCan';
import { useReorderable } from '@/composables/useReorderable';
import type { ReportResultPayload, ReportRow, StatResultPayload } from './charts/types';
import LazyMount from './charts/LazyMount.vue';
import StatWidgetVue from './widgets/StatWidget.vue';
import TableWidgetVue from './widgets/TableWidget.vue';
import WidgetErrorBoundary from './WidgetErrorBoundary.vue';

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
    // >>> MYRA v2.5 [A]
    layout?: DashboardLayoutPayload | null;
    editing?: boolean;
    // <<< MYRA v2.5 [A]
}>(), {
    results: () => ({}),
    loading: () => ({}),
    editing: false,
});

// >>> MYRA v2.5 [A]
const emit = defineEmits<{
    reorder: [key: string, toIndex: number];
    resize: [key: string, colSpan: ColSpan, rowSpan?: number];
    remove: [key: string];
    rename: [key: string, title: string | null];
    /** Restoring happens from the editor bar, where the hidden tiles are listed. */
    hide: [key: string];
}>();
// <<< MYRA v2.5 [A]

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

// The edit chrome is dead weight outside edit mode.
const WidgetTileChrome = defineAsyncComponent(() => import('./WidgetTileChrome.vue'));

const { t } = useI18n();
const { can } = useCan();

/**
 * Permission filter STRICTLY first: applyLayout is a presentation overlay and
 * is structurally incapable of adding or resurrecting a widget.
 */
const resolved = computed<WidgetSchema[]>(() =>
    applyLayout(resolveWidgets(props.widgets, props.pageProps, props.can ?? can), props.layout),
);

const reorder = useReorderable<WidgetSchema>({
    items: resolved,
    keyOf: widget => widget.key,
    onMove: (key, toIndex) => emit('reorder', key, toIndex),
    announce: () => '',
    enabled: () => props.editing === true,
    roleDescription: () => t('dashboardLayout.a11y.roledescription'),
});

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

function isInstance(widget: WidgetSchema): boolean {
    return widget.key.includes('#');
}
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        :role="editing ? 'list' : undefined"
    >
        <template v-for="(widget, index) in resolved" :key="widget.key">
            <div :class="[spanClasses(widget.colSpan, widget.rowSpan), editing ? 'relative' : '']">
                <WidgetTileChrome
                    v-if="editing"
                    :widget="widget"
                    :editing="editing"
                    :removable="isInstance(widget)"
                    :handle-props="reorder.handleProps(widget, index)"
                    @resize="(colSpan: ColSpan) => emit('resize', widget.key, colSpan)"
                    @toggle-hidden="emit('hide', widget.key)"
                    @remove="emit('remove', widget.key)"
                    @rename="(title: string | null) => emit('rename', widget.key, title)"
                />
                <LazyMount :enabled="widget.lazy !== false" :min-height="widget.height ?? 160">
                    <WidgetErrorBoundary :widget-key="widget.key">
                        <component
                            :is="widgetComponents[widget.type]"
                            v-bind="widgetProps(widget)"
                            @segment="(row: ReportRow, measureKey: string) => onSegmentFrom(widget, row, measureKey)"
                        />
                    </WidgetErrorBoundary>
                </LazyMount>
            </div>
        </template>
    </div>
</template>
