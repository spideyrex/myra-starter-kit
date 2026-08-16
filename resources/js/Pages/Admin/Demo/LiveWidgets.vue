<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Users } from 'lucide-vue-next';
import DataSurface from '@/components/admin/DataSurface.vue';
import LiveBadge from '@/components/admin/dashboard/LiveBadge.vue';
import SkeletonStat from '@/components/admin/skeletons/SkeletonStat.vue';
import { STAT_HEIGHT } from '@/components/admin/skeletons/geometry';
import StatWidgetVue from '@/components/admin/widgets/StatWidget.vue';
import ChartWidgetVue from '@/components/admin/widgets/ChartWidget.vue';
import TableWidgetVue from '@/components/admin/widgets/TableWidget.vue';
import { ChartWidget, StatWidget, TableWidget } from '@/composables/useDashboardWidgets';
import { useDashboardBus, type BatchBody } from '@/composables/useDashboardBus';
import type { SurfaceState } from '@/composables/useAsyncSurface';
import type { ReportResultPayload, StatResultPayload } from '@/components/admin/charts/types';

defineProps<{
    realtime: { enabled: boolean; coalesceMs: number; defaultPoll: number; channel: string };
    rowHeight: number;
}>();

const { t } = useI18n();

// --- canned server payloads, in the frozen wire shape -----------------------

let seed = 1;

function statPayload(): StatResultPayload {
    seed += 1;
    return {
        report: 'users', measure: 'signups', value: 120 + seed, previous: 118,
        delta: { absolute: seed, percent: 1.7, direction: 'up', good: true },
        spark: [3, 5, 4, 8, 6, 9], format: 'number', decimals: 0, goal: null, drill: null,
    };
}

function seriesPayload(): ReportResultPayload {
    seed += 1;
    return {
        report: 'users',
        state: { period: { preset: 'last_30_days' }, compare: 'none', dimension: 'created_at', measures: ['signups'] },
        period: { preset: 'last_30_days', from: '2026-07-17', to: '2026-08-16', tz: 'UTC', bucket: 'day' },
        comparison: null,
        dimension: { key: 'created_at', labelKey: 'reports.dim.signupDate', type: 'date', drillable: false, allowedBuckets: ['day'] },
        measures: [{ key: 'signups', labelKey: 'reports.measure.signups', format: 'number', decimals: 0, goal: null, invertTrend: false, additive: true }],
        rows: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'].map((label, i) => ({
            key: label, label,
            values: { signups: 4 + ((i * 3 + seed) % 11) },
            previous: null, deltas: {}, isOther: false, drill: null,
        })),
        totals: { signups: 42 }, previousTotals: null, deltas: {},
        truncated: false, groupCount: 5,
    };
}

// --- the bus ----------------------------------------------------------------

const requests = ref(0);
const signals = ref(0);
const lastBody = ref<BatchBody | null>(null);
const replyUnchanged = ref(false);
const failSlot = ref<string | null>(null);

const bus = useDashboardBus({
    maxBatch: 12,
    coalesceMs: 400,
    channel: null,
    transport: async (body) => {
        requests.value += 1;
        lastBody.value = body;

        if (failSlot.value && Object.keys(body.widgets).includes(failSlot.value)) {
            throw new Error('simulated slot failure');
        }

        const results: Record<string, any> = {};

        for (const slot of Object.keys(body.widgets)) {
            if (replyUnchanged.value && body.versions[slot]) {
                results[slot] = { unchanged: true, version: body.versions[slot] };
                continue;
            }

            const payload: any = slot.startsWith('stat')
                ? statPayload()
                : seriesPayload();

            payload.version = `v${seed}`;
            results[slot] = payload;
        }

        return { results };
    },
});

// --- six widgets, one bus ---------------------------------------------------

