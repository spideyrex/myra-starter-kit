import { ref } from 'vue';

const isOpen = ref(false);
const title = ref('');
const description = ref('');
const confirmText = ref('Confirm');
const cancelText = ref('Cancel');
const variant = ref<'default' | 'destructive'>('default');
let resolver: ((value: boolean) => void) | null = null;

export function useConfirm() {
    const confirm = (options: {
        title: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'default' | 'destructive';
    }): Promise<boolean> => {
        title.value = options.title;
        description.value = options.description || '';
        confirmText.value = options.confirmText || 'Confirm';
        cancelText.value = options.cancelText || 'Cancel';
        variant.value = options.variant || 'default';
        isOpen.value = true;

        return new Promise((resolve) => {
            resolver = resolve;
        });
    };

    const handleConfirm = () => {
        isOpen.value = false;
        resolver?.(true);
        resolver = null;
    };

    const handleCancel = () => {
        isOpen.value = false;
        resolver?.(false);
        resolver = null;
    };

    return {
        isOpen,
        title,
        description,
        confirmText,
        cancelText,
        variant,
        confirm,
        handleConfirm,
        handleCancel,
    };
}
