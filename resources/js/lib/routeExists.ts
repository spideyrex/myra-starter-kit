/**
 * True only when Ziggy knows the route.
 *
 * Bundles A, B and C each define their own catalogue routes, and a bundle that
 * has not been merged simply has none. Guarding here is what lets one bundle
 * link to another's page without the link breaking in an isolated worktree.
 */
export function routeExists(name: string): boolean {
    try {
        const router = (globalThis as any).route;

        if (typeof router !== 'function') {
            return false;
        }

        const instance = router();

        return typeof instance?.has === 'function' && instance.has(name) === true;
    } catch {
        return false;
    }
}
