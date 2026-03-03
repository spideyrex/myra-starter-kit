import type { Directive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export const vCan: Directive<HTMLElement, string> = {
    mounted(el, binding) {
        const page = usePage<PageProps>();
        const user = page.props.auth.user;
        const permission = binding.value;

        const hasPermission =
            user.roles.includes('super-admin') ||
            user.permissions.includes(permission);

        if (!hasPermission) {
            el.style.display = 'none';
        }
    },
    updated(el, binding) {
        const page = usePage<PageProps>();
        const user = page.props.auth.user;
        const permission = binding.value;

        const hasPermission =
            user.roles.includes('super-admin') ||
            user.permissions.includes(permission);

        el.style.display = hasPermission ? '' : 'none';
    },
};
