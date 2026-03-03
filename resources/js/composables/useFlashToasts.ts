import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { toast } from 'vue-sonner';

export function useFlashToasts() {
    const page = usePage<PageProps>();

    watch(
        () => page.props.flash,
        (flash) => {
            if (!flash) return;

            if (flash.success) {
                toast.success(flash.success);
            }
            if (flash.error) {
                toast.error(flash.error);
            }
            if (flash.warning) {
                toast.warning(flash.warning);
            }
            if (flash.info) {
                toast.info(flash.info);
            }
        },
        { immediate: true, deep: true },
    );
}
