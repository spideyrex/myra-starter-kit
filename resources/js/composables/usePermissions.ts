import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '@/types';

export function usePermissions() {
    const page = usePage<PageProps>();
    const user = computed(() => page.props.auth.user);

    const can = (permission: string): boolean => {
        return user.value.roles.includes('super-admin') || user.value.permissions.includes(permission);
    };

    const hasRole = (role: string): boolean => {
        return user.value.roles.includes(role);
    };

    const isSuperAdmin = computed(() => user.value.roles.includes('super-admin'));

    return {
        can,
        hasRole,
        isSuperAdmin,
        roles: computed(() => user.value.roles),
        permissions: computed(() => user.value.permissions),
    };
}
