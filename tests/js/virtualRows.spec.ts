import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useVirtualRows } from '@/composables/useVirtualRows';

function makeRows(n: number) {
    return Array.from({ length: n }, (_, i) => ({ id: i + 1 }));
}

describe('useVirtualRows', () => {
    it('windows 100k rows down to a viewport-sized slice without losing height', () => {
        const rows = ref(makeRows(100_000));
        const v = useVirtualRows({
            rows,
            container: ref(null),
            enabled: ref(true),
            rowHeight: 44,
            viewportHeight: 600,
            overscan: 8,
        });

        expect(v.windowRows.value.length).toBeLessThanOrEqual(22);
        expect(v.padTop.value + v.windowRows.value.length * 44 + v.padBottom.value).toBe(4_400_000);
    });

    it('keeps the window size constant and shifts firstIndex when scrolled', () => {
        const rows = ref(makeRows(100_000));
        const v = useVirtualRows({
            rows,
            container: ref(null),
            enabled: ref(true),
            rowHeight: 44,
            viewportHeight: 600,
            overscan: 8,
        });

        const before = v.windowRows.value.length;
        expect(v.firstIndex.value).toBe(0);

        v.scrollToIndex(50_000);

        expect(v.firstIndex.value).toBeGreaterThan(49_000);
        expect(v.windowRows.value.length).toBe(before);
        expect(v.padTop.value + v.windowRows.value.length * 44 + v.padBottom.value).toBe(4_400_000);
    });

    it('scrollToRow finds a row by its id, never by its index', () => {
        const rows = ref(makeRows(1_000));
        const v = useVirtualRows({
            rows,
            container: ref(null),
            enabled: ref(true),
            rowHeight: 44,
            viewportHeight: 600,
            overscan: 8,
        });

        v.scrollToRow(900);

        expect(v.windowRows.value.some(r => r.id === 900)).toBe(true);
    });

    it('is the identity function when disabled', () => {
        const rows = ref(makeRows(500));
        const v = useVirtualRows({
            rows,
            container: ref(null),
            enabled: ref(false),
            rowHeight: 44,
            viewportHeight: 600,
        });

        expect(v.windowRows.value).toBe(rows.value);
        expect(v.padTop.value).toBe(0);
        expect(v.padBottom.value).toBe(0);
        expect(v.firstIndex.value).toBe(0);
    });

    it('never strands the window past the end of a shorter list', () => {
        const rows = ref(makeRows(10_000));
        const v = useVirtualRows({
            rows,
            container: ref(null),
            enabled: ref(true),
            rowHeight: 44,
            viewportHeight: 600,
            overscan: 8,
        });

        v.scrollToIndex(9_999);
        rows.value = makeRows(5);

        expect(v.windowRows.value.length).toBe(5);
        expect(v.padTop.value).toBe(0);
        expect(v.padBottom.value).toBe(0);
    });
});
