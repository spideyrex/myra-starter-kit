<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import {
    TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText,
} from '@/components/ui/tags-input';
import { DAY_KEYS, toInput } from '@/composables/useReportSchedules';
import type {
    ReportSchedule, ScheduleFormat, ScheduleFrequency, ScheduleInput, ScheduleRecipient,
} from '@/types/report-delivery';

/**
 * Opens prefilled with the state on screen: "email me this, every Monday, 8am"
 * should be three clicks from looking at it, not a form you fill in twice.
 */
const props = withDefaults(defineProps<{
    reportKey: string;
    state: Record<string, unknown>;
    schedule?: ReportSchedule | null;
    timezones?: string[];
    canMailExternal?: boolean;
    /** Teammates the owner may address. Resolved server-side again on submit. */
    people?: Array<{ id: number; name: string; email: string }>;
    /** Disables the pdf option when the active locale is not Latin-scripted. */
    pdfAvailable?: boolean;
    busy?: boolean;
}>(), {
    schedule: null,
    timezones: () => ['UTC'],
    canMailExternal: false,
    people: () => [],
    pdfAvailable: true,
    busy: false,
});

const emit = defineEmits<{ submit: [input: ScheduleInput] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const FORMATS: ScheduleFormat[] = ['pdf', 'xlsx', 'csv', 'inline'];
const FREQUENCIES: ScheduleFrequency[] = ['daily', 'weekly', 'monthly', 'quarterly'];

function blank(): ScheduleInput {
    return {
        report_key: props.reportKey,
        name: '',
        state: props.state,
        format: props.pdfAvailable ? 'pdf' : 'xlsx',
        frequency: 'weekly',
        day_of_week: 1,
        day_of_month: 1,
        hour: 8,
        minute: 0,
        timezone: props.timezones[0] ?? 'UTC',
        recipients: [],
        subject: null,
        message: null,
        skip_if_empty: false,
        is_active: true,
    };
}

const form = ref<ScheduleInput>(blank());
const userIds = ref<number[]>([]);
const emails = ref<string[]>([]);

watch([open, () => props.schedule], () => {
    if (!open.value) return;

    form.value = props.schedule ? { ...toInput(props.schedule), state: props.schedule.state } : blank();
    userIds.value = form.value.recipients.filter(r => r.type === 'user').map(r => (r as { id: number }).id);
    emails.value = form.value.recipients
        .filter(r => r.type === 'email')
        .map(r => (r as { address: string }).address);
}, { immediate: true });

const showDayOfWeek = computed(() => form.value.frequency === 'weekly');
const showDayOfMonth = computed(() => form.value.frequency === 'monthly' || form.value.frequency === 'quarterly');

// Input/Textarea model `string | number`; these fields are nullable on the wire.
const dayOfMonth = computed<string | number>({
    get: () => form.value.day_of_month ?? 1,
    set: (v) => { form.value.day_of_month = Number(v) || 1; },
});

const message = computed<string | number>({
    get: () => form.value.message ?? '',
    set: (v) => { form.value.message = String(v).trim() === '' ? null : String(v); },
});

const recipients = computed<ScheduleRecipient[]>(() => [
    ...userIds.value.map(id => ({ type: 'user', id }) as ScheduleRecipient),
    ...(props.canMailExternal ? emails.value.map(address => ({ type: 'email', address }) as ScheduleRecipient) : []),
]);

const valid = computed(() => form.value.name.trim().length > 0 && recipients.value.length > 0);

function togglePerson(id: number): void {
    userIds.value = userIds.value.includes(id)
        ? userIds.value.filter(existing => existing !== id)
        : [...userIds.value, id];
}

function submit(): void {
    if (!valid.value) return;

    emit('submit', {
        ...form.value,
        report_key: props.reportKey,
        recipients: recipients.value,
        day_of_week: showDayOfWeek.value ? form.value.day_of_week : null,
        day_of_month: showDayOfMonth.value ? form.value.day_of_month : null,
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ schedule ? t('reportDelivery.schedule.edit') : t('reportDelivery.schedule.new') }}</DialogTitle>
                <DialogDescription>{{ t('reportDelivery.schedule.description') }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="schedule-name">{{ t('reportDelivery.schedule.name') }}</Label>
                    <Input id="schedule-name" v-model="form.name" maxlength="60" />
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="schedule-format">{{ t('reportDelivery.schedule.format') }}</Label>
                        <NativeSelect id="schedule-format" v-model="form.format" class="w-full">
                            <NativeSelectOption
                                v-for="f in FORMATS"
                                :key="f"
                                :value="f"
                                :disabled="f === 'pdf' && !pdfAvailable"
                            >
                                {{ t(`reportDelivery.format.${f}`) }}
                            </NativeSelectOption>
                        </NativeSelect>
                        <p v-if="!pdfAvailable" class="text-xs text-muted-foreground">
                            {{ t('reportDelivery.pdf.unsupportedLocale') }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="schedule-frequency">{{ t('reportDelivery.schedule.frequency') }}</Label>
                        <NativeSelect id="schedule-frequency" v-model="form.frequency" class="w-full">
                            <NativeSelectOption v-for="f in FREQUENCIES" :key="f" :value="f">
                                {{ t(`reportDelivery.frequency.${f}`) }}
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div v-if="showDayOfWeek" class="grid gap-2">
                        <Label for="schedule-dow">{{ t('reportDelivery.schedule.dayOfWeek') }}</Label>
                        <NativeSelect id="schedule-dow" v-model="form.day_of_week" class="w-full">
                            <NativeSelectOption v-for="(key, i) in DAY_KEYS" :key="key" :value="i">
                                {{ t(`reportDelivery.days.${key}`) }}
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>

                    <div v-if="showDayOfMonth" class="grid gap-2">
                        <Label for="schedule-dom">{{ t('reportDelivery.schedule.dayOfMonth') }}</Label>
                        <Input id="schedule-dom" v-model="dayOfMonth" type="number" min="1" max="31" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="schedule-hour">{{ t('reportDelivery.schedule.time') }}</Label>
                        <div class="flex items-center gap-1">
                            <Input id="schedule-hour" v-model="form.hour" type="number" min="0" max="23" class="w-16" />
                            <span aria-hidden="true">:</span>
                            <Input
                                v-model="form.minute"
                                type="number"
                                min="0"
                                max="59"
                                class="w-16"
                                :aria-label="t('reportDelivery.schedule.minute')"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="schedule-tz">{{ t('reportDelivery.schedule.timezone') }}</Label>
                        <NativeSelect id="schedule-tz" v-model="form.timezone" class="w-full">
                            <NativeSelectOption v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</NativeSelectOption>
                        </NativeSelect>
                    </div>
                </div>

                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">{{ t('reportDelivery.schedule.recipients') }}</legend>

                    <div v-if="people.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="person in people"
                            :key="person.id"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="userIds.includes(person.id) ? 'border-primary bg-primary/10 text-foreground' : 'border-border text-muted-foreground'"
                            :aria-pressed="userIds.includes(person.id)"
                            @click="togglePerson(person.id)"
                        >
                            {{ person.name }}
                        </button>
                    </div>

                    <div class="grid gap-1">
                        <Label for="schedule-emails" class="text-xs text-muted-foreground">
                            {{ t('reportDelivery.schedule.externalEmails') }}
                        </Label>
                        <TagsInput v-if="canMailExternal" id="schedule-emails" v-model="emails">
                            <TagsInputItem v-for="address in emails" :key="address" :value="address">
                                <TagsInputItemText />
                                <TagsInputItemDelete />
                            </TagsInputItem>
                            <TagsInputInput :placeholder="t('reportDelivery.schedule.addEmail')" />
                        </TagsInput>
                        <p v-else class="text-xs text-muted-foreground">
                            {{ t('reportDelivery.schedule.externalDenied') }}
                        </p>
                    </div>
                </fieldset>

                <div class="grid gap-2">
                    <Label for="schedule-message">{{ t('reportDelivery.schedule.message') }}</Label>
                    <Textarea id="schedule-message" v-model="message" rows="2" maxlength="2000" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <Label for="schedule-skip" class="text-sm font-normal">{{ t('reportDelivery.schedule.skipIfEmpty') }}</Label>
                    <Switch id="schedule-skip" v-model="form.skip_if_empty" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <Label for="schedule-active" class="text-sm font-normal">{{ t('reportDelivery.schedule.active') }}</Label>
                    <Switch id="schedule-active" v-model="form.is_active" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ t('reportDelivery.schedule.cancel') }}</Button>
                <Button :disabled="!valid || busy" @click="submit">{{ t('reportDelivery.schedule.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
