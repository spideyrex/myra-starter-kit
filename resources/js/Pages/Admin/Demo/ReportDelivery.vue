<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CrossFilterBar from '@/components/admin/reportDelivery/CrossFilterBar.vue';
import SegmentPopover from '@/components/admin/reportDelivery/SegmentPopover.vue';
import ReportScheduleDialog from '@/components/admin/reportDelivery/ReportScheduleDialog.vue';
import { provideCrossFilter, type CrossFilterChip } from '@/composables/useCrossFilter';
import type { ReportRow } from '@/types/report-delivery';
import { adminPath } from '@/lib/adminPath';

/**
 * Client-side showcase: the rows below stand in for a ReportResult so the
 * interaction contract is visible without a live aggregation.
 */
const { t } = useI18n();

const rows: ReportRow[] = [
    {
        key: 'active', label: 'Active', values: { signups: 812 }, previous: { signups: 741 },
        deltas: { signups: { absolute: 71, percent: 9.6, direction: 'up', good: true } },
        isOther: false,
        drill: { url: adminPath('users'), params: { query: '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["active"]}],"groups":[]}' } },
    },
    {
        key: 'pending', label: 'Pending', values: { signups: 214 }, previous: { signups: 260 },
        deltas: { signups: { absolute: -46, percent: -17.7, direction: 'down', good: false } },
        isOther: false,
        drill: { url: adminPath('users'), params: { query: '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["pending"]}],"groups":[]}' } },
    },
    {
        key: '__other', label: 'Other', values: { signups: 96 }, previous: null,
        deltas: { signups: null }, isOther: true, drill: null,
    },
];

const batchedRequests = ref(0);

const bus = provideCrossFilter({
    onChange: () => { batchedRequests.value += 1; },
});

const scheduleOpen = ref(false);

function filterBy(row: ReportRow): void {
    const chip: CrossFilterChip = {
        widget: 'demo-status',
        source: 'status',
        label: `Status: ${row.label}`,
        group: { conjunction: 'and', rules: [{ field: 'status', operator: 'in', value: [row.key] }], groups: [] },
    };

    bus.emit('demo-status', chip);
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo' }, { label: t('reportDelivery.demo.title') }]">
        <Head :title="t('reportDelivery.demo.title')" />

        <PageHeader :title="t('reportDelivery.demo.title')" :description="t('reportDelivery.demo.drill')" />

        <div class="mt-6 grid gap-4">
            <CrossFilterBar :chips="bus.chips.value" @remove="bus.clear($event)" @clear="bus.clear()" />

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('reportDelivery.segment.title', { label: 'status' }) }}</CardTitle>
                    <CardDescription>{{ t('reportDelivery.demo.crossFilter') }}</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-3">
                    <SegmentPopover
                        v-for="row in rows"
                        :key="row.key"
                        :row="row"
                        measure-key="signups"
                        @filter="filterBy"
                    >
                        <span class="inline-flex flex-col rounded-md border border-border px-4 py-3 text-left">
                            <span class="text-xs text-muted-foreground">{{ row.label }}</span>
                            <span class="text-lg font-semibold">{{ row.values.signups }}</span>
                        </span>
                    </SegmentPopover>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('reportDelivery.schedule.title') }}</CardTitle>
                    <CardDescription>{{ t('reportDelivery.demo.schedule') }}</CardDescription>
                </CardHeader>
                <CardContent class="flex items-center gap-4">
                    <Button @click="scheduleOpen = true">{{ t('reportDelivery.schedule.new') }}</Button>
                    <p class="text-sm text-muted-foreground">
                        {{ t('reportDelivery.crossFilter.hint') }} ({{ batchedRequests }})
                    </p>
                </CardContent>
            </Card>
        </div>

        <ReportScheduleDialog
            v-model:open="scheduleOpen"
            report-key="users"
            :state="{ period: { preset: 'last_30_days' }, dimension: 'status', measures: ['signups'] }"
            :timezones="['UTC', 'Asia/Kuala_Lumpur']"
            :can-mail-external="false"
            :people="[]"
            @submit="scheduleOpen = false"
        />
    </AuthenticatedLayout>
</template>
