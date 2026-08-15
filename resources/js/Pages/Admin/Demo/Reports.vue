<script setup lang="ts">
import { computed, ref, toRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft } from 'lucide-vue-next';
import ReportPage from '@/components/admin/reports/ReportPage.vue';
import TableViewsMenu from '@/components/admin/TableViewsMenu.vue';
import { useReportState } from '@/composables/useReportState';
import { TableView, useTableViews } from '@/composables/useTableViews';
import type { SavedView, TableViewPayload } from '@/types/table-views';
import type { ReportResultPayload, ReportSchema, ReportState } from '@/types/reports';

/**
 * Deliberately backed by the REAL `users` report against real data. There is no
 * HasSampleData provider here: the point of the page is that the aggregation
 * happens in the database, which an in-memory Collection cannot demonstrate.
 */
const props = defineProps<{
    schema: ReportSchema;
    state: ReportState;
    result: ReportResultPayload;
    savedViews: SavedView[];
    canShareViews: boolean;
}>();

const { t } = useI18n();

const report = useReportState({
    reportKey: props.schema.key,
    schema: toRef(props, 'schema'),
    initial: props.state,
    initialResult: props.result,
    syncUrl: false,
});

const views = useTableViews({
    tableKey: computed(() => `report:${props.schema.key}`),
    savedViews: toRef(props, 'savedViews'),
    declared: ref<TableView[]>([]),
    current: () => report.toPayload(),
    apply: (payload: TableViewPayload) => report.applyPayload(payload),
});

const notes = [
    { key: 'period', title: 'reports.toolbar.period' },
    { key: 'bucket', title: 'reports.toolbar.bucket' },
    { key: 'compare', title: 'reports.toolbar.compare' },
    { key: 'dimension', title: 'reports.toolbar.dimension' },
    { key: 'measures', title: 'reports.toolbar.measures' },
    { key: 'truncated', title: 'reports.other' },
    { key: 'views', title: 'reports.toolbar.views' },
    { key: 'export', title: 'reports.toolbar.export' },
];

const activeQuery = computed(() => JSON.stringify(report.state.value, null, 2));
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: t('reports.demo.title') },
    ]">
        <Head :title="t('reports.demo.title')" />

        <PageHeader :title="t('reports.demo.title')" :description="t('reports.demo.description')">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
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
            </ReportPage>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="note in notes" :key="note.key">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">{{ t(note.title) }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">{{ t(`reports.demo.${note.key}`) }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-sm">{{ t('reports.title') }}</CardTitle>
                    <CardDescription>
                        <Badge variant="secondary" class="font-normal">
                            {{ t('reports.groupCount', { count: report.result.value.groupCount }) }}
                        </Badge>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="overflow-x-auto rounded-md bg-muted p-3 text-xs">{{ activeQuery }}</pre>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
