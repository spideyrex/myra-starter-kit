import { usePage } from '@inertiajs/vue3';

const DEFAULT_PREFIX = 'dashboard';

/**
 * The configured admin URL segment, shared from `myra.admin.prefix`.
 *
 * Only for building a fallback URL when Ziggy cannot resolve a route name —
 * a named route is always preferable, because it survives this changing.
 */
export function adminPrefix(): string {
    try {
        const shared = usePage().props as Record<string, unknown> | undefined;
        const prefix = shared?.adminPrefix;

        if (typeof prefix === 'string' && prefix.trim() !== '') {
            return prefix.replace(/^\/+|\/+$/g, '') || DEFAULT_PREFIX;
        }
    } catch {
        // usePage() throws outside an Inertia app (unit tests, SSR probes).
    }

    return DEFAULT_PREFIX;
}

/** `adminPath('landing/builder')` -> `/dashboard/landing/builder`. */
export function adminPath(path = ''): string {
    const suffix = path.replace(/^\/+/, '');

    return `/${adminPrefix()}${suffix === '' ? '' : `/${suffix}`}`;
}
