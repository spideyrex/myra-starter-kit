import { ref, toValue, type MaybeRefOrGetter, type Ref } from 'vue';

/** The id of the visually-hidden instructions node `handleProps` points at. */
export const REORDER_INSTRUCTIONS_ID = 'myra-reorder-instructions';

export interface UseReorderableOptions<T> {
    items: MaybeRefOrGetter<T[]>;
    keyOf: (item: T) => string;
    onMove: (key: string, toIndex: number) => void;
    announce: (item: T, index: number, total: number) => string;
    enabled?: MaybeRefOrGetter<boolean>;
    /** Already translated. The composable never renders English of its own. */
    roleDescription?: MaybeRefOrGetter<string>;
}

export interface UseReorderable<T> {
    grabbed: Ref<string | null>;
    announcement: Ref<string>;
    /** v-bind onto the tile handle. */
    handleProps(item: T, index: number): Record<string, unknown>;
    /** Spread onto a `<VueDraggable>` container; pointer drag calls the SAME onMove. */
    dragOptions: Record<string, unknown>;
}

/**
 * Keyboard-first reordering.
 *
 * Space grab/drop · ArrowUp/Left -1 · ArrowDown/Right +1 · Home first ·
 * End last · Escape cancel and restore the pre-grab index.
 *
 * `aria-grabbed` / `aria-dropeffect` are deliberately NOT emitted: both are
 * deprecated and unimplemented in current AT.
 */
export function useReorderable<T>(options: UseReorderableOptions<T>): UseReorderable<T> {
    const grabbed = ref<string | null>(null);
    const announcement = ref('');
    const originIndex = ref<number | null>(null);

    function items(): T[] {
        return toValue(options.items) ?? [];
    }

    function enabled(): boolean {
        return toValue(options.enabled ?? true) !== false;
    }

    function say(index: number): void {
        const list = items();
        const item = list[index];
        if (item === undefined) return;

        announcement.value = options.announce(item, index, list.length);
    }

    function moveTo(key: string, index: number): void {
        const total = items().length;
        const target = Math.max(0, Math.min(index, Math.max(0, total - 1)));

        options.onMove(key, target);
        say(target);
    }

    function onKeydown(event: KeyboardEvent, item: T, index: number): void {
        if (!enabled()) return;

        const key = options.keyOf(item);
        const total = items().length;

        if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
            event.preventDefault();

            if (grabbed.value === key) {
                grabbed.value = null;
                originIndex.value = null;
                say(index);

                return;
            }

            grabbed.value = key;
            originIndex.value = index;
            say(index);

            return;
        }

        if (event.key === 'Escape') {
            if (grabbed.value !== key) return;
            event.preventDefault();

            const origin = originIndex.value;
            grabbed.value = null;
            originIndex.value = null;

            if (origin !== null && origin !== index) moveTo(key, origin);
            else say(index);

            return;
        }

        if (grabbed.value !== key) return;

        const delta = event.key === 'ArrowUp' || event.key === 'ArrowLeft' ? -1
            : event.key === 'ArrowDown' || event.key === 'ArrowRight' ? 1
                : 0;

        if (delta !== 0) {
            event.preventDefault();
            moveTo(key, index + delta);

            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            moveTo(key, 0);

            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            moveTo(key, Math.max(0, total - 1));
        }
    }

    // Native pointer drag. Costs zero bundle weight on the dashboard; the demo
    // page drives the identical `onMove` through vue-draggable-plus.
    function onDragstart(event: DragEvent, item: T): void {
        if (!enabled()) return;

        const key = options.keyOf(item);
        grabbed.value = key;
        event.dataTransfer?.setData('text/plain', key);
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    }

    function onDragover(event: DragEvent): void {
        if (!enabled() || grabbed.value === null) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
    }

    function onDrop(event: DragEvent, _item: T, index: number): void {
        if (!enabled()) return;
        event.preventDefault();

        const key = grabbed.value ?? event.dataTransfer?.getData('text/plain') ?? null;
        grabbed.value = null;
        if (!key) return;

        moveTo(key, index);
    }

    function onDragend(): void {
        grabbed.value = null;
    }

    function handleProps(item: T, index: number): Record<string, unknown> {
        return {
            role: 'listitem',
            tabindex: 0,
            draggable: enabled(),
            'aria-roledescription': toValue(options.roleDescription ?? 'dashboardLayout.a11y.roledescription'),
            'aria-describedby': REORDER_INSTRUCTIONS_ID,
            'data-reorder-key': options.keyOf(item),
            'data-reorder-index': index,
            onKeydown: (event: KeyboardEvent) => onKeydown(event, item, index),
            onPointerdown: () => { if (enabled()) originIndex.value = index; },
            onDragstart: (event: DragEvent) => onDragstart(event, item),
            onDragover,
            onDrop: (event: DragEvent) => onDrop(event, item, index),
            onDragend,
        };
    }

    const dragOptions: Record<string, unknown> = {
        animation: 150,
        handle: '[data-reorder-key]',
        onUpdate: (event: { oldIndex?: number; newIndex?: number }) => {
            const from = event.oldIndex ?? -1;
            const to = event.newIndex ?? -1;
            const item = items()[from];

            if (item === undefined || to < 0) return;

            moveTo(options.keyOf(item), to);
        },
    };

    return { grabbed, announcement, handleProps, dragOptions };
}
