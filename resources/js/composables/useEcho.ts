import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { toast } from 'vue-sonner';
import { useEchoChannel } from '@/composables/useEchoChannel';

/** Laravel's broadcast notification event, which `Echo.notification()` wraps. */
const NOTIFICATION = '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';

/**
 * Signature and observable behaviour are unchanged from v2.4.0; the plumbing is
 * now `useEchoChannel`, so the channel is refcounted and actually left.
 */
export function useEcho() {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (!reverbKey) return;

    const page = usePage<PageProps>();

    const channel = computed(() => {
        const userId = page.props.auth?.user?.id;
        return userId ? `App.Models.User.${userId}` : null;
    });

    useEchoChannel(channel, {
        [NOTIFICATION]: (notification: any) => {
            const message = notification.data?.message || notification.message || 'You have a new notification';
            toast.info(message);
            router.reload({ only: ['unreadNotificationsCount', 'recentNotifications'] });
        },
    });
}
