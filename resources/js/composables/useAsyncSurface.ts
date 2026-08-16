import { computed, onScopeDispose, ref, type ComputedRef, type Ref } from 'vue';

/**
 * The state machine every data surface runs. `delayMs` keeps a fast response
 * from flashing a skeleton at all; `minMs` keeps a skeleton that DID appear on
 * screen long enough to read, because a flicker reads as a glitch.
 */
export type SurfaceState =
    | 'idle' | 'loading' | 'streaming' | 'ready' | 'empty' | 'error' | 'offline';

export function useAsyncSurface<T>(options: {
    load: (signal: AbortSignal) => Promise<T>;
    delayMs?: number;
    minMs?: number;
    isEmpty?: (data: T) => boolean;
    immediate?: boolean;
}): {
    state: Ref<SurfaceState>;
    data: Ref<T | null>;
    error: Ref<unknown>;
    showSkeleton: ComputedRef<boolean>;
    reload(): Promise<void>;
    abort(): void;
} {
    const delayMs = Math.max(0, options.delayMs ?? 120);
    const minMs = Math.max(0, options.minMs ?? 320);

    const state = ref<SurfaceState>('idle') as Ref<SurfaceState>;
    const data = ref<T | null>(null) as Ref<T | null>;
    const error = ref<unknown>(null);
    const visible = ref(false);

    let controller: AbortController | null = null;
    let delayTimer: ReturnType<typeof setTimeout> | null = null;
    let shownAt = 0;
    let seq = 0;

    const showSkeleton = computed(() => visible.value && state.value === 'loading');

    function clearDelay(): void {
        if (delayTimer) clearTimeout(delayTimer);
        delayTimer = null;
    }

    function abort(): void {
        controller?.abort();
        controller = null;
        clearDelay();
    }

    async function settle(): Promise<void> {
        if (!visible.value) return;

        const held = Date.now() - shownAt;
        if (held >= minMs) return;

        await new Promise(resolve => setTimeout(resolve, minMs - held));
    }

    async function reload(): Promise<void> {
        const mine = ++seq;

        abort();
        controller = new AbortController();
        error.value = null;
        state.value = 'loading';
        visible.value = false;

        delayTimer = setTimeout(() => {
            if (mine !== seq) return;
            visible.value = true;
            shownAt = Date.now();
        }, delayMs);

        try {
            const result = await options.load(controller.signal);

            if (mine !== seq) return;

            clearDelay();
            await settle();

            if (mine !== seq) return;

            data.value = result;
            state.value = options.isEmpty?.(result) ? 'empty' : 'ready';
        } catch (e) {
            if (mine !== seq) return;

            clearDelay();
            await settle();

            if (mine !== seq) return;

            error.value = e;
            state.value = typeof navigator !== 'undefined' && navigator.onLine === false
                ? 'offline'
                : 'error';
        } finally {
            if (mine === seq) visible.value = false;
        }
    }

    if (options.immediate) void reload();

    onScopeDispose(() => abort(), true);

    return { state, data, error, showSkeleton, reload, abort };
}
