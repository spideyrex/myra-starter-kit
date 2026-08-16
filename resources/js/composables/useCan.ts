import { usePage } from '@inertiajs/vue3';

/**
 * Identity comes from Inertia's shared props, never from a page's own data.
 * Extracted from DashboardGrid so the grid, the catalogue and the editor share
 * one implementation.
 */
export function useCan(): { can: (ability: string) => boolean } {
    const page = usePage<any>();

    function can(ability: string): boolean {
        const user = page.props?.auth?.user;
        if (!user) return false;
        if (user.roles?.includes('super-admin')) return true;

        return user.permissions?.includes(ability) ?? false;
    }

    return { can };
}
