/**
 * The live-edit wire protocol between the builder and its preview frame.
 *
 * The frame is same-origin, so postMessage is not strictly required — but it
 * keeps the two documents at arm's length, which is what lets the preview stay
 * the REAL public page rather than a bespoke renderer.
 */

export const LIVE_EDIT_CHANNEL = 'myra-live-edit';

/** How a field is edited. Only `text` and `multiline` are edited in place. */
export type FieldKind = 'text' | 'multiline' | 'html' | 'image' | 'link';

export const INLINE_KINDS: readonly FieldKind[] = ['text', 'multiline'];

export function isInlineKind(kind: string): kind is 'text' | 'multiline' {
    return (INLINE_KINDS as readonly string[]).includes(kind);
}

/** Frame → builder. */
export type FrameMessage =
    | { type: 'ready' }
    | { type: 'change'; block: string; field: string; value: string }
    | { type: 'select'; block: string }
    | { type: 'activate'; block: string; field: string; kind: FieldKind };

/** Builder → frame. */
export type HostMessage =
    | { type: 'enable'; on: boolean }
    | { type: 'highlight'; block: string | null };

export type Envelope<T> = T & { channel: typeof LIVE_EDIT_CHANNEL };

export function envelope<T extends object>(message: T): Envelope<T> {
    return { ...message, channel: LIVE_EDIT_CHANNEL };
}

/**
 * A message is ours only if it carries the channel marker. Any other frame on
 * the page may postMessage at us; nothing else is read.
 */
export function unwrap<T>(data: unknown): T | null {
    if (data === null || typeof data !== 'object') return null;

    const record = data as Record<string, unknown>;

    if (record.channel !== LIVE_EDIT_CHANNEL) return null;
    if (typeof record.type !== 'string' || record.type === '') return null;

    return data as T;
}
