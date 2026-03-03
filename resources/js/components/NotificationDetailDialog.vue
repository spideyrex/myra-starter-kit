<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Monitor, UserCheck, ShieldAlert, ExternalLink, Clock, User } from 'lucide-vue-next';

interface NotificationData {
    id: string;
    title?: string;
    message?: string;
    type?: string;
    action_url?: string | null;
    read_at?: string | null;
    created_at?: string;
    // data nested format (from user notifications)
    data?: Record<string, any>;
    // flat format (from admin or bell)
    user_name?: string;
    user_email?: string;
    performed_by?: string;
    ip_address?: string;
}

const props = defineProps<{
    notification: NotificationData | null;
    showRecipient?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const title = computed(() =>
    props.notification?.title
    || props.notification?.data?.title
    || 'Notification'
);

const message = computed(() =>
    props.notification?.message
    || props.notification?.data?.message
    || ''
);

const type = computed(() =>
    props.notification?.type
    || props.notification?.data?.type
    || 'system'
);

const actionUrl = computed(() =>
    props.notification?.action_url
    || props.notification?.data?.action_url
    || ''
);

const performedBy = computed(() =>
    props.notification?.performed_by
    || props.notification?.data?.performed_by
    || ''
);

const ipAddress = computed(() =>
    props.notification?.ip_address
    || props.notification?.data?.ip_address
    || ''
);

const typeConfig: Record<string, { label: string; variant: string; icon: any; color: string }> = {
    system: { label: 'System', variant: 'default', icon: Monitor, color: 'bg-primary/10 text-primary' },
    user_action: { label: 'User Action', variant: 'secondary', icon: UserCheck, color: 'bg-secondary text-secondary-foreground' },
    security_alert: { label: 'Security Alert', variant: 'destructive', icon: ShieldAlert, color: 'bg-destructive/10 text-destructive' },
};

const currentTypeConfig = computed(() => typeConfig[type.value] || typeConfig.system);

const formattedDate = computed(() => {
    if (!props.notification?.created_at) return '';
    const date = new Date(props.notification.created_at);
    return date.toLocaleDateString(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const isUnread = computed(() => !props.notification?.read_at);

function markAsRead() {
    if (!props.notification || !isUnread.value) return;
    router.patch(route('notifications.mark-read', props.notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="max-w-lg">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full" :class="currentTypeConfig.color">
                        <component :is="currentTypeConfig.icon" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-lg leading-tight pr-6">{{ title }}</DialogTitle>
                        <DialogDescription class="mt-1 flex items-center gap-2">
                            <Badge :variant="(currentTypeConfig.variant as any)" class="text-[10px]">
                                {{ currentTypeConfig.label }}
                            </Badge>
                            <Badge v-if="isUnread" variant="secondary" class="text-[10px]">Unread</Badge>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <Separator />

            <!-- Message body with proper formatting -->
            <div class="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{{ message }}</div>

            <!-- Metadata -->
            <div v-if="formattedDate || showRecipient || performedBy || ipAddress" class="space-y-2 rounded-md border bg-muted/30 p-3">
                <div v-if="formattedDate" class="flex items-center gap-2 text-xs text-muted-foreground">
                    <Clock class="size-3.5 shrink-0" />
                    <span>{{ formattedDate }}</span>
                </div>
                <div v-if="showRecipient && notification?.user_name" class="flex items-center gap-2 text-xs text-muted-foreground">
                    <User class="size-3.5 shrink-0" />
                    <span>{{ notification.user_name }} ({{ notification.user_email }})</span>
                </div>
                <div v-if="performedBy" class="flex items-center gap-2 text-xs text-muted-foreground">
                    <UserCheck class="size-3.5 shrink-0" />
                    <span>Performed by {{ performedBy }}</span>
                </div>
                <div v-if="ipAddress" class="flex items-center gap-2 text-xs text-muted-foreground">
                    <ShieldAlert class="size-3.5 shrink-0" />
                    <span>IP: {{ ipAddress }}</span>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button
                    v-if="actionUrl"
                    variant="outline"
                    as="a"
                    :href="actionUrl"
                    target="_blank"
                    class="gap-2"
                >
                    <ExternalLink class="size-4" />
                    View Details
                </Button>
                <Button
                    v-if="isUnread && !showRecipient"
                    @click="markAsRead"
                >
                    Mark as Read
                </Button>
                <Button v-else variant="outline" @click="open = false">Close</Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
