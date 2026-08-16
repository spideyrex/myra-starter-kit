<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VisAxis, VisGroupedBar, VisLine, VisXYContainer } from '@unovis/vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartLegendContent, type ChartConfig } from '@/components/ui/chart';

interface MonthlyRow { month: string; desktop: number; mobile: number }
interface ChannelRow { channel: string; visitors: number }

const props = withDefaults(
    defineProps<{ monthly?: MonthlyRow[]; channels?: ChannelRow[] }>(),
    { monthly: () => [], channels: () => [] },
);

const { t } = useI18n();

/** Series colours come from the theme tokens, never from a literal hex. */
const seriesConfig = computed<ChartConfig>(() => ({
    desktop: { label: t('gallery.componentDemos.chartPrimitives.series.desktop'), color: 'var(--chart-1)' },
    mobile: { label: t('gallery.componentDemos.chartPrimitives.series.mobile'), color: 'var(--chart-2)' },
}));

const channelConfig = computed<ChartConfig>(() => ({
    visitors: { label: t('gallery.componentDemos.chartPrimitives.series.visitors'), color: 'var(--chart-3)' },
}));

const monthIndex = (_row: MonthlyRow, i: number) => i;
const channelIndex = (_row: ChannelRow, i: number) => i;

const monthlyBars = [(row: MonthlyRow) => row.desktop, (row: MonthlyRow) => row.mobile];
const monthlyLine = (row: MonthlyRow) => row.desktop + row.mobile;
const channelBars = [(row: ChannelRow) => row.visitors];

const barColors = ['var(--chart-1)', 'var(--chart-2)'];

function monthTick(value: number): string {
    return props.monthly[value]?.month ?? '';
}

function channelTick(value: number): string {
    return props.channels[value]?.channel ?? '';
}

const monthlyTotal = computed(() => props.monthly.reduce((sum, row) => sum + row.desktop + row.mobile, 0));
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.chartPrimitives.title') }]"
    >
        <Head :title="t('gallery.demos.chartPrimitives.title')" />

        <PageHeader
            :title="t('gallery.demos.chartPrimitives.title')"
            :description="t('gallery.demos.chartPrimitives.description')"
        />

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.chartPrimitives.barTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.chartPrimitives.barDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ChartContainer :config="seriesConfig" class="h-[260px] w-full">
                        <VisXYContainer :data="monthly">
                            <VisGroupedBar :x="monthIndex" :y="monthlyBars" :color="barColors" :rounded-corners="4" />
                            <VisAxis type="x" :tick-format="monthTick" :grid-line="false" />
                            <VisAxis type="y" :tick-line="false" />
                        </VisXYContainer>
                        <ChartLegendContent />
                    </ChartContainer>

                    <!-- Colour is never the only channel: the same numbers, as a table. -->
                    <table class="sr-only">
                        <caption>{{ t('gallery.componentDemos.chartPrimitives.barTitle') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.month') }}</th>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.series.desktop') }}</th>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.series.mobile') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in monthly" :key="row.month">
                                <th scope="row">{{ row.month }}</th>
                                <td>{{ row.desktop }}</td>
                                <td>{{ row.mobile }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.chartPrimitives.lineTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.chartPrimitives.lineDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ChartContainer :config="seriesConfig" class="h-[260px] w-full">
                        <VisXYContainer :data="monthly">
                            <VisLine :x="monthIndex" :y="monthlyLine" color="var(--chart-1)" />
                            <VisAxis type="x" :tick-format="monthTick" :grid-line="false" />
                            <VisAxis type="y" :tick-line="false" />
                        </VisXYContainer>
                    </ChartContainer>

                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('gallery.componentDemos.chartPrimitives.total', { total: monthlyTotal }) }}
                    </p>

                    <table class="sr-only">
                        <caption>{{ t('gallery.componentDemos.chartPrimitives.lineTitle') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.month') }}</th>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.series.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in monthly" :key="row.month">
                                <th scope="row">{{ row.month }}</th>
                                <td>{{ row.desktop + row.mobile }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card class="xl:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.chartPrimitives.channelTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.chartPrimitives.channelDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ChartContainer :config="channelConfig" class="h-[240px] w-full">
                        <VisXYContainer :data="channels">
                            <VisGroupedBar :x="channelIndex" :y="channelBars" color="var(--chart-3)" :rounded-corners="4" />
                            <VisAxis type="x" :tick-format="channelTick" :grid-line="false" />
                            <VisAxis type="y" :tick-line="false" />
                        </VisXYContainer>
                    </ChartContainer>

                    <table class="sr-only">
                        <caption>{{ t('gallery.componentDemos.chartPrimitives.channelTitle') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.channel') }}</th>
                                <th scope="col">{{ t('gallery.componentDemos.chartPrimitives.series.visitors') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in channels" :key="row.channel">
                                <th scope="row">{{ row.channel }}</th>
                                <td>{{ row.visitors }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
