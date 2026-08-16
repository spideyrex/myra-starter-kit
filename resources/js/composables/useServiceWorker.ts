import { computed, onMounted, ref, type Ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import { purgeAppCaches, registerAppShell, unregisterAppShell } from '@/pwa/register';

let started = false;
let logoutHookInstalled = false;

/** Purge the shell cache before logout, so the next person here reads nothing. */
function installLogoutPurge(): void {
    if (logoutHookInstalled) return;
    logoutHookInstalled = true;

    router.on('before', (event: unknown) => {
        try {
            const url = String((event as { detail?: { visit?: { url?: unknown } } })?.detail?.visit?.url ?? '');
            if (url.includes('/logout')) void purgeAppCaches();
        } catch {
            // Never block a visit.
        }
    });
}

const registered = ref(false);
const updateAvailable = ref(false);
const waiting = ref<ServiceWorker | null>(null);

export interface UseServiceWorker {
    supported: boolean;
    registered: Ref<boolean>;
    offline: Ref<boolean>;
    updateAvailable: Ref<boolean>;
    applyUpdate(): Promise<void>;
    purge(): Promise<void>;
}

/**
 * Inert unless `pwaEnabled` is true AND the build is production. With the flag
 * off it actively unregisters a stale myra-sw.js and purges its caches, so
 * turning the feature off rolls it back rather than merely stopping updates.
 */
export function useServiceWorker(): UseServiceWorker {
    const page = usePage();
    const online = useOnline();
    const offline = computed(() => !online.value);

    const supported = typeof navigator !== 'undefined' && 'serviceWorker' in navigator;

    onMounted(async () => {
        if (started || !supported) return;
        started = true;
        installLogoutPurge();

        try {
            const props = page.props as Record<string, unknown>;
            const enabled = props.pwaEnabled === true && import.meta.env.PROD;

            if (!enabled) {
                await unregisterAppShell();
                return;
            }

            const buildId = typeof props.buildId === 'string' ? props.buildId : 'dev';
            const registration = await registerAppShell(buildId);
            if (!registration) return;

            registered.value = true;

            if (registration.waiting) {
                waiting.value = registration.waiting;
                updateAvailable.value = true;
            }

            registration.addEventListener('updatefound', () => {
                const installing = registration.installing;
                if (!installing) return;

                installing.addEventListener('statechange', () => {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        waiting.value = installing;
                        updateAvailable.value = true;
                    }
                });
            });
        } catch {
            // A failed registration must never break the layout.
        }
    });

    async function applyUpdate(): Promise<void> {
        try {
            waiting.value?.postMessage({ type: 'myra:skip-waiting' });
            updateAvailable.value = false;
            window.location.reload();
        } catch {
            // Nothing to apply.
        }
    }

    return {
        supported,
        registered,
        offline,
        updateAvailable,
        applyUpdate,
        purge: purgeAppCaches,
    };
}