const widgets = [
    StatWidget.make('stat-signups').title('Signups').report('users').measure('signups').icon(Users).toSchema(),
    StatWidget.make('stat-active').title('Active').report('users').measure('signups').toSchema(),
    StatWidget.make('stat-verified').title('Verified').report('users').measure('signups').toSchema(),
    ChartWidget.make('chart-daily').title('Daily signups').type('bar').report('users').dimension('created_at').measures(['signups']).height(240).toSchema(),
    ChartWidget.make('chart-weekly').title('Weekly signups').type('line').report('users').dimension('created_at').measures(['signups']).height(240).toSchema(),
    TableWidget.make('table-recent').title('Recent buckets').report('users').measures(['signups']).toSchema(),
];

const mounted = ref(false);

function mountAll(): void {
    mounted.value = true;
    for (const w of widgets) {
        if (w.binding) bus.track(w.key, w.binding);
    }
}

function unmountAll(): void {
    mounted.value = false;
    for (const w of widgets) bus.untrack(w.key);
}

function burst(): void {
    // Thirty frames inside coalesceMs. The debounce turns them into ONE POST.
    for (let i = 0; i < 30; i++) {
        window.setTimeout(() => {
            signals.value += 1;
            bus.signal(['users']);
        }, i * 10);
    }
}

// --- hidden-tab stub --------------------------------------------------------

const forcedHidden = ref(false);
let originalDescriptor: PropertyDescriptor | undefined;

function setHidden(hidden: boolean): void {
    if (!originalDescriptor) {
        originalDescriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'visibilityState');
    }

    forcedHidden.value = hidden;

    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => (hidden ? 'hidden' : 'visible'),
    });

    document.dispatchEvent(new Event('visibilitychange'));
}

onBeforeUnmount(() => {
    if (forcedHidden.value) setHidden(false);
});

function reset(): void {
    unmountAll();
    requests.value = 0;
    signals.value = 0;
    lastBody.value = null;
    replyUnchanged.value = false;
    failSlot.value = null;
    if (forcedHidden.value) setHidden(false);
}

function resultFor(key: string): any {
    return bus.results.value[key]?.payload ?? null;
}

function stateFor(key: string): SurfaceState {
    if (bus.errored.value[key]) return 'error';
    if (bus.loading.value[key]) return 'loading';
    return bus.results.value[key] ? 'ready' : 'idle';
}

// --- surface state showcase -------------------------------------------------

const STATES: SurfaceState[] = ['idle', 'loading', 'streaming', 'ready', 'empty', 'error', 'offline'];
const showcase = ref<SurfaceState>('loading');
const announced = ref('');

// Mirror the sr-only sentence into something sighted reviewers can read.
watch(showcase, (next) => {
    const keys: Record<string, string> = {
        loading: 'live.a11y.loading', streaming: 'live.a11y.streaming', ready: 'live.a11y.ready',
        empty: 'live.a11y.empty', error: 'live.a11y.error', offline: 'live.a11y.offline',
    };
    const key = keys[next];
    announced.value = key ? t(key, { label: t('live.demo.statesTitle') }) : '—';
}, { immediate: true });

const busy = computed(() => Object.values(bus.loading.value).some(Boolean));
</script>

