import { useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    variant?: 'default' | 'destructive';
}

interface ResourceFormOptions<T extends Record<string, any>> {
    data: T;
    storeRoute?: string;
    storeRouteParams?: any;
    updateRoute?: string;
    updateRouteParams?: any;
    confirm?: ConfirmOptions;
}

export function useResourceForm<T extends Record<string, any>>(options: ResourceFormOptions<T>) {
    const form = useForm<T>(options.data);
    const { confirm } = useConfirm();

    async function submit() {
        if (options.confirm) {
            const confirmed = await confirm({
                title: options.confirm.title,
                description: options.confirm.description,
                confirmText: options.confirm.confirmText ?? 'Confirm',
                variant: options.confirm.variant ?? 'default',
            });
            if (!confirmed) return;
        }

        if (options.updateRoute) {
            form.put(route(options.updateRoute, options.updateRouteParams));
        } else if (options.storeRoute) {
            form.post(route(options.storeRoute, options.storeRouteParams));
        }
    }

    return { form, submit };
}
