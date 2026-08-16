/*
 * Myra app-shell service worker.
 *
 * Hand-written on purpose: a generated runtimeCaching rule over /admin is
 * exactly the leak this must not ship, and a generated config is not reviewable.
 *
 * TWO INDEPENDENT GATES:
 *   1. isCacheable() is a POSITIVE allow-list. Anything not on it falls through
 *      to a bare `return` in the fetch handler, so the worker is not in the
 *      request path at all.
 *   2. cacheFirst() re-checks the RESPONSE before cache.put. A misclassification
 *      in gate 1 still cannot persist an authenticated body.
 */

const VERSION = '__MYRA_BUILD__';
const SHELL = `myra-shell-${VERSION}`;
const SHELL_PATHS = ['/offline.html', '/manifest.webmanifest', '/favicon.ico'];
const ASSET_PREFIX = '/build/';

function isCacheable(request) {
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

/** The second gate. A response that looks personalised is served, never stored. */
function isStorable(res) {
    if (!res || res.status !== 200 || res.type !== 'basic') return false;
    const vary = (res.headers.get('Vary') || '').toLowerCase();
    if (vary.includes('cookie')) return false;
    const control = (res.headers.get('Cache-Control') || '').toLowerCase();
    if (control.includes('private') || control.includes('no-store')) return false;
    return true;
}

async function cacheFirst(request) {
    const cache = await caches.open(SHELL);
    const hit = await cache.match(request);
    if (hit) return hit;

    try {
        const res = await fetch(request);
        if (isStorable(res)) {
            cache.put(request, res.clone());
        }
        return res;
    } catch (e) {
        const offline = await cache.match('/offline.html');
        if (offline && request.mode === 'navigate') return offline;
        throw e;
    }
}

self.addEventListener('install', (event) => {
    // No skipWaiting() on first install — a page already open keeps its worker.
    event.waitUntil(caches.open(SHELL).then((c) => c.addAll(SHELL_PATHS)).catch(() => undefined));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== SHELL).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
            .catch(() => undefined),
    );
});

self.addEventListener('fetch', (event) => {
    if (!isCacheable(event.request)) return;   // no respondWith → default network path
    event.respondWith(cacheFirst(event.request));
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'myra:purge') {
        event.waitUntil(caches.keys().then((ks) => Promise.all(ks.map((k) => caches.delete(k)))));
    }
    if (event.data && event.data.type === 'myra:skip-waiting') {
        self.skipWaiting();
    }
});
