<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import NotificationDetailDialog from '@/components/NotificationDetailDialog.vue';
import { DateCell } from '@/components/admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import EmptyState from '@/components/EmptyState.vue';
import type { Notification } from '@/types';
import { Bell, CheckCheck, Monitor, UserCheck, ShieldAlert, ExternalLink } from 'lucide-vue-next';

const props = defineProps<{
    notifications: any;
}>();

// Handle both flat paginator format and {data, meta, links} format
const items = computed(() => props.notifications.data ?? []);
const meta = computed(() => props.notifications.meta ?? props.notifications);
const paginationLinks = computed(() => meta.value.links ?? []);

const dialogOpen = ref(false);
const selectedNotification = ref<Notification | null>(null);

function openNotification(n: Notification) {
    selectedNotification.value = n;
    dialogOpen.value = true;
}

function markRead(id: string) {
    router.patch(route('notifications.mark-read', id));
}

function markAllRead() {
    router.post(route('notifications.mark-all-read'));
}

const typeConfig: Record<string, { label: string; variant: string; icon: any }> = {
    system: { label: 'System', variant: 'default', icon: Monitor },
    user_action: { label: 'User Action', variant: 'secondary', icon: UserCheck },
    security_alert: { label: 'Security Alert', variant: 'destructive', icon: ShieldAlert },
};

function getTypeConfig(n: Notification) {
    const type = (n.data as any).type || 'system';
    return typeConfig[type] || typeConfig.system;
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Notifications' }]">
        <Head title="Notifications" />
        <PageHeader title="Notifications" description="View all your notifications.">
            <template #actions>
                <Button variant="outline" @click="markAllRead">
                    <CheckCheck class="mr-2 size-4" />Mark All Read
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-2">
            <EmptyState v-if="items.length === 0" title="No notifications" description="You're all caught up!" :icon="Bell" />
            <Card
                v-for="n in items"
                :key="n.id"
                class="cursor-pointer transition-colors hover:bg-muted/50"
                :class="{ 'border-primary/20 bg-primary/5': !n.read_at }"
                @click="openNotification(n)"
            >
                <CardContent class="flex items-start gap-4 p-4">
                    <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full" :class="{
                        'bg-primary/10 text-primary': getTypeConfig(n).variant === 'default',
                        'bg-secondary text-secondary-foreground': getTypeConfig(n).variant === 'secondary',
                        'bg-destructive/10 text-destructive': getTypeConfig(n).variant === 'destructive',
                    }">
                        <component :is="getTypeConfig(n).icon" class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-sm font-medium">{{ (n.data as any).title || n.type }}</p>
                            <Badge :variant="(getTypeConfig(n).variant as any)" class="text-[10px] px-1.5 py-0">
                                {{ getTypeConfig(n).label }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground line-clamp-2">{{ (n.data as any).message }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <DateCell :value="n.created_at" format="relative" />
                            <span
                                v-if="(n.data as any).action_url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                            >
                                <ExternalLink class="size-3" />
                                Has link
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Badge v-if="!n.read_at" variant="secondary">New</Badge>
                        <Button
                            v-if="!n.read_at"
                            variant="ghost"
                            size="sm"
                            @click.stop="markRead(n.id)"
                        >
                            Mark Read
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="meta.last_page > 1" class="flex items-center justify-between pt-4">
                <p class="text-sm text-muted-foreground">
                    Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }}
                </p>
                <div class="flex gap-1">
                    <Button
                        v-for="link in paginationLinks"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        :disabled="!link.url || link.active"
                        @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                    >{{ link.label.replace(/&laquo;/g, '\u00AB').replace(/&raquo;/g, '\u00BB') }}</Button>
                </div>
            </div>
        </div>

        <NotificationDetailDialog
            v-model:open="dialogOpen"
            :notification="selectedNotification"
        />
    </AuthenticatedLayout>
</template>
