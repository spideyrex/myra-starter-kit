<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/components/StatCard.vue';
import DateCell from '@/components/admin/DateCell.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import {
    Users, UserCheck, UserPlus, ShieldAlert, Activity,
    UserCog, Shield, HeartPulse, Settings, ArrowRight,
    CalendarDays,
} from 'lucide-vue-next';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip } from 'chart.js';

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

// Trends
const totalTrend = computed(() => {
    if (props.stats.lastMonthTotal === 0) return null;
    const diff = props.stats.totalUsers - props.stats.lastMonthTotal;
    const pct = Math.round((diff / props.stats.lastMonthTotal) * 100);
    return { value: pct, isPositive: pct >= 0 };
});

const newUsersTrend = computed(() => {
    if (props.stats.lastMonthNew === 0) return null;
    const diff = props.stats.newUsersThisMonth - props.stats.lastMonthNew;
    const pct = Math.round((diff / props.stats.lastMonthNew) * 100);
    return { value: pct, isPositive: pct >= 0 };
});

const activePercent = computed(() => {
    if (props.stats.totalUsers === 0) return 0;
    return Math.round((props.stats.activeUsers / props.stats.totalUsers) * 100);
});

// Chart
const chartColor = ref('oklch(0.646 0.222 41.116)');

onMounted(() => {
    const style = getComputedStyle(document.documentElement);
    chartColor.value = style.getPropertyValue('--chart-1').trim() || chartColor.value;
});

const chartData = computed(() => ({
    labels: props.userGrowth.map(g => {
        const [year, month] = g.month.split('-');
        return new Date(+year, +month - 1).toLocaleDateString('en-US', { month: 'short' });
    }),
    datasets: [
        {
            label: 'New Users',
            data: props.userGrowth.map(g => g.count),
            backgroundColor: chartColor.value,
            borderRadius: 6,
            borderSkipped: false,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: 'rgba(128, 128, 128, 0.1)' },
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

const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    active: { label: 'Active', color: 'bg-success', bg: 'bg-success/20' },
    suspended: { label: 'Suspended', color: 'bg-destructive', bg: 'bg-destructive/20' },
    pending: { label: 'Pending', color: 'bg-warning', bg: 'bg-warning/20' },
};

const statusEntries = computed(() =>
    Object.entries(props.usersByStatus).map(([status, count]) => ({
        status,
        count,
        pct: Math.round((count / statusTotal.value) * 100),
        config: statusConfig[status] || { label: status, color: 'bg-muted-foreground', bg: 'bg-muted' },
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

// Quick actions
const quickActions = [
    { label: 'Add User', icon: UserPlus, href: 'admin.users.create' },
    { label: 'Manage Roles', icon: Shield, href: 'admin.roles.index' },
    { label: 'System Health', icon: HeartPulse, href: 'admin.system-health.index' },
    { label: 'Settings', icon: Settings, href: 'admin.settings.index' },
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Dashboard' }]">
        <Head title="Dashboard" />

        <div class="space-y-6">
            <!-- Welcome Banner -->
            <Card class="animate-fade-in-up border-l-4 border-l-primary">
                <CardContent class="flex items-center justify-between py-5">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight">
                            Welcome back, {{ firstName }}
                        </h2>
                        <p class="text-sm text-muted-foreground mt-1 flex items-center gap-1.5">
                            <CalendarDays class="size-3.5" />
                            {{ todayFormatted }}
                        </p>
                    </div>
                    <div class="hidden sm:block text-right">
                        <p class="text-sm text-muted-foreground">
                            <span class="font-medium text-foreground">{{ stats.newUsersThisMonth }}</span>
                            new {{ stats.newUsersThisMonth === 1 ? 'user' : 'users' }} this month
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Stats Row -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Users"
                    :value="stats.totalUsers"
                    :icon="Users"
                    :trend="totalTrend ?? undefined"
                    description="from last month"
                    class="animate-stagger-1"
                />
                <StatCard
                    title="Active Users"
                    :value="stats.activeUsers"
                    :icon="UserCheck"
                    :description="`${activePercent}% of total users`"
                    class="animate-stagger-2"
                />
                <StatCard
                    title="New This Month"
                    :value="stats.newUsersThisMonth"
                    :icon="UserPlus"
                    :trend="newUsersTrend ?? undefined"
                    description="from last month"
                    class="animate-stagger-3"
                />
                <StatCard
                    title="Pending Verifications"
                    :value="stats.pendingVerifications"
                    :icon="ShieldAlert"
                    description="unverified emails"
                    class="animate-stagger-4"
                />
            </div>

            <!-- Charts Row -->
            <div class="grid gap-6 lg:grid-cols-7">
                <!-- User Growth Chart -->
                <Card class="lg:col-span-4 animate-fade-in-up">
                    <CardHeader>
                        <CardTitle>User Growth</CardTitle>
                        <CardDescription>New registrations over the last 6 months</CardDescription>
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
                        <CardTitle>Users by Status</CardTitle>
                        <CardDescription>Distribution across statuses</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-5">
                            <div v-for="entry in statusEntries" :key="entry.status" class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full" :class="entry.config.color" />
                                        <span class="font-medium capitalize">{{ entry.config.label }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <span>{{ entry.count }}</span>
                                        <span class="text-xs">({{ entry.pct }}%)</span>
                                    </div>
                                </div>
                                <Progress
                                    :model-value="entry.pct"
                                    :class="entry.config.bg"
                                    class="h-2"
                                />
                            </div>
                            <div v-if="statusEntries.length === 0" class="text-sm text-muted-foreground py-8 text-center">
                                No status data available.
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Activity + Recent Users -->
            <div class="grid gap-6 lg:grid-cols-7">
                <!-- Recent Activity -->
                <Card class="lg:col-span-4 animate-fade-in-up">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Activity class="size-4" />
                            Recent Activity
                        </CardTitle>
                        <CardDescription>Latest actions across the system</CardDescription>
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
                                        <Avatar class="size-8 shrink-0">
                                            <AvatarImage v-if="item.causer_avatar" :src="item.causer_avatar" :alt="item.causer" />
                                            <AvatarFallback class="text-xs">{{ getInitials(item.causer) }}</AvatarFallback>
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
                        <CardTitle class="flex items-center gap-2">
                            <UserCog class="size-4" />
                            Recent Users
                        </CardTitle>
                        <CardDescription>Latest registrations</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableBody>
                                <TableRow v-for="user in recentUsers" :key="user.id">
                                    <TableCell class="py-3">
                                        <div class="flex items-center gap-3">
                                            <Avatar class="size-8 shrink-0">
                                                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                                <AvatarFallback class="text-xs">{{ getInitials(user.name) }}</AvatarFallback>
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
                    <div class="flex flex-wrap gap-3">
                        <Button
                            v-for="action in quickActions"
                            :key="action.label"
                            variant="outline"
                            as-child
                        >
                            <Link :href="route(action.href)">
                                <component :is="action.icon" class="size-4 mr-2" />
                                {{ action.label }}
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
