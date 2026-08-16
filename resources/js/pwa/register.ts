/**
 * The app-shell registration surface.
 *
 * `isCacheableRequest` below is the SINGLE SOURCE OF TRUTH for the caching
 * rule; `public/myra-sw.js` mirrors it verbatim and
 * tests/js/serviceWorkerParity.spec.ts fails the build if the two ever drift.
 */

const SHELL_PATHS = ['/offline.html', '/manifest.webmanifest', '/favicon.ico'];
const ASSET_PREFIX = '/build/';

export const SW_PATH = '/myra-sw.js';

export interface CacheableRequest {
    method: string;
    headers: Headers;
    url: string;
}

/** MIRRORED VERBATIM in public/myra-sw.js. Do not edit one without the other. */
export function isCacheableRequest(request: CacheableRequest): boolean {
    if (request.method !== 'GET') return false;
    if (request.headers.get('X-Inertia')) return false;
    if (request.headers.get('X-Requested-With') === 'XMLHttpRequest') return false;
    const accept = request.headers.get('Accept') || '';
    if (accept.includes('application/json')) return false;
    if (accept.includes('text/event-stream')) return false;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return false;
    if (url.search.length > 0) return false;
    return url.pathname.startsWith(ASSET_PREFIX) || SHELL_PATHS.includes(url.pathname);
}

export function isCacheable(url: URL, request: { method: string; headers: Headers }): boolean {
    return isCacheableRequest({ method: request.method, headers: request.headers, url: url.toString() });
}

function swSupported(): boolean {
    return typeof navigator !== 'undefined' && 'serviceWorker' in navigator;
}

/** Registers the shell worker. Never throws. */
export async function registerAppShell(buildId: string): Promise<ServiceWorkerRegistration | null> {
    if (!swSupported()) return null;

    try {
        return await navigator.serviceWorker.register(`${SW_PATH}?v=${encodeURIComponent(buildId)}`, { scope: '/' });
    } catch {
        return null;
    }
}

/**
 * Turning the flag off is a real rollback: any myra-sw.js registration is
 * removed and every cache purged. NEVER touches firebase-messaging-sw.js.
 */
export async function unregisterAppShell(): Promise<void> {
    if (!swSupported()) return;

    try {
        const registrations = await navigator.serviceWorker.getRegistrations();

        for (const registration of registrations) {
            const script = registration.active?.scriptURL
                || registration.waiting?.scriptURL
                || registration.installing?.scriptURL
                || '';

            if (script.includes('myra-sw.js')) {
                await registration.unregister();
            }
        }
    } catch {
        // A browser that refuses to enumerate is already not caching for us.
    }

    await purgeAppCaches();
}

/** Called from the logout handler BEFORE the POST fires. */
export async function purgeAppCaches(): Promise<void> {
    try {
        navigator.serviceWorker?.controller?.postMessage({ type: 'myra:purge' });
    } catch {
        // No controller yet — the direct delete below is enough.
    }

    try {
        if (typeof caches === 'undefined') return;
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('myra-')).map((k) => caches.delete(k)));
    } catch {
        // Storage denied. Nothing was written either.
    }
}
