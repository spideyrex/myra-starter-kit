import { onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { toast } from 'vue-sonner';

let echoChannel: any = null;

export function useEcho() {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (!reverbKey) return;

    const page = usePage<PageProps>();

    onMounted(async () => {
        const userId = page.props.auth?.user?.id;
        if (!userId) return;

        try {
            const { default: echo } = await import('@/echo');
            echoChannel = echo.private(`App.Models.User.${userId}`);
            echoChannel.notification((notification: any) => {
                const message = notification.data?.message || notification.message || 'You have a new notification';
                toast.info(message);
                router.reload({ only: ['unreadNotificationsCount', 'recentNotifications'] });
            });
        } catch {
            // Reverb not available — silently ignore
        }
    });

    onUnmounted(() => {
        if (echoChannel) {
            echoChannel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
            echoChannel = null;
        }
    });
}
