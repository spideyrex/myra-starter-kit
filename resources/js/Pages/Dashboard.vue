<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/components/StatCard.vue';
import DateCell from '@/components/admin/DateCell.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
    Users, UserCheck, UserPlus, ShieldAlert, Activity,
    UserCog, Shield, HeartPulse, Settings, ArrowRight,
    CalendarDays, Sparkles,
} from 'lucide-vue-next';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip } from 'chart.js';
import { StatWidget, ChartWidget, TableWidget } from '@/composables/useDashboardWidgets';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip);

const page = usePage<PageProps>();

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

// User info
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const todayFormatted = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

// Chart colors
const chartColors = ref<string[]>([]);

onMounted(() => {
    const style = getComputedStyle(document.documentElement);
    chartColors.value = [1, 2, 3, 4, 5].map(
        i => style.getPropertyValue(`--chart-${i}`).trim() || `oklch(0.6 0.2 ${i * 60})`,
    );
});

const chartData = computed(() => {
    const colors = chartColors.value.length > 0
        ? props.userGrowth.map((_, i) => chartColors.value[i % chartColors.value.length])
        : props.userGrowth.map(() => 'oklch(0.646 0.222 41.116)');

    return {
        labels: props.userGrowth.map(g => {
            const [year, month] = g.month.split('-');
            return new Date(+year, +month - 1).toLocaleDateString('en-US', { month: 'short' });
        }),
        datasets: [
            {
                label: 'New Users',
                data: props.userGrowth.map(g => g.count),
                backgroundColor: colors,
                borderRadius: 8,
                borderSkipped: false,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(128, 128, 128, 0.08)' },
        },
        x: {
            grid: { display: false },
        },
    },
};

// Users by status
const statusTotal = computed(() =>
    Object.values(props.usersByStatus).reduce((sum, c) => sum + c, 0) || 1,
);

const statusConfig: Record<string, { label: string; color: string; indicator: string }> = {
    active: { label: 'Active', color: 'bg-emerald-500', indicator: 'text-emerald-500' },
    suspended: { label: 'Suspended', color: 'bg-rose-500', indicator: 'text-rose-500' },
    pending: { label: 'Pending', color: 'bg-amber-500', indicator: 'text-amber-500' },
};

const statusEntries = computed(() =>
    Object.entries(props.usersByStatus).map(([status, count]) => ({
        status,
        count,
        pct: Math.round((count / statusTotal.value) * 100),
        config: statusConfig[status] || { label: status, color: 'bg-muted-foreground', indicator: 'text-muted-foreground' },
    })),
);

// Helpers
function getInitials(name: string): string {
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

// Quick actions with colors
const quickActions = [
    { label: 'Add User', icon: UserPlus, href: 'admin.users.create', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-500/10 dark:bg-emerald-400/15' },
    { label: 'Manage Roles', icon: Shield, href: 'admin.roles.index', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-500/10 dark:bg-blue-400/15' },
    { label: 'System Health', icon: HeartPulse, href: 'admin.system-health.index', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-500/10 dark:bg-rose-400/15' },
    { label: 'Settings', icon: Settings, href: 'admin.settings.index', color: 'text-violet-600 dark:text-violet-400', bg: 'bg-violet-500/10 dark:bg-violet-400/15' },
];

// Widget-based stat cards with colors
const statWidgets = [
    StatWidget.make('total_users').title('Total Users')
        .value(p => p.stats.totalUsers)
        .icon(Users)
        .color('blue')
        .trend(p => {
            if (p.stats.lastMonthTotal === 0) return null;
            const diff = p.stats.totalUsers - p.stats.lastMonthTotal;
            const pct = Math.round((diff / p.stats.lastMonthTotal) * 100);
            return { value: pct, isPositive: pct >= 0 };
        })
        .description(() => 'from last month'),
    StatWidget.make('active_users').title('Active Users')
        .value(p => p.stats.activeUsers)
        .icon(UserCheck)
        .color('green')
        .description(p => {
            const pct = p.stats.totalUsers === 0 ? 0 : Math.round((p.stats.activeUsers / p.stats.totalUsers) * 100);
            return `${pct}% of total users`;
        }),
    StatWidget.make('new_users').title('New This Month')
        .value(p => p.stats.newUsersThisMonth)
        .icon(UserPlus)
        .color('violet')
        .trend(p => {
            if (p.stats.lastMonthNew === 0) return null;
            const diff = p.stats.newUsersThisMonth - p.stats.lastMonthNew;
            const pct = Math.round((diff / p.stats.lastMonthNew) * 100);
            return { value: pct, isPositive: pct >= 0 };
        })
        .description(() => 'from last month'),
    StatWidget.make('pending_verifications').title('Pending Verifications')
        .value(p => p.stats.pendingVerifications)
        .icon(ShieldAlert)
        .color('orange')
        .description(() => 'unverified emails'),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Dashboard' }]">
        <Head title="Dashboard" />

        <div class="space-y-6">
            <!-- Welcome Banner -->
            <div class="animate-fade-in-up relative overflow-hidden rounded-xl border bg-gradient-to-br from-primary/5 via-chart-1/5 to-chart-2/5 dark:from-primary/10 dark:via-chart-1/8 dark:to-chart-2/8">
                <!-- Decorative circles -->
                <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-chart-1/5 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-8 -left-8 size-48 rounded-full bg-chart-2/5 blur-3xl" />

                <div class="relative flex items-center justify-between px-6 py-6">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <Sparkles class="size-5 text-chart-1" />
                            <h2 class="text-xl font-semibold tracking-tight">
                                Welcome back, {{ firstName }}
                            </h2>
                        </div>
                        <p class="text-sm text-muted-foreground flex items-center gap-1.5">
                            <CalendarDays class="size-3.5" />
                            {{ todayFormatted }}
                        </p>
                    </div>
                    <div class="hidden sm:flex items-center gap-3 text-right">
                        <div class="flex items-center justify-center size-10 rounded-lg bg-violet-500/10 dark:bg-violet-400/15">
                            <UserPlus class="size-5 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ stats.newUsersThisMonth }}</p>
                            <p class="text-xs text-muted-foreground">
                                new {{ stats.newUsersThisMonth === 1 ? 'user' : 'users' }} this month
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row (widget-based with colors) -->
            <DashboardGrid :widgets="statWidgets" :page-props="props" />

            <!-- Charts Row -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <!-- User Growth Chart -->
                <Card class="lg:col-span-4 animate-fade-in-up">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center size-8 rounded-lg bg-chart-1/10">
                                <Activity class="size-4 text-chart-1" />
                            </div>
                            <div>
                                <CardTitle>User Growth</CardTitle>
                                <CardDescription>New registrations over the last 6 months</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="userGrowth.length > 0" style="height: 260px">
                            <Bar :data="chartData" :options="chartOptions" />
                        </div>
                        <p v-else class="text-sm text-muted-foreground py-8 text-center">
                            No growth data available yet.
                        </p>
                    </CardContent>
                </Card>

                <!-- Users by Status -->
                <Card class="lg:col-span-3 animate-fade-in-up">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center size-8 rounded-lg bg-chart-2/10">
                                <Users class="size-4 text-chart-2" />
                            </div>
                            <div>
                                <CardTitle>Users by Status</CardTitle>
                                <CardDescription>Distribution across statuses</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-5">
                            <div v-for="entry in statusEntries" :key="entry.status" class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full" :class="entry.config.color" />
                                        <span class="font-medium capitalize">{{ entry.config.label }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold" :class="entry.config.indicator">{{ entry.count }}</span>
                                        <span class="text-xs text-muted-foreground">({{ entry.pct }}%)</span>
                                    </div>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="entry.config.color"
                                        :style="{ width: `${entry.pct}%` }"
                                    />
                                </div>
                            </div>
                            <div v-if="statusEntries.length === 0" class="text-sm text-muted-foreground py-8 text-center">
                                No status data available.
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Activity + Recent Users -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-7">
                <!-- Recent Activity -->
                <Card class="lg:col-span-4 animate-fade-in-up">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center size-8 rounded-lg bg-amber-500/10 dark:bg-amber-400/15">
                                <Activity class="size-4 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <CardTitle>Recent Activity</CardTitle>
                                <CardDescription>Latest actions across the system</CardDescription>
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
                                    <!-- Timeline line -->
                                    <div class="flex flex-col items-center">
                                        <Avatar class="size-8 shrink-0 ring-2 ring-background">
                                            <AvatarImage v-if="item.causer_avatar" :src="item.causer_avatar" :alt="item.causer" />
                                            <AvatarFallback class="text-xs bg-primary/10 text-primary">{{ getInitials(item.causer) }}</AvatarFallback>
                                        </Avatar>
                                        <div
                                            v-if="index < recentActivity.length - 1"
                                            class="mt-1 w-px flex-1 bg-border"
                                        />
                                    </div>
                                    <!-- Content -->
                                    <div class="flex-1 min-w-0 pt-1">
                                        <p class="text-sm leading-snug">
                                            <span class="font-medium">{{ item.causer }}</span>
                                            {{ ' ' }}
                                            <span class="text-muted-foreground">{{ item.description }}</span>
                                        </p>
                                        <DateCell :value="item.created_at" format="relative" class="mt-0.5" />
                                    </div>
                                </div>
                                <div v-if="recentActivity.length === 0" class="text-sm text-muted-foreground py-8 text-center">
                                    No recent activity.
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>

                <!-- Recent Users -->
                <Card class="lg:col-span-3 animate-fade-in-up">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center size-8 rounded-lg bg-blue-500/10 dark:bg-blue-400/15">
                                <UserCog class="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <CardTitle>Recent Users</CardTitle>
                                <CardDescription>Latest registrations</CardDescription>
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
                                                <AvatarFallback class="text-xs bg-primary/10 text-primary">{{ getInitials(user.name) }}</AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium truncate">{{ user.name }}</p>
                                                <p class="text-xs text-muted-foreground truncate">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="py-3 text-right">
                                        <Badge
                                            v-if="user.roles.length > 0"
                                            variant="secondary"
                                            class="capitalize text-xs"
                                        >
                                            {{ user.roles[0] }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="py-3 text-right whitespace-nowrap">
                                        <DateCell :value="user.created_at" format="relative" />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div v-if="recentUsers.length === 0" class="text-sm text-muted-foreground py-8 text-center">
                            No users yet.
                        </div>
                        <div v-else class="mt-3 text-center">
                            <Link :href="route('admin.users.index')" class="text-sm text-primary hover:underline inline-flex items-center gap-1">
                                View all users
                                <ArrowRight class="size-3" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card class="animate-fade-in-up">
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Link
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="route(action.href)"
                            class="group flex flex-col items-center gap-2.5 rounded-xl border bg-card p-4 text-center transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 hover:border-primary/20"
                        >
                            <div
                                class="flex items-center justify-center size-10 rounded-lg transition-colors"
                                :class="action.bg"
                            >
                                <component :is="action.icon" class="size-5" :class="action.color" />
                            </div>
                            <span class="text-sm font-medium">{{ action.label }}</span>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
