<script setup lang="ts">
import { computed, shallowRef, toRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import ReportPage from '@/components/admin/reports/ReportPage.vue';
import TableViewsMenu from '@/components/admin/TableViewsMenu.vue';
import { useReportState } from '@/composables/useReportState';
import { TableView, useTableViews } from '@/composables/useTableViews';
import type { SavedView, TableViewPayload } from '@/types/table-views';
import type { ReportResultPayload, ReportSchema, ReportState } from '@/types/reports';

const props = defineProps<{
    schema: ReportSchema;
    state: ReportState;
    result: ReportResultPayload;
    savedViews: SavedView[];
    canShareViews: boolean;
    /** Supplied by the report-delivery bundle; absent until it merges. */
    schedules?: unknown[];
}>();

const { t } = useI18n();

const report = useReportState({
    reportKey: props.schema.key,
    schema: toRef(props, 'schema'),
    initial: props.state,
    initialResult: props.result,
});

// A report view is an ordinary saved table view with a `report:` prefixed key —
// the table_views table, controller, policy and menu are reused unchanged.
const views = useTableViews({
    tableKey: computed(() => `report:${props.schema.key}`),
    savedViews: toRef(props, 'savedViews'),
    // shallowRef: ref() deep-unwraps the TableView class and strips its private members.
    declared: shallowRef<TableView[]>([]),
    current: () => report.toPayload(),
    apply: (payload: TableViewPayload) => report.applyPayload(payload),
});

const scheduleCount = computed(() => (props.schedules ?? []).length);
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: t('reports.title'), href: route('admin.reports.index') },
        { label: t(schema.titleKey) },
    ]">
        <Head :title="t(schema.titleKey)" />

        <PageHeader :title="t(schema.titleKey)" :description="t('reports.subtitle')">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.reports.index')">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        {{ t('reports.backToReports') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <ReportPage :schema="schema" :report="report">
                <template #views>
                    <TableViewsMenu
                        :views="views.all.value"
                        :active="views.active.value"
                        :is-modified="views.isModified.value"
                        :busy="views.busy.value"
                        :can-share-with-team="canShareViews"
                        @apply="views.applyView"
                        @save-as="views.saveAs"
                        @update-active="views.updateActive"
                        @rename="views.rename"
                        @remove="views.remove"
                        @make-default="views.makeDefault"
                    />
                </template>
                <template #actions>
                    <!-- Filled by the report-delivery bundle's schedule dialog. -->
                    <slot name="schedule" :count="scheduleCount" />
                </template>
            </ReportPage>
        </div>
    </AuthenticatedLayout>
</template>
