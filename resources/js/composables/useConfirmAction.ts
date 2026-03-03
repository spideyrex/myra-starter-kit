import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    variant?: 'default' | 'destructive';
}

export function useConfirmAction() {
    const { confirm } = useConfirm();

    async function confirmDelete(
        routeName: string,
        routeParams: any,
        options?: ConfirmOptions,
    ) {
        const confirmed = await confirm({
            title: options?.title ?? 'Delete',
            description: options?.description ?? 'Are you sure? This action cannot be undone.',
            confirmText: options?.confirmText ?? 'Delete',
            variant: options?.variant ?? 'destructive',
        });
        if (confirmed) {
            router.delete(route(routeName, routeParams));
        }
    }

    async function confirmPost(
        routeName: string,
        routeParams: any,
        data?: Record<string, any>,
        options?: ConfirmOptions,
    ) {
        const confirmed = await confirm({
            title: options?.title ?? 'Confirm',
            description: options?.description,
            confirmText: options?.confirmText ?? 'Confirm',
            variant: options?.variant ?? 'default',
        });
        if (confirmed) {
            router.post(route(routeName, routeParams), data ?? {});
        }
    }

    return { confirmDelete, confirmPost };
}
