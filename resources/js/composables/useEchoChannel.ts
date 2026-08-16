import { onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue';

/**
 * Refcounted private-channel subscription.
 *
 * The pre-v2.5 `useEcho()` held its channel in a module-level `let` and only
 * `stopListening`'d on unmount — it never `leave()`d, so two hosts clobbered
 * each other and the socket outlived the page. This is per-instance: handlers
 * are unbound on dispose and the LAST holder of a channel calls `echo.leave()`.
 *
 * Inert when VITE_REVERB_APP_KEY is unset. Never throws.
 */

type Handlers = Record<string, (payload: any) => void>;

interface Subscription {
    channel: any;
    refs: number;
}

const subscriptions = new Map<string, Subscription>();

function echoEnabled(): boolean {
    try {
        return Boolean(import.meta.env?.VITE_REVERB_APP_KEY);
    } catch {
        return false;
    }
}

export function useEchoChannel(
    name: MaybeRefOrGetter<string | null>,
    handlers: Handlers,
): { connected: Ref<boolean>; enabled: boolean } {
    const enabled = echoEnabled();
    const connected = ref(false);

    let current: string | null = null;
    let bound: Array<[string, (payload: any) => void]> = [];
    let generation = 0;

    async function leave(): Promise<void> {
        const target = current;
        current = null;
        connected.value = false;

        if (!target) return;

        const entry = subscriptions.get(target);

        try {
            for (const [event, handler] of bound) {
                entry?.channel?.stopListening(event, handler);
            }
        } catch {
            // A half-dead channel is still a channel we are done with.
        }

        bound = [];

        if (!entry) return;

        entry.refs -= 1;

        if (entry.refs > 0) return;

        subscriptions.delete(target);

        try {
            const { default: echo } = await import('@/echo');
            echo.leave(target);
        } catch {
            // Reverb not available — nothing to leave.
        }
    }

    async function join(target: string | null): Promise<void> {
        const mine = ++generation;

        await leave();

        if (!enabled || !target || mine !== generation) return;

        try {
            const { default: echo } = await import('@/echo');

            if (mine !== generation) return;

            let entry = subscriptions.get(target);

            if (!entry) {
                entry = { channel: echo.private(target), refs: 0 };
                subscriptions.set(target, entry);
            }

            entry.refs += 1;
            current = target;

            for (const [event, handler] of Object.entries(handlers)) {
                const safe = (payload: any) => {
                    // A throwing handler must not kill the socket.
                    try {
                        handler(payload);
                    } catch {
                        // Swallowed by design.
                    }
                };

                entry.channel.listen(event, safe);
                bound.push([event, safe]);
            }

            connected.value = true;
        } catch {
            connected.value = false;
        }
    }

    watch(() => toValue(name), (target) => { void join(target); }, { immediate: true });

    onScopeDispose(() => { void leave(); }, true);

    return { connected, enabled };
}
