<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateCell from '@/components/admin/DateCell.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import {
    Users, UserCheck, UserPlus, ShieldAlert, Activity,
    UserCog, Shield, HeartPulse, Settings, ArrowRight,
    CalendarDays, Sparkles,
} from 'lucide-vue-next';
import { StatWidget, ChartWidget, type ColSpan } from '@/composables/useDashboardWidgets';
import type { ReportResultPayload, ReportState } from '@/components/admin/charts/types';
// >>> MYRA v2.5 [A] START
import { useDashboardLayout } from '@/composables/useDashboardLayout';
// <<< MYRA v2.5 [A] END

const page = usePage<PageProps>();
const { t, locale } = useI18n();

const props = defineProps<{
    stats: {
        totalUsers: number;
        activeUsers: number;
        newUsersThisMonth: number;
        pendingVerifications: number;
        lastMonthTotal: number;
        lastMonthNew: number;
    };
    usersByRole: Array<{ name: string; count: number }>;
    usersByStatus: Record<string, number>;
    recentActivity: Array<{
        id: number;
        description: string;
        causer: string;
        causer_avatar: string | null;
        subject: string | null;
        created_at: string;
    }>;
    recentUsers: Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        status: string;
        roles: string[];
        created_at: string;
    }>;
    userGrowth: Array<{ month: string; count: number }>;
}>();

const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const todayFormatted = computed(() => new Date().toLocaleDateString(locale.value, {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}));

// --- Widget declarations -----------------------------------------------------

const statWidgets = computed(() => [
    StatWidget.make('total_users').title(t('charts.dashboard.users'))
        .value(p => p.stats.totalUsers)
        .icon(Users)
        .color('blue')
        .trend(p => {
            if (p.stats.lastMonthTotal === 0) return null;
            const diff = p.stats.totalUsers - p.stats.lastMonthTotal;
            const pct = Math.round((diff / p.stats.lastMonthTotal) * 100);
            return { value: pct, isPositive: pct >= 0 };
        })
        .description(() => t('charts.compare.previous')),
    StatWidget.make('active_users').title(t('status.active'))
        .value(p => p.stats.activeUsers)
        .icon(UserCheck)
        .color('green')
        .description(p => {
            const pct = p.stats.totalUsers === 0 ? 0 : Math.round((p.stats.activeUsers / p.stats.totalUsers) * 100);
            return t('charts.goalProgress', { percent: pct });
        }),
    StatWidget.make('new_users').title(t('charts.dashboard.newUsers'))
        .value(p => p.stats.newUsersThisMonth)
        .icon(UserPlus)
        .color('violet')
        .trend(p => {
            if (p.stats.lastMonthNew === 0) return null;
            const diff = p.stats.newUsersThisMonth - p.stats.lastMonthNew;
            const pct = Math.round((diff / p.stats.lastMonthNew) * 100);
            return { value: pct, isPositive: pct >= 0 };
        })
        .description(() => t('charts.compare.previous')),
    StatWidget.make('pending_verifications').title(t('status.pending'))
        .value(p => p.stats.pendingVerifications)
        .icon(ShieldAlert)
        .color('orange'),
]);

/**
 * Report-bound charts. The binding names bundle B's `users` report; until its
 * controller ships the payload, `chartResults` below adapts the props this page
 * already receives into the same frozen wire shape. Post-merge the adapter is
 * deleted and `results` comes straight from the server.
 */
const chartWidgets = computed(() => [
    ChartWidget.make('user_growth').title(t('charts.dashboard.growth'))
        .report('users').dimension('created_at').measures(['signups']).bucket('month')
        .type('area').colSpan({ sm: 2, lg: 2 }).height(280)
        .emptyState({ headingKey: 'charts.empty.heading', descriptionKey: 'charts.dashboard.growthDescription' }),
    ChartWidget.make('users_by_status').title(t('charts.dashboard.byStatus'))
        .report('users').dimension('status').measures(['signups'])
        .type('doughnut').colSpan({ sm: 2, lg: 2 }).height(280).legend()
        .emptyState({ headingKey: 'charts.empty.heading', descriptionKey: 'charts.dashboard.byStatusDescription' }),
]);

