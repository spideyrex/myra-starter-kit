import { nextTick, ref, type Ref } from 'vue';

/**
 * ui/form wires aria-invalid onto the control itself, so the first invalid
 * control is findable without mapping field names back to generated ids.
 */
export function useFocusFirstError(): {
    formRef: Ref<HTMLElement | null>;
    focusFirstError: () => Promise<void>;
} {
    const formRef = ref<HTMLElement | null>(null);

    async function focusFirstError(): Promise<void> {
        await nextTick();

        formRef.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    }

    return { formRef, focusFirstError };
}
