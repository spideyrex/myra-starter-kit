import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { REORDER_INSTRUCTIONS_ID, useReorderable } from '@/composables/useReorderable';

interface Tile { key: string; title: string }

const tiles = ref<Tile[]>([
    { key: 'a', title: 'Alpha' },
    { key: 'b', title: 'Bravo' },
    { key: 'c', title: 'Charlie' },
]);

let onMove: ReturnType<typeof vi.fn>;

function build(enabled = true) {
    onMove = vi.fn();

    return useReorderable<Tile>({
        items: tiles,
        keyOf: tile => tile.key,
        onMove,
        announce: (tile, index, total) => `${tile.title} ${index + 1}/${total}`,
        enabled: () => enabled,
    });
}

function key(props: Record<string, unknown>, name: string): void {
    const event = { key: name, preventDefault: vi.fn() } as unknown as KeyboardEvent;
    (props.onKeydown as (e: KeyboardEvent) => void)(event);
}

describe('useReorderable', () => {
    beforeEach(() => {
        tiles.value = [
            { key: 'a', title: 'Alpha' },
            { key: 'b', title: 'Bravo' },
            { key: 'c', title: 'Charlie' },
        ];
    });

    it('grabs on Space and announces the position', () => {
        const r = build();

        key(r.handleProps(tiles.value[0], 0), ' ');

        expect(r.grabbed.value).toBe('a');
        expect(r.announcement.value).toBe('Alpha 1/3');
    });

    it('drops on a second Space', () => {
        const r = build();
        const props = r.handleProps(tiles.value[0], 0);

        key(props, ' ');
        key(props, ' ');

        expect(r.grabbed.value).toBeNull();
    });

    it('moves down one on ArrowDown while grabbed', () => {
        const r = build();
        const props = r.handleProps(tiles.value[0], 0);

        key(props, ' ');
        key(props, 'ArrowDown');

        expect(onMove).toHaveBeenCalledWith('a', 1);
        expect(r.announcement.value).not.toBe('');
    });

    it('moves up one on ArrowUp and ArrowLeft', () => {
        const r = build();
        const props = r.handleProps(tiles.value[1], 1);

        key(props, ' ');
        key(props, 'ArrowUp');
        key(props, 'ArrowLeft');

        expect(onMove).toHaveBeenNthCalledWith(1, 'b', 0);
        expect(onMove).toHaveBeenNthCalledWith(2, 'b', 0);
    });

    it('ignores arrows when nothing is grabbed', () => {
        const r = build();

        key(r.handleProps(tiles.value[0], 0), 'ArrowDown');

        expect(onMove).not.toHaveBeenCalled();
    });

    it('moves to the first and last position on Home and End', () => {
        const r = build();

        key(r.handleProps(tiles.value[2], 2), ' ');
        key(r.handleProps(tiles.value[2], 2), 'Home');
        expect(onMove).toHaveBeenCalledWith('c', 0);

        key(r.handleProps(tiles.value[2], 0), 'End');
        expect(onMove).toHaveBeenCalledWith('c', 2);
    });

    it('clamps a move past the ends', () => {
        const r = build();

        key(r.handleProps(tiles.value[0], 0), ' ');
        key(r.handleProps(tiles.value[0], 0), 'ArrowUp');

        expect(onMove).toHaveBeenCalledWith('a', 0);
    });

    it('restores the pre-grab index on Escape', () => {
        const r = build();

        key(r.handleProps(tiles.value[0], 0), ' ');
        key(r.handleProps(tiles.value[0], 2), 'Escape');

        expect(onMove).toHaveBeenLastCalledWith('a', 0);
        expect(r.grabbed.value).toBeNull();
        expect(r.announcement.value).not.toBe('');
    });

    it('does nothing at all when disabled', () => {
        const r = build(false);

        key(r.handleProps(tiles.value[0], 0), ' ');

        expect(r.grabbed.value).toBeNull();
        expect(onMove).not.toHaveBeenCalled();
    });

    it('never emits the deprecated drag ARIA attributes', () => {
        const props = build().handleProps(tiles.value[0], 0);

        expect(props).not.toHaveProperty('aria-grabbed');
        expect(props).not.toHaveProperty('aria-dropeffect');
    });

    it('points at the shared instructions node and carries a role description', () => {
        const props = build().handleProps(tiles.value[0], 0);

        expect(props['aria-describedby']).toBe(REORDER_INSTRUCTIONS_ID);
        expect(props['aria-roledescription']).toBeTruthy();
        expect(props.role).toBe('listitem');
        expect(props.tabindex).toBe(0);
    });

    it('routes pointer drag through the same onMove', () => {
        const r = build();

        (r.dragOptions.onUpdate as (e: { oldIndex: number; newIndex: number }) => void)({ oldIndex: 0, newIndex: 2 });

        expect(onMove).toHaveBeenCalledWith('a', 2);
    });

    it('every keyboard path writes a non-empty announcement', () => {
        const r = build();
        const props = r.handleProps(tiles.value[1], 1);

        for (const name of [' ', 'ArrowDown', 'ArrowUp', 'Home', 'End']) {
            r.announcement.value = '';
            key(props, name);
            expect(r.announcement.value, `${name} announced nothing`).not.toBe('');
        }
    });
});