// >>> MYRA v2.5 [A] START
// Per-user layout. With the flag off, no saved row and an empty catalogue this
// is inert: `layout` is null, `instances` is empty and the bar never renders.
const DashboardEditorBar = defineAsyncComponent(() => import('@/components/admin/DashboardEditorBar.vue'));

const canCustomise = computed(() => (page.props as any).canCustomiseDashboard === true);

const dashboard = useDashboardLayout({
    dashboard: 'admin.dashboard',
    declared: () => [...statWidgets.value, ...chartWidgets.value],
    saved: () => (page.props as any).dashboardLayout ?? null,
    catalogue: () => (page.props as any).dashboardCatalogue ?? [],
    editable: canCustomise,
    t: (key, params) => t(key, (params ?? {}) as any),
});

// A saved layout is honoured even with editing turned off — the flag hides the
// bar, it must never silently discard a layout the user already saved.
const editorLayout = computed(() => dashboard.layout.value);
const editing = computed(() => canCustomise.value && dashboard.editing.value);

function onReorder(key: string, toIndex: number): void {
    dashboard.moveTo(key, toIndex);
}
// <<< MYRA v2.5 [A] END

// --- Local adapter: page props -> frozen report wire shape --------------------

function reportState(dimension: string, bucket?: 'month'): ReportState {
    return {
        period: { preset: 'last_30_days' },
        compare: 'none',
        dimension,
        bucket,
        measures: ['signups'],
    };
}

function monthLabel(key: string): string {
    const [year, month] = key.split('-');
    return new Date(+year, +month - 1).toLocaleDateString(locale.value, { month: 'short' });
}

function statusLabel(status: string): string {
    if (status === 'suspended') return t('charts.dashboard.suspended');
    if (status === 'active' || status === 'pending' || status === 'inactive') return t(`status.${status}`);
    return status;
}

const signups = {
    key: 'signups',
    labelKey: 'charts.dashboard.newUsers',
    format: 'number' as const,
    decimals: 0,
    goal: null,
    invertTrend: false,
    additive: true,
};

const growthResult = computed<ReportResultPayload>(() => ({
    report: 'users',
    state: reportState('created_at', 'month'),
    period: { preset: 'last_30_days', from: '', to: '', tz: 'UTC', bucket: 'month' },
    comparison: null,
    dimension: {
        key: 'created_at', labelKey: 'charts.dashboard.month', type: 'date',
        drillable: false, allowedBuckets: ['month'],
    },
    measures: [signups],
    rows: props.userGrowth.map(g => ({
        key: g.month,
        label: monthLabel(g.month),
        values: { signups: g.count },
        previous: null,
        deltas: { signups: null },
        isOther: false,
        drill: null,
    })),
    totals: { signups: props.userGrowth.reduce((sum, g) => sum + g.count, 0) },
    previousTotals: null,
    deltas: { signups: null },
    truncated: false,
    groupCount: props.userGrowth.length,
}));

const statusResult = computed<ReportResultPayload>(() => {
    const entries = Object.entries(props.usersByStatus);

    return {
        report: 'users',
        state: reportState('status'),
        period: { preset: 'last_30_days', from: '', to: '', tz: 'UTC', bucket: null },
        comparison: null,
        dimension: {
            key: 'status', labelKey: 'charts.dashboard.status', type: 'field',
            drillable: false, allowedBuckets: [],
        },
        measures: [signups],
        rows: entries.map(([status, count]) => ({
            key: status,
            label: statusLabel(status),
            values: { signups: count },
            previous: null,
            deltas: { signups: null },
            isOther: false,
            drill: null,
        })),
        totals: { signups: entries.reduce((sum, [, count]) => sum + count, 0) },
        previousTotals: null,
        deltas: { signups: null },
        truncated: false,
        groupCount: entries.length,
    };
});

const chartResults = computed(() => ({
    user_growth: growthResult.value,
    users_by_status: statusResult.value,
}));

