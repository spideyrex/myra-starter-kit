import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';

export interface VirtualRowsOptions<T> {
    rows: Ref<T[]>;
    container: Ref<HTMLElement | null>;
    enabled: Ref<boolean>;
    rowHeight: number;
    viewportHeight: number;
    overscan?: number;
}

export interface VirtualRowsResult<T> {
    windowRows: ComputedRef<T[]>;
    padTop: ComputedRef<number>;
    padBottom: ComputedRef<number>;
    firstIndex: ComputedRef<number>;
    scrollToIndex(index: number): void;
    scrollToRow(id: string | number): void;
    onScroll(): void;
}

/**
 * A windowing primitive with zero dependencies. It deliberately returns row
 * SLICES plus two pixel pads, so the host can keep a real <table> (and therefore
 * its <colgroup> widths, sticky header and column manager) instead of the div
 * grid a generic virtualiser would force.
 *
 * The host owns the listener: bind `@scroll.passive="onScroll"` on the scrolling
 * container. Nothing is attached here, so there is nothing to leak on unmount.
 */
export function useVirtualRows<T extends { id: string | number }>(
    opts: VirtualRowsOptions<T>,
): VirtualRowsResult<T> {
    const rowHeight = Math.max(1, Math.floor(opts.rowHeight) || 1);
    const overscan = Math.max(0, opts.overscan ?? 8);
    const viewport = Math.max(rowHeight, Math.floor(opts.viewportHeight) || rowHeight);

    const scrollTop = ref(0);

    const windowSize = computed(() => Math.ceil(viewport / rowHeight) + overscan);

    const firstIndex = computed(() => {
        if (!opts.enabled.value) return 0;
        const total = opts.rows.value.length;
        const maxStart = Math.max(0, total - windowSize.value);
        const raw = Math.floor(scrollTop.value / rowHeight) - Math.floor(overscan / 2);
        return Math.max(0, Math.min(raw, maxStart));
    });

    const windowRows = computed<T[]>(() => {
        if (!opts.enabled.value) return opts.rows.value;
        return opts.rows.value.slice(firstIndex.value, firstIndex.value + windowSize.value);
    });

    const padTop = computed(() => (opts.enabled.value ? firstIndex.value * rowHeight : 0));

    const padBottom = computed(() => {
        if (!opts.enabled.value) return 0;
        const after = opts.rows.value.length - firstIndex.value - windowRows.value.length;
        return Math.max(0, after) * rowHeight;
    });

    function onScroll(): void {
        scrollTop.value = opts.container.value?.scrollTop ?? scrollTop.value;
    }

    function scrollToIndex(index: number): void {
        const clamped = Math.max(0, Math.min(index, Math.max(0, opts.rows.value.length - 1)));
        const top = clamped * rowHeight;
        scrollTop.value = top;
        if (opts.container.value) opts.container.value.scrollTop = top;
    }

    function scrollToRow(id: string | number): void {
        const index = opts.rows.value.findIndex(r => r.id === id);
        if (index >= 0) scrollToIndex(index);
    }

    // A shorter list must never leave the window stranded past its end.
    watch(
        () => opts.rows.value.length,
        () => { if (scrollTop.value > opts.rows.value.length * rowHeight) scrollTop.value = 0; },
    );

    return { windowRows, padTop, padBottom, firstIndex, scrollToIndex, scrollToRow, onScroll };
}
