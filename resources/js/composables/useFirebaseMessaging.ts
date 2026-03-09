import { ref, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { toast } from 'vue-sonner';
import axios from 'axios';

const permissionStatus = ref<NotificationPermission>('default');
const currentToken = ref<string | null>(null);
const isSupported = ref(false);

let initialized = false;

export function useFirebaseMessaging() {
    const page = usePage<PageProps>();

    onMounted(async () => {
        if (initialized) return;

        const config = page.props.firebaseConfig;
        if (!config || !config.apiKey) return;

        // Check browser support
        if (!('serviceWorker' in navigator) || !('Notification' in window)) return;
        isSupported.value = true;
        permissionStatus.value = Notification.permission;

        try {
            // Register service worker and send config
            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            registration.active?.postMessage({
                type: 'FIREBASE_CONFIG',
                config: {
                    apiKey: config.apiKey,
                    authDomain: config.authDomain,
                    projectId: config.projectId,
                    storageBucket: config.storageBucket,
                    messagingSenderId: config.messagingSenderId,
                    appId: config.appId,
                },
            });

            // Dynamically import Firebase modules
            const { initializeApp } = await import('firebase/app');
            const { getMessaging, onMessage, getToken } = await import('firebase/messaging');

            const app = initializeApp({
                apiKey: config.apiKey,
                authDomain: config.authDomain,
                projectId: config.projectId,
                storageBucket: config.storageBucket,
                messagingSenderId: config.messagingSenderId,
                appId: config.appId,
            });

            const messaging = getMessaging(app);

            // Listen for foreground messages
            onMessage(messaging, (payload) => {
                const title = payload.notification?.title || 'New Notification';
                const body = payload.notification?.body || '';
                toast.info(`${title}: ${body}`);
                router.reload({ only: ['unreadNotificationsCount', 'recentNotifications'] });
            });

            // If permission already granted, get token
            if (Notification.permission === 'granted') {
                try {
                    const token = await getToken(messaging, {
                        vapidKey: config.vapidKey,
                        serviceWorkerRegistration: registration,
                    });
                    if (token) {
                        currentToken.value = token;
                        await registerToken(token);
                    }
                } catch {
                    // Token retrieval may fail silently
                }
            }

            initialized = true;
        } catch {
            // Firebase not available — silently ignore
        }
    });

    async function requestPermission(): Promise<string | null> {
        const config = usePage<PageProps>().props.firebaseConfig;
        if (!config || !config.apiKey) return null;

        try {
            const permission = await Notification.requestPermission();
            permissionStatus.value = permission;

            if (permission !== 'granted') return null;

            const { initializeApp, getApps } = await import('firebase/app');
            const { getMessaging, getToken } = await import('firebase/messaging');

            const app = getApps().length
                ? getApps()[0]
                : initializeApp({
                    apiKey: config.apiKey,
                    authDomain: config.authDomain,
                    projectId: config.projectId,
                    storageBucket: config.storageBucket,
                    messagingSenderId: config.messagingSenderId,
                    appId: config.appId,
                });

            const messaging = getMessaging(app);
            const registration = await navigator.serviceWorker.getRegistration('/firebase-messaging-sw.js');

            const token = await getToken(messaging, {
                vapidKey: config.vapidKey,
                serviceWorkerRegistration: registration,
            });

            if (token) {
                currentToken.value = token;
                await registerToken(token);
            }

            return token || null;
        } catch {
            return null;
        }
    }

    async function registerToken(token: string) {
        try {
            await axios.post(route('fcm-tokens.store'), {
                token,
                device_type: 'web',
                device_name: navigator.userAgent.substring(0, 100),
            });
        } catch {
            // Registration may fail silently
        }
    }

    async function unregisterToken() {
        if (!currentToken.value) return;

        try {
            await axios.delete(route('fcm-tokens.destroy'), {
                data: { token: currentToken.value },
            });
            currentToken.value = null;
        } catch {
            // Unregistration may fail silently
        }
    }

    return {
        permissionStatus,
        currentToken,
        isSupported,
        requestPermission,
        unregisterToken,
    };
}
