<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { TriangleAlert } from 'lucide-vue-next';
import ReportToolbar from '@/components/admin/reports/ReportToolbar.vue';
import ReportFilterBar from '@/components/admin/reports/ReportFilterBar.vue';
import ReportTable from '@/components/admin/reports/ReportTable.vue';
import type { useReportState } from '@/composables/useReportState';
import type { QueryGroup } from '@/types/admin';
import type { ReportSchema } from '@/types/reports';

/**
 * The layout shell for one report: toolbar, filter bar, and the result.
 *
 * The state composable is owned by the PAGE, not by this shell, so the page can
 * wire saved views to the same `current()` / `apply()` pair.
 *
 * SEAM: the chart bundle fills the `#visual` slot with a real chart. Until then
 * the table IS the visualisation — and it stays the accessible fallback the
 * chart's "view as table" toggle resolves to.
 */
const props = defineProps<{
    schema: ReportSchema;
    report: ReturnType<typeof useReportState>;
}>();

const { t } = useI18n();

const state = computed(() => props.report.state.value);
const result = computed(() => props.report.result.value);

const truncatedNotice = computed(() => t('reports.truncated', {
    shown: result.value.rows.filter(r => !r.isOther).length,
    total: result.value.groupCount,
}));
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3">
            <ReportToolbar
                :schema="schema"
                :result="result"
                :period="state.period"
                :dimension="state.dimension"
                :bucket="state.bucket"
                :compare="state.compare"
                :measures="state.measures"
                :chart="state.chart ?? schema.defaults.chart"
                :export-href="report.exportHref"
                @update:period="report.setPeriod"
                @update:dimension="report.setDimension"
                @update:bucket="report.setBucket"
                @update:compare="report.setCompare"
                @update:chart="report.setChart"
                @toggle-measure="report.toggleMeasure"
            >
                <template #views><slot name="views" /></template>
                <template #actions><slot name="actions" /></template>
            </ReportToolbar>

            <ReportFilterBar
                v-if="schema.fields.fields.length"
                :schema="schema"
                :model-value="(state.query as QueryGroup | null) ?? null"
                @update:model-value="report.setQuery"
            />
        </div>

        <Alert v-if="report.error.value" variant="destructive">
            <TriangleAlert class="size-4" aria-hidden="true" />
            <AlertDescription>{{ t(report.error.value) }}</AlertDescription>
        </Alert>

        <Card>
            <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle class="text-base">{{ t(schema.titleKey) }}</CardTitle>
                <div class="flex items-center gap-2">
                    <Badge v-if="result.truncated" variant="secondary">{{ truncatedNotice }}</Badge>
                    <span v-if="report.loading.value" role="status" class="text-xs text-muted-foreground">
                        {{ t('reports.updating') }}
                    </span>
                </div>
            </CardHeader>
            <CardContent :aria-busy="report.loading.value">
                <slot name="visual" :result="result">
                    <ReportTable :result="result" />
                </slot>
            </CardContent>
        </Card>
    </div>
</template>
