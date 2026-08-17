import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import { envelope, unwrap, type FieldKind, type FrameMessage, type HostMessage } from '@/pagebuilder/liveEditProtocol';

/**
 * The builder half of the live-edit bridge.
 *
 * Owns nothing about the draft: it translates frame messages into callbacks and
 * lets the editor decide what a change means. That keeps undo/redo, validation
 * and the dirty flag in the one place that already implements them.
 */
export interface UseLiveEditHostOptions {
    /** The preview iframe. May be null until the token resolves. */
    frame: Ref<HTMLIFrameElement | null>;
    onChange(block: string, path: string, value: string): void;
    onSelect(block: string): void;
    onActivate(block: string, path: string, kind: FieldKind): void;
}

export interface UseLiveEditHost {
    /** True once the framed page has announced itself. */
    connected: Ref<boolean>;
    /** Turn editing affordances on or off inside the frame. */
    setEnabled(on: boolean): void;
    /** Ring the section that the editor is focused on. */
    highlight(block: string | null): void;
}

/**
 * `items.0.title` -> a new `items` array with that one field replaced.
 *
 * Returns the ROOT field name and the value to store under it, so the caller
 * still goes through the ordinary `update(id, field, value)` path and gets
 * history for free. A path that does not resolve returns null and is dropped.
 */
export function applyPath(
    data: Record<string, unknown>,
    path: string,
    value: unknown,
): { field: string; value: unknown } | null {
    const parts = path.split('.').filter(part => part !== '');

    if (parts.length === 0) return null;

    const [root, ...rest] = parts;

    if (rest.length === 0) return { field: root, value };

    // JSON, never structuredClone: `data` arrives as Vue reactive state, and a
    // reactive Proxy cannot be structured-cloned (DataCloneError). That mistake
    // breaks repeater edits ONLY — scalar fields never reach this line — which
    // is what made it look like the bridge worked.
    const clone = JSON.parse(JSON.stringify(data[root] ?? null)) as unknown;

    let cursor: unknown = clone;

    for (let i = 0; i < rest.length - 1; i++) {
        if (cursor === null || typeof cursor !== 'object') return null;

        cursor = (cursor as Record<string, unknown>)[rest[i]];
    }

    if (cursor === null || typeof cursor !== 'object') return null;

    (cursor as Record<string, unknown>)[rest[rest.length - 1]] = value;

    return { field: root, value: clone };
}

export function useLiveEditHost(options: UseLiveEditHostOptions): UseLiveEditHost {
    const connected = ref(false);

    let wanted = false;

    function send(message: HostMessage): void {
        const target = options.frame.value?.contentWindow;

        if (!target) return;

        try {
            target.postMessage(envelope(message), window.location.origin);
        } catch {
            // A frame mid-navigation is not worth failing the editor over.
        }
    }

    function onMessage(event: MessageEvent): void {
        if (event.origin !== window.location.origin) return;

        // Only our own frame may drive the draft.
        if (event.source !== options.frame.value?.contentWindow) return;

        const message = unwrap<FrameMessage>(event.data);

        if (message === null) return;

        if (message.type === 'ready') {
            connected.value = true;
            send({ type: 'enable', on: wanted });

            return;
        }

        if (message.type === 'change') {
            options.onChange(message.block, message.field, message.value);

            return;
        }

        if (message.type === 'select') {
            if (message.block !== '') options.onSelect(message.block);

            return;
        }

        if (message.type === 'activate') {
            options.onActivate(message.block, message.field, message.kind);
        }
    }

    onMounted(() => window.addEventListener('message', onMessage));
    onBeforeUnmount(() => window.removeEventListener('message', onMessage));

    return {
        connected,
        setEnabled(on: boolean): void {
            wanted = on;
            send({ type: 'enable', on });
        },
        highlight(block: string | null): void {
            send({ type: 'highlight', block });
        },
    };
}
