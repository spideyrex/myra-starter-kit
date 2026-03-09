<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import type { PageProps, Notification } from '@/types';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import NotificationDetailDialog from '@/components/NotificationDetailDialog.vue';
import { Bell, BellDot, Inbox, Monitor, UserCheck, ShieldAlert } from 'lucide-vue-next';

const page = usePage<PageProps>();
const unreadCount = computed(() => page.props.unreadNotificationsCount ?? 0);
const notifications = computed<Notification[]>(() => (page.props as any).recentNotifications ?? []);

const popoverOpen = ref(false);
const dialogOpen = ref(false);
const selectedNotification = ref<Notification | null>(null);

const typeIcon: Record<string, any> = {
    system: Monitor,
    user_action: UserCheck,
    security_alert: ShieldAlert,
};

const typeColor: Record<string, string> = {
    system: 'text-primary',
    user_action: 'text-secondary-foreground',
    security_alert: 'text-destructive',
};

function getNotificationType(n: Notification): string {
    return (n.data as any).type || 'system';
}

function openNotification(n: Notification) {
    selectedNotification.value = n;
    popoverOpen.value = false;
    dialogOpen.value = true;
}
</script>

<template>
    <Popover v-model:open="popoverOpen">
        <PopoverTrigger as-child>
            <Button variant="ghost" size="sm" class="relative">
                <BellDot v-if="unreadCount > 0" class="size-5" />
                <Bell v-else class="size-5" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full p-0 text-xs"
                >
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-75" />
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </Badge>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[calc(100vw-2rem)] p-0 sm:w-80" align="end">
            <div class="flex items-center justify-between border-b p-3">
                <h4 class="text-sm font-semibold">Notifications</h4>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="h-auto px-2 py-1 text-xs"
                    @click="router.post(route('notifications.mark-all-read'))"
                >
                    Mark all read
                </Button>
            </div>
            <ScrollArea class="max-h-72">
                <template v-if="notifications.length > 0">
                    <button
                        v-for="notification in notifications"
                        :key="notification.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b px-3 py-2.5 text-left transition-colors hover:bg-muted/80 last:border-0"
                        :class="!notification.read_at ? 'bg-muted/50' : ''"
                        @click="openNotification(notification)"
                    >
                        <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted">
                            <component
                                :is="typeIcon[getNotificationType(notification)] || Monitor"
                                class="size-3.5"
                                :class="typeColor[getNotificationType(notification)] || 'text-primary'"
                            />
                        </div>
                        <div class="flex-1 min-w-0 space-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span v-if="!notification.read_at" class="flex size-1.5 shrink-0 rounded-full bg-primary" />
                                <p class="text-sm font-medium leading-tight truncate">
                                    {{ (notification.data as any).title || 'Notification' }}
                                </p>
                            </div>
                            <p class="text-xs text-muted-foreground line-clamp-2">
                                {{ (notification.data as any).message }}
                            </p>
                            <p class="text-[11px] text-muted-foreground/70">{{ notification.created_at }}</p>
                        </div>
                    </button>
                </template>
                <div v-else class="flex flex-col items-center gap-2 py-8 text-center">
                    <Inbox class="size-8 text-muted-foreground/50" />
                    <p class="text-sm text-muted-foreground">No notifications</p>
                </div>
            </ScrollArea>
            <div class="border-t p-2 text-center">
                <Link :href="route('notifications.index')" class="text-sm text-muted-foreground hover:text-foreground hover:underline">
                    View all notifications
                </Link>
            </div>
        </PopoverContent>
    </Popover>

    <NotificationDetailDialog
        v-model:open="dialogOpen"
        :notification="selectedNotification"
    />
</template>