<template>
    <Head :title="t('live.demo.title')" />

    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo'), href: route('admin.demo.index') }, { label: t('live.demo.title') }]"
    >
        <div class="space-y-6 p-4 sm:p-6">
        <PageHeader :title="t('live.demo.title')" :description="t('live.demo.subtitle')">
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

            <!-- 1. The bus -->
            <Card>
                <CardHeader>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <CardTitle>{{ t('live.demo.busTitle') }}</CardTitle>
                            <CardDescription>{{ t('live.demo.busDescription') }}</CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{ realtime.enabled ? t('live.connected') : t('live.polling') }}
                            </Badge>
                            <LiveBadge :connected="bus.connected.value" :at="Date.now()" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" @click="mountAll">{{ t('live.demo.mountSix') }}</Button>
                        <Button size="sm" variant="outline" :disabled="!mounted" @click="burst">
                            {{ t('live.demo.burst') }}
                        </Button>
                        <Button size="sm" variant="outline" @click="setHidden(!forcedHidden)">
                            {{ forcedHidden ? t('live.demo.show') : t('live.demo.hide') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :aria-pressed="replyUnchanged"
                            @click="replyUnchanged = !replyUnchanged"
                        >
                            {{ t('live.demo.unchanged') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :aria-pressed="failSlot !== null"
                            @click="failSlot = failSlot ? null : 'table-recent'"
                        >
                            {{ t('live.demo.failOne') }}
                        </Button>
                        <Button size="sm" variant="ghost" @click="reset">{{ t('live.demo.reset') }}</Button>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border p-3">
                            <dt class="text-xs text-muted-foreground">{{ t('live.demo.requestCount') }}</dt>
                            <dd class="text-2xl font-bold tabular-nums" data-request-count>{{ requests }}</dd>
                        </div>
                        <div class="rounded-lg border p-3">
                            <dt class="text-xs text-muted-foreground">{{ t('live.demo.signalCount') }}</dt>
                            <dd class="text-2xl font-bold tabular-nums">{{ signals }}</dd>
                        </div>
                        <div class="rounded-lg border p-3">
                            <dt class="text-xs text-muted-foreground">{{ t('live.paused') }}</dt>
                            <dd class="text-2xl font-bold">{{ bus.paused.value ? '✓' : '—' }}</dd>
                        </div>
                        <div class="rounded-lg border p-3">
                            <dt class="text-xs text-muted-foreground">{{ t('live.requests') }}</dt>
                            <dd class="truncate text-xs font-mono">{{ Object.keys(lastBody?.widgets ?? {}).length }} slots</dd>
                        </div>
                    </dl>

                    <div v-if="mounted" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" :aria-busy="busy">
                        <StatWidgetVue
                            v-for="w in widgets.filter(x => x.type === 'stat')"
                            :key="w.key"
                            :widget="w"
                            :result="resultFor(w.key)"
                            :state="stateFor(w.key)"
                            class="sm:col-span-1"
                        />
                        <ChartWidgetVue
                            v-for="w in widgets.filter(x => x.type === 'chart')"
                            :key="w.key"
                            :widget="w"
                            :result="resultFor(w.key)"
                            :state="stateFor(w.key)"
                            class="sm:col-span-2"
                        />
                        <TableWidgetVue
                            v-for="w in widgets.filter(x => x.type === 'table')"
                            :key="w.key"
                            :widget="w"
                            :result="resultFor(w.key)"
                            :state="stateFor(w.key)"
                            class="sm:col-span-2"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Every surface state -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('live.demo.statesTitle') }}</CardTitle>
                    <CardDescription>{{ t('live.demo.statesDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="s in STATES"
                            :key="s"
                            size="sm"
                            :variant="showcase === s ? 'default' : 'outline'"
                            :aria-pressed="showcase === s"
                            @click="showcase = s"
                        >
                            {{ s }}
                        </Button>
                    </div>

                    <div class="rounded-lg border p-4">
                        <DataSurface :state="showcase" skeleton="chart" :height="180" :label="t('live.demo.statesTitle')">
                            <p class="text-sm">Server-aggregated content renders here.</p>
                        </DataSurface>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        <span class="font-medium">{{ t('live.demo.announcer') }}:</span>
                        <span class="ml-1 font-mono">{{ announced }}</span>
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Zero CLS -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('live.demo.clsTitle') }}</CardTitle>
                    <CardDescription>{{ t('live.demo.clsDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-2 text-xs font-medium text-muted-foreground">{{ t('live.demo.skeleton') }}</p>
                            <SkeletonStat />
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-medium text-muted-foreground">{{ t('live.demo.loaded') }}</p>
                            <StatCard title="Signups" :value="128" description="+2 this week" :icon="Users" />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ t('live.demo.heights', { height: STAT_HEIGHT }) }}</p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
