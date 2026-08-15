import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { ReportSchedule, ScheduleInput } from '@/types/report-delivery';

/** Indexed by the stored day_of_week (0 = Sunday), which is what Carbon reports. */
export const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as const;

export interface UseReportSchedulesOptions {
    reportKey: Ref<string>;
    schedules: Ref<ReportSchedule[]>;
    /** Props reloaded after a write so the list refreshes in place. */
    reloadProps?: string[];
}

export function useReportSchedules(opts: UseReportSchedulesOptions) {
    const { t } = useI18n();
    const busy = ref(false);
    const reloadProps = opts.reloadProps ?? ['schedules', 'flash'];

    const schedules: ComputedRef<ReportSchedule[]> = computed(() =>
        opts.schedules.value.filter(s => !opts.reportKey.value || s.report_key === opts.reportKey.value),
    );

    /** Resolves on failure too: errors surface through Inertia's error bag. */
    function write(run: (done: () => void) => void): Promise<void> {
        return new Promise<void>((resolve) => {
            busy.value = true;
            run(() => { busy.value = false; resolve(); });
        });
    }

    function create(input: ScheduleInput): Promise<void> {
        return write((done) => {
            router.post(route('admin.report-schedules.store'), input as unknown as Record<string, any>, {
                preserveState: true, preserveScroll: true, only: reloadProps,
                onSuccess: () => done(), onError: () => done(),
            });
        });
    }

    function update(schedule: ReportSchedule, input: ScheduleInput): Promise<void> {
        return write((done) => {
            router.put(route('admin.report-schedules.update', schedule.id), input as unknown as Record<string, any>, {
                preserveState: true, preserveScroll: true, only: reloadProps,
                onSuccess: () => done(), onError: () => done(),
            });
        });
    }

    function toggle(schedule: ReportSchedule): Promise<void> {
        return update(schedule, { ...toInput(schedule), is_active: !schedule.is_active });
    }

    function remove(schedule: ReportSchedule): Promise<void> {
        return write((done) => {
            router.delete(route('admin.report-schedules.destroy', schedule.id), {
                preserveState: true, preserveScroll: true, only: reloadProps,
                onSuccess: () => done(), onError: () => done(),
            });
        });
    }

    function sendTest(schedule: ReportSchedule): Promise<void> {
        return write((done) => {
            router.post(route('admin.report-schedules.test', schedule.id), {}, {
                preserveState: true, preserveScroll: true, only: reloadProps,
                onSuccess: () => done(), onError: () => done(),
            });
        });
    }

    function time(schedule: ReportSchedule): string {
        return `${String(schedule.hour).padStart(2, '0')}:${String(schedule.minute).padStart(2, '0')}`;
    }

    function describe(schedule: ReportSchedule): string {
        return t(`reportDelivery.describe.${schedule.frequency}`, {
            time: time(schedule),
            tz: schedule.timezone,
            day: t(`reportDelivery.days.${DAY_KEYS[schedule.day_of_week ?? 1]}`),
            dayOfMonth: schedule.day_of_month ?? 1,
        });
    }

    function nextRunLabel(schedule: ReportSchedule): string {
        if (!schedule.next_run_at) return t('reportDelivery.schedule.never');

        try {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium', timeStyle: 'short', timeZone: schedule.timezone,
            }).format(new Date(schedule.next_run_at));
        } catch {
            return schedule.next_run_at;
        }
    }

    return { schedules, busy, create, update, toggle, remove, sendTest, describe, nextRunLabel, toInput };
}

export function toInput(schedule: ReportSchedule): ScheduleInput {
    return {
        report_key: schedule.report_key,
        name: schedule.name,
        state: schedule.state,
        format: schedule.format,
        frequency: schedule.frequency,
        day_of_week: schedule.day_of_week,
        day_of_month: schedule.day_of_month,
        hour: schedule.hour,
        minute: schedule.minute,
        timezone: schedule.timezone,
        recipients: schedule.recipients,
        subject: schedule.subject ?? null,
        message: schedule.message ?? null,
        skip_if_empty: schedule.skip_if_empty,
        is_active: schedule.is_active,
    };
}
