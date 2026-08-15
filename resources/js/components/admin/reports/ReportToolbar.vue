<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Download, Sigma } from 'lucide-vue-next';
import PeriodPicker from '@/components/admin/reports/PeriodPicker.vue';
import type {
    Bucket,
    CompareMode,
    PeriodSelection,
    ReportResultPayload,
    ReportSchema,
} from '@/types/reports';

const props = defineProps<{
    schema: ReportSchema;
    result: ReportResultPayload;
    period: PeriodSelection;
    dimension: string;
    bucket: Bucket | null | undefined;
    compare: CompareMode;
    measures: string[];
    chart: string;
    exportHref: (format: string) => string;
}>();

const emit = defineEmits<{
    'update:period': [value: PeriodSelection];
    'update:dimension': [value: string];
    'update:bucket': [value: Bucket];
    'update:compare': [value: CompareMode];
    'update:chart': [value: string];
    'toggle-measure': [key: string];
}>();

const { t } = useI18n();

const activeDimension = computed(() => props.schema.dimensions.find(d => d.key === props.dimension));
const buckets = computed<Bucket[]>(() => activeDimension.value?.allowedBuckets ?? []);
const chartTypes = ['bar', 'stackedBar', 'horizontalBar', 'line', 'area', 'stackedArea', 'pie', 'doughnut', 'table'];

const measureLabel = computed(() => {
    const picked = props.schema.measures.filter(m => props.measures.includes(m.key));
    return picked.length === 1 ? t(picked[0].labelKey) : t('reports.toolbar.measures') + ` (${picked.length})`;
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <PeriodPicker
            :model-value="period"
            :presets="schema.periods"
            :from="result.period.from"
            :to="result.period.to"
            @update:model-value="v => emit('update:period', v)"
        />

        <Select :model-value="dimension" @update:model-value="v => emit('update:dimension', String(v))">
            <SelectTrigger class="w-[11rem]" :aria-label="t('reports.toolbar.dimension')">
                <SelectValue :placeholder="t('reports.toolbar.dimension')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="d in schema.dimensions" :key="d.key" :value="d.key">
                    {{ t(d.labelKey) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            v-if="buckets.length > 1"
            :model-value="bucket ?? result.period.bucket ?? undefined"
            @update:model-value="v => emit('update:bucket', v as Bucket)"
        >
            <SelectTrigger class="w-[9.5rem]" :aria-label="t('reports.toolbar.bucket')">
                <SelectValue :placeholder="t('reports.toolbar.bucket')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="b in buckets" :key="b" :value="b">{{ t(`reports.bucket.${b}`) }}</SelectItem>
            </SelectContent>
        </Select>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline" class="gap-2">
                    <Sigma class="size-4" aria-hidden="true" />
                    <span class="truncate">{{ measureLabel }}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuLabel>{{ t('reports.toolbar.measures') }}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuCheckboxItem
                    v-for="m in schema.measures"
                    :key="m.key"
                    :model-value="measures.includes(m.key)"
                    @select="(e: Event) => e.preventDefault()"
                    @update:model-value="() => emit('toggle-measure', m.key)"
                >
                    {{ t(m.labelKey) }}
                </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Select
            v-if="schema.comparisons.length"
            :model-value="compare"
            @update:model-value="v => emit('update:compare', v as CompareMode)"
        >
            <SelectTrigger class="w-[12rem]" :aria-label="t('reports.toolbar.compare')">
                <SelectValue :placeholder="t('reports.toolbar.compare')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">{{ t('reports.compare.none') }}</SelectItem>
                <SelectItem v-for="c in schema.comparisons" :key="c" :value="c">
                    {{ t(`reports.compare.${c}`) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select :model-value="chart" @update:model-value="v => emit('update:chart', String(v))">
            <SelectTrigger class="w-[10rem]" :aria-label="t('reports.toolbar.chart')">
                <SelectValue :placeholder="t('reports.toolbar.chart')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="type in chartTypes" :key="type" :value="type">
                    {{ t(`reports.chart.${type}`) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <div class="ms-auto flex items-center gap-2">
            <slot name="views" />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="gap-2">
                        <Download class="size-4" aria-hidden="true" />
                        {{ t('reports.toolbar.export') }}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem v-for="format in schema.formats" :key="format" as-child>
                        <a :href="exportHref(format)">{{ t(`reports.export.${format}`) }}</a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <slot name="actions" />
        </div>
    </div>
</template>