function getInitials(name: string): string {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

const quickActions = computed(() => [
    { label: t('charts.dashboard.addUser'), icon: UserPlus, href: 'admin.users.create', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-500/10 dark:bg-emerald-400/15' },
    { label: t('charts.dashboard.manageRoles'), icon: Shield, href: 'admin.roles.index', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-500/10 dark:bg-blue-400/15' },
    { label: t('charts.dashboard.systemHealth'), icon: HeartPulse, href: 'admin.system-health.index', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-500/10 dark:bg-rose-400/15' },
    { label: t('charts.dashboard.settings'), icon: Settings, href: 'admin.settings.index', color: 'text-violet-600 dark:text-violet-400', bg: 'bg-violet-500/10 dark:bg-violet-400/15' },
]);
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('nav.dashboard') }]">
        <Head :title="t('nav.dashboard')" />

        <div class="space-y-6">
            <!-- Welcome Banner -->
            <div class="animate-fade-in-up relative overflow-hidden rounded-xl border bg-gradient-to-br from-primary/5 via-chart-1/5 to-chart-2/5 dark:from-primary/10 dark:via-chart-1/8 dark:to-chart-2/8">
                <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-chart-1/5 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-8 -left-8 size-48 rounded-full bg-chart-2/5 blur-3xl" />

                <div class="relative flex items-center justify-between px-6 py-6">
                    <div>
                        <div class="mb-1 flex items-center gap-2">
                            <Sparkles class="size-5 text-chart-1" aria-hidden="true" />
                            <h2 class="text-xl font-semibold tracking-tight">
                                {{ t('charts.dashboard.greeting', { name: firstName }) }}
                            </h2>
                        </div>
                        <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{ todayFormatted }}
                        </p>
                    </div>
                    <div class="hidden items-center gap-3 text-right sm:flex">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-violet-500/10 dark:bg-violet-400/15">
                            <UserPlus class="size-5 text-violet-600 dark:text-violet-400" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats.newUsersThisMonth }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ t('charts.dashboard.newUsersThisMonth', stats.newUsersThisMonth) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- >>> MYRA v2.5 [A] START -->
            <DashboardEditorBar
                v-if="canCustomise"
                :editing="dashboard.editing.value"
                :dirty="dashboard.dirty.value"
                :saving="dashboard.saving.value"
                :customised="dashboard.customised.value"
                :announcement="dashboard.announcement.value"
                :catalogue="(page.props as any).dashboardCatalogue ?? []"
                :used="dashboard.usedCatalogueKeys.value"
                :hidden="dashboard.hidden.value"
                @update:editing="dashboard.editing.value = $event"
                @add="(key: string, binding: any) => dashboard.addInstance(key, binding)"
                @restore="dashboard.toggleHidden($event)"
                @undo="dashboard.undo()"
                @reset="dashboard.reset()"
                @save="dashboard.save()"
                @cancel="dashboard.cancel()"
            />
            <!-- <<< MYRA v2.5 [A] END -->

            <!-- Stats Row -->
            <DashboardGrid
                :widgets="statWidgets"
                :page-props="props"
                :layout="editorLayout"
                :editing="editing"
                @reorder="onReorder"
                @resize="(key: string, colSpan: ColSpan) => dashboard.resize(key, colSpan)"
                @rename="(key: string, title: string | null) => dashboard.rename(key, title)"
                @hide="dashboard.toggleHidden($event)"
                @remove="dashboard.removeInstance($event)"
            />

            <!-- Charts Row: report-bound widgets, rendered by the lazy chart registry -->
            <DashboardGrid
                :widgets="chartWidgets"
                :page-props="props"
                :results="chartResults"
                :layout="editorLayout"
                :editing="editing"
                @reorder="onReorder"
                @resize="(key: string, colSpan: ColSpan) => dashboard.resize(key, colSpan)"
                @rename="(key: string, title: string | null) => dashboard.rename(key, title)"
                @hide="dashboard.toggleHidden($event)"
                @remove="dashboard.removeInstance($event)"
            />

            <!-- >>> MYRA v2.5 [A] START -->
            <!-- Catalogue-added tiles. Empty unless the user added one. -->
            <DashboardGrid
                v-if="dashboard.instances.value.length > 0"
                :widgets="dashboard.instances.value"
                :page-props="props"
                :layout="editorLayout"
                :editing="editing"
                @reorder="onReorder"
                @resize="(key: string, colSpan: ColSpan) => dashboard.resize(key, colSpan)"
                @rename="(key: string, title: string | null) => dashboard.rename(key, title)"
                @hide="dashboard.toggleHidden($event)"
                @remove="dashboard.removeInstance($event)"
            />
            <!-- <<< MYRA v2.5 [A] END -->

            <!-- Activity + Recent Users -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <Card class="animate-fade-in-up lg:col-span-4">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 dark:bg-amber-400/15">
                                <Activity class="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            </div>
                            <div>
                                <CardTitle>{{ t('charts.dashboard.activity') }}</CardTitle>
                                <CardDescription>{{ t('charts.dashboard.activityDescription') }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[380px] pr-3">
                            <div class="space-y-0">
                                <div
                                    v-for="(item, index) in recentActivity"
                                    :key="item.id"
                                    class="relative flex gap-3 pb-5 last:pb-0"
                                >
                                    <div class="flex flex-col items-center">
                                        <Avatar class="size-8 shrink-0 ring-2 ring-background">
                                            <AvatarImage v-if="item.causer_avatar" :src="item.causer_avatar" :alt="item.causer" />
                                            <AvatarFallback class="bg-primary/10 text-xs text-primary">{{ getInitials(item.causer) }}</AvatarFallback>
                                        </Avatar>
                                        <div v-if="index < recentActivity.length - 1" class="mt-1 w-px flex-1 bg-border" />
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1">
                                        <p class="text-sm leading-snug">
                                            <span class="font-medium">{{ item.causer }}</span>
                                            {{ ' ' }}
                                            <span class="text-muted-foreground">{{ item.description }}</span>
                                        </p>
                                        <DateCell :value="item.created_at" format="relative" class="mt-0.5" />
                                    </div>
                                </div>
                                <div v-if="recentActivity.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                    {{ t('charts.dashboard.activityEmpty') }}
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>

                <Card class="animate-fade-in-up lg:col-span-3">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-blue-500/10 dark:bg-blue-400/15">
                                <UserCog class="size-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
                            </div>
                            <div>
                                <CardTitle>{{ t('charts.dashboard.recentUsers') }}</CardTitle>
                                <CardDescription>{{ t('charts.dashboard.recentUsersDescription') }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableBody>
                                <TableRow v-for="user in recentUsers" :key="user.id" class="group">
                                    <TableCell class="py-3">
                                        <div class="flex items-center gap-3">
                                            <Avatar class="size-8 shrink-0 ring-2 ring-background">
                                                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                                <AvatarFallback class="bg-primary/10 text-xs text-primary">{{ getInitials(user.name) }}</AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium">{{ user.name }}</p>
                                                <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-3 text-right">
                                        <Badge v-if="user.roles.length > 0" variant="secondary" class="text-xs capitalize">
                                            {{ user.roles[0] }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="whitespace-nowrap py-3 text-right">
                                        <DateCell :value="user.created_at" format="relative" />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div v-if="recentUsers.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            {{ t('charts.dashboard.usersEmpty') }}
                        </div>
                        <div v-else class="mt-3 text-center">
                            <Link :href="route('admin.users.index')" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                                {{ t('charts.dashboard.viewAllUsers') }}
                                <ArrowRight class="size-3" aria-hidden="true" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card class="animate-fade-in-up">
                <CardHeader>
                    <CardTitle>{{ t('charts.dashboard.quickActions') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Link
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="route(action.href)"
                            class="group flex flex-col items-center gap-2.5 rounded-xl border bg-card p-4 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/20 hover:shadow-md"
                        >
                            <div class="flex size-10 items-center justify-center rounded-lg transition-colors" :class="action.bg">
                                <component :is="action.icon" class="size-5" :class="action.color" aria-hidden="true" />
                            </div>
                            <span class="text-sm font-medium">{{ action.label }}</span>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
