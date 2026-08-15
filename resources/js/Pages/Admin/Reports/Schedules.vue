<script setup lang="ts">
import { computed, ref, toRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Send, Trash2, Pencil } from 'lucide-vue-next';
import ReportScheduleDialog from '@/components/admin/reportDelivery/ReportScheduleDialog.vue';
import { useReportSchedules } from '@/composables/useReportSchedules';
import { useConfirm } from '@/composables/useConfirm';
import type { ReportSchedule, ScheduleInput } from '@/types/report-delivery';

const props = withDefaults(defineProps<{
    schedules: ReportSchedule[];
    reports: Array<{ key: string; titleKey: string }>;
    timezones: string[];
    canMailExternal: boolean;
    people?: Array<{ id: number; name: string; email: string }>;
}>(), { people: () => [] });

const { t, te } = useI18n();
const { confirm } = useConfirm();

const filter = ref('');
const dialogOpen = ref(false);
const editing = ref<ReportSchedule | null>(null);

const {
    schedules, busy, create, update, toggle, remove, sendTest, describe, nextRunLabel,
} = useReportSchedules({
    reportKey: filter,
    schedules: toRef(props, 'schedules'),
});

const activeReportKey = computed(() => filter.value || props.reports[0]?.key || '');

function reportLabel(key: string): string {
    const entry = props.reports.find(r => r.key === key);

    return entry && te(entry.titleKey) ? t(entry.titleKey) : key;
}

function openNew(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(schedule: ReportSchedule): void {
    editing.value = schedule;
    dialogOpen.value = true;
}

async function submit(input: ScheduleInput): Promise<void> {
    if (editing.value) await update(editing.value, input);
    else await create(input);

    dialogOpen.value = false;
}

async function destroy(schedule: ReportSchedule): Promise<void> {
    const ok = await confirm({
        title: t('reportDelivery.schedule.deleteTitle'),
        description: t('reportDelivery.schedule.deleteDescription', { name: schedule.name }),
        variant: 'destructive',
        confirmText: t('reportDelivery.schedule.delete'),
    });

    if (ok) await remove(schedule);
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('reportDelivery.schedule.breadcrumb') }]">
        <Head :title="t('reportDelivery.schedule.title')" />

        <PageHeader
            :title="t('reportDelivery.schedule.title')"
            :description="t('reportDelivery.schedule.subtitle')"
        >
            <template #actions>
                <Button :disabled="!activeReportKey" @click="openNew">
                    <Plus class="mr-2 size-4" aria-hidden="true" />
                    {{ t('reportDelivery.schedule.new') }}
                </Button>
            </template>
        </PageHeader>

        <Card class="mt-6">
            <CardContent class="pt-6">
                <div class="mb-4 flex items-center gap-2">
                    <label for="schedule-filter" class="text-sm text-muted-foreground">
                        {{ t('reportDelivery.schedule.report') }}
                    </label>
                    <NativeSelect id="schedule-filter" v-model="filter" class="w-56">
                        <NativeSelectOption value="">{{ t('reportDelivery.schedule.allReports') }}</NativeSelectOption>
                        <NativeSelectOption v-for="r in reports" :key="r.key" :value="r.key">
                            {{ reportLabel(r.key) }}
                        </NativeSelectOption>
                    </NativeSelect>
                </div>

                <p v-if="!schedules.length" class="py-8 text-center text-sm text-muted-foreground">
                    {{ t('reportDelivery.schedule.empty') }}
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('reportDelivery.schedule.name') }}</TableHead>
                            <TableHead>{{ t('reportDelivery.schedule.cadence') }}</TableHead>
                            <TableHead>{{ t('reportDelivery.schedule.format') }}</TableHead>
                            <TableHead>{{ t('reportDelivery.schedule.nextRun') }}</TableHead>
                            <TableHead>{{ t('reportDelivery.schedule.status') }}</TableHead>
                            <TableHead class="text-right">{{ t('reportDelivery.schedule.actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in schedules" :key="s.id">
                            <TableCell class="font-medium">
                                {{ s.name }}
                                <span class="block text-xs text-muted-foreground">{{ reportLabel(s.report_key) }}</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">{{ describe(s) }}</TableCell>
                            <TableCell>{{ t(`reportDelivery.format.${s.format}`) }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ nextRunLabel(s) }}</TableCell>
                            <TableCell>
                                <Badge :variant="s.is_active ? 'default' : 'secondary'">
                                    {{ s.is_active ? t('reportDelivery.schedule.active') : t('reportDelivery.schedule.paused') }}
                                </Badge>
                                <span v-if="s.last_error" class="mt-1 block text-xs text-destructive">
                                    {{ s.last_error }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="busy"
                                    :aria-label="t('reportDelivery.schedule.sendTest')"
                                    @click="sendTest(s)"
                                >
                                    <Send class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="busy"
                                    :aria-label="s.is_active ? t('reportDelivery.schedule.pause') : t('reportDelivery.schedule.resume')"
                                    @click="toggle(s)"
                                >
                                    {{ s.is_active ? t('reportDelivery.schedule.pause') : t('reportDelivery.schedule.resume') }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="t('reportDelivery.schedule.edit')"
                                    @click="openEdit(s)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="t('reportDelivery.schedule.delete')"
                                    @click="destroy(s)"
                                >
                                    <Trash2 class="size-4 text-destructive" aria-hidden="true" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <ReportScheduleDialog
            v-model:open="dialogOpen"
            :report-key="editing?.report_key ?? activeReportKey"
            :state="editing?.state ?? {}"
            :schedule="editing"
            :timezones="timezones"
            :can-mail-external="canMailExternal"
            :people="people"
            :busy="busy"
            @submit="submit"
        />
    </AuthenticatedLayout>
</template>
