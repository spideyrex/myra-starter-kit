import { computed, onScopeDispose, ref, watch, type ComputedRef, type Ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { useDocumentVisibility } from '@vueuse/core';
import { useEchoChannel } from '@/composables/useEchoChannel';
import { adminPath } from '@/lib/adminPath';
import type { ReportBinding } from '@/composables/useDashboardWidgets';
import type { ReportResultPayload, StatResultPayload } from '@/components/admin/charts/types';

/**
 * ONE socket, ONE coalesced batch request and ONE timer for a whole dashboard,
 * whatever the widget count. Widgets never fetch — the bus owns every
 * round-trip, so twenty live widgets are twenty subscriptions to one stream.
 *
 * With no socket (the default: BROADCAST_CONNECTION=log, no
 * VITE_REVERB_APP_KEY) this is a correct POLLED dashboard. Silently degrading
 * to polling is the intended behaviour; a dashboard that looks live and is not
 * would be worse.
 */

export interface WidgetResult {
    payload: ReportResultPayload | StatResultPayload;
    version: string;
    at: number;
}

export interface UseDashboardBus {
    results: Readonly<Ref<Record<string, WidgetResult>>>;
    loading: Readonly<Ref<Record<string, boolean>>>;
    errored: Readonly<Ref<Record<string, string | null>>>;
    freshness: Readonly<Ref<Record<string, number>>>;
    live: Readonly<Ref<Record<string, boolean>>>;
    stale: Readonly<Ref<Set<string>>>;
    connected: Readonly<Ref<boolean>>;
    paused: ComputedRef<boolean>;
    track(slot: string, binding: ReportBinding, opts?: { pollSeconds?: number }): void;
    untrack(slot: string): void;
    invalidate(slots?: string[]): void;
    flush(): Promise<void>;
    /**
     * The exact handler the socket calls, exposed so the demo and the tests can
     * exercise coalescing without a broker. Feeding it topics is identical to
     * receiving a `widget.data.changed` frame.
     */
    signal(topics: string[]): void;
}

export interface BatchBody {
    widgets: Record<string, ReportBinding>;
    versions: Record<string, string>;
}

/** Swappable only so the demo can run with no server. Production uses axios. */
export type BusTransport = (
    body: BatchBody,
    signal: AbortSignal,
) => Promise<{ results: Record<string, any> }>;

interface TrackedSlot {
    binding: ReportBinding;
    pollSeconds: number;
    lastPoll: number;
}

const widgetsUrlFallback = () => adminPath('dashboard/widgets/data');
const MIN_POLL_SECONDS = 5;

function widgetsUrl(): string {
    try {
        const resolve = (globalThis as any).route;
        const url = typeof resolve === 'function' ? resolve('admin.reports.widgets') : null;
        return typeof url === 'string' && url !== '' ? url : widgetsUrlFallback();
    } catch {
        return widgetsUrlFallback();
    }
}

function defaultChannel(): string | null {
    try {
        const id = (usePage() as any)?.props?.auth?.user?.id;
        return id ? `myra.dashboard.${id}` : null;
    } catch {
        // No Inertia app around us (the playground, a unit test): no channel.
        return null;
    }
}

function nextFrame(fn: () => void): void {
    // Called on globalThis, not detached: a bare `raf(cb)` is an illegal
    // invocation in a browser.
    if (typeof globalThis.requestAnimationFrame === 'function') {
        globalThis.requestAnimationFrame(() => fn());
        return;
    }

    setTimeout(fn, 0);
}

function chunk<T>(items: T[], size: number): T[][] {
    const out: T[][] = [];
    const step = Math.max(1, size);
    for (let i = 0; i < items.length; i += step) out.push(items.slice(i, i + step));
    return out;
}

export function useDashboardBus(opts?: {
    maxBatch?: number;
    coalesceMs?: number;
    channel?: string | null;
    transport?: BusTransport;
}): UseDashboardBus {
    const maxBatch = Math.max(1, opts?.maxBatch ?? 12);
    const coalesceMs = Math.max(0, opts?.coalesceMs ?? 400);
    const channelName = opts?.channel === undefined ? defaultChannel() : opts.channel;

    const results = ref<Record<string, WidgetResult>>({});
    const loading = ref<Record<string, boolean>>({});
    const errored = ref<Record<string, string | null>>({});
    const freshness = ref<Record<string, number>>({});
    const live = ref<Record<string, boolean>>({});
    const stale = ref<Set<string>>(new Set());

    const tracked = new Map<string, TrackedSlot>();
    const trackedCount = ref(0);
    const dirty = new Set<string>();

    let scheduled = false;
    let inflight: Promise<void> | null = null;
    let queued = false;
    let seq = 0;
    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;
    let signalTimer: ReturnType<typeof setTimeout> | null = null;
    let disposed = false;

    const visibility = useDocumentVisibility();
    const paused = computed(() => visibility.value === 'hidden');

    // ---------------------------------------------------------------- polling

    function retimer(): void {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }

        // Zero polling widgets means NO timer at all, not a timer that does nothing.
        const intervals = [...tracked.values()]
            .map(slot => slot.pollSeconds)
            .filter(seconds => seconds > 0);

        if (intervals.length === 0) return;

        const smallest = Math.max(MIN_POLL_SECONDS, Math.min(...intervals));
        timer = setInterval(tick, smallest * 1000);
    }

    function tick(): void {
        if (paused.value) return;

        const now = Date.now();
        let any = false;

        for (const [slot, entry] of tracked) {
            if (entry.pollSeconds <= 0) continue;
            if (now - entry.lastPoll < entry.pollSeconds * 1000 - 50) continue;

            entry.lastPoll = now;
            dirty.add(slot);
            any = true;
        }

        if (any) schedule();
    }

    // --------------------------------------------------------------- flushing

    function schedule(): void {
        if (scheduled || disposed) return;
        scheduled = true;

        queueMicrotask(() => nextFrame(() => {
            scheduled = false;
            void flush();
        }));
    }

    async function flush(): Promise<void> {
        if (disposed || paused.value) return;

        if (inflight) {
            // A flush scheduled while one is airborne is queued, never raced.
            queued = true;
            return inflight;
        }

        inflight = run();

        try {
            await inflight;
        } finally {
            inflight = null;
        }

        if (queued) {
            queued = false;
            await flush();
        }
    }

    async function run(): Promise<void> {
        const slots = [...dirty].filter(slot => tracked.has(slot));
        dirty.clear();

        if (slots.length === 0) return;

        for (const group of chunk(slots, maxBatch)) {
            await request(group);
        }
    }

    async function request(slots: string[]): Promise<void> {
        const widgets: Record<string, ReportBinding> = {};
        const versions: Record<string, string> = {};

        for (const slot of slots) {
            const entry = tracked.get(slot);
            if (!entry) continue;

            widgets[slot] = { ...entry.binding };

            const version = results.value[slot]?.version;
            if (version) versions[slot] = version;

            loading.value[slot] = true;
        }

        if (Object.keys(widgets).length === 0) return;

        const mine = ++seq;
        controller = new AbortController();

        try {
            const data = opts?.transport
                ? await opts.transport({ widgets, versions }, controller.signal)
                : (await axios.post(
                    widgetsUrl(),
                    { widgets, versions },
                    { signal: controller.signal },
                ))?.data;

            if (mine !== seq) return;

            apply(data?.results ?? {}, slots);
        } catch {
            if (mine !== seq) return;

            // One failing report never blanks the grid: only its own slots move.
            for (const slot of slots) errored.value[slot] = 'live.errors.slot';
        } finally {
            if (mine === seq) {
                for (const slot of slots) loading.value[slot] = false;
            }
        }
    }

    function apply(data: Record<string, any>, slots: string[]): void {
        const at = Date.now();

        for (const slot of slots) {
            const payload = data[slot];

            if (payload === undefined || payload === null) {
                errored.value[slot] = 'live.errors.slot';
                continue;
            }

            errored.value[slot] = null;
            freshness.value[slot] = at;
            stale.value.delete(slot);

            // The short-circuit: the server ran no aggregation, so the result we
            // already hold is still correct — keep it, reference and all.
            if (payload.unchanged === true) {
                const held = results.value[slot];
                if (held && typeof payload.version === 'string' && payload.version !== '') {
                    held.version = payload.version;
                }
                continue;
            }

            results.value[slot] = {
                payload,
                version: typeof payload.version === 'string' ? payload.version : '',
                at,
            };
        }

        stale.value = new Set(stale.value);
    }

    // ---------------------------------------------------------------- realtime

    const channel = computed(() => (trackedCount.value === 0 ? null : channelName));

    /**
     * The payload is a change SIGNAL, so nothing here reads data out of it: it
     * intersects topics with what we track, marks those stale, and debounces
     * ONE refetch through the Gate-checked batch endpoint. A burst of thirty
     * frames inside `coalesceMs` costs one request.
     */
    function signal(rawTopics: unknown): void {
        const topics: string[] = Array.isArray(rawTopics)
            ? rawTopics.filter((t: unknown): t is string => typeof t === 'string')
            : [];

        if (topics.length === 0 || disposed) return;

        let any = false;

        for (const [slot, entry] of tracked) {
            if (!entry.binding?.report || !topics.includes(entry.binding.report)) continue;

            stale.value.add(slot);
            live.value[slot] = true;
            dirty.add(slot);
            any = true;
        }

        if (!any) return;

        stale.value = new Set(stale.value);

        if (signalTimer) clearTimeout(signalTimer);
        signalTimer = setTimeout(() => {
            signalTimer = null;
            void flush();
        }, coalesceMs);
    }

    const { connected } = useEchoChannel(channel, {
        '.widget.data.changed': (payload: any) => signal(payload?.topics),
    });

    // ------------------------------------------------------------------- api

    function track(slot: string, binding: ReportBinding, options?: { pollSeconds?: number }): void {
        if (!slot || !binding?.report) return;

        const existing = tracked.get(slot);

        tracked.set(slot, {
            binding,
            pollSeconds: Math.max(0, Math.trunc(options?.pollSeconds ?? 0)),
            lastPoll: existing?.lastPoll ?? Date.now(),
        });

        trackedCount.value = tracked.size;
        dirty.add(slot);
        retimer();
        schedule();
    }

    function untrack(slot: string): void {
        if (!tracked.delete(slot)) return;

        trackedCount.value = tracked.size;
        dirty.delete(slot);
        delete loading.value[slot];
        delete errored.value[slot];
        delete live.value[slot];
        stale.value.delete(slot);
        retimer();
    }

    function invalidate(slots?: string[]): void {
        const targets = slots ?? [...tracked.keys()];
        let any = false;

        for (const slot of targets) {
            if (!tracked.has(slot)) continue;
            dirty.add(slot);
            any = true;
        }

        if (any) schedule();
    }

    // Resume flushes ONCE. Ticks that fired while hidden marked nothing, so
    // there are no missed ticks to replay.
    watch(paused, (isPaused) => {
        if (isPaused || disposed) return;
        invalidate();
        schedule();
    });

    onScopeDispose(() => {
        disposed = true;
        controller?.abort();
        if (timer) clearInterval(timer);
        if (signalTimer) clearTimeout(signalTimer);
        timer = null;
        signalTimer = null;
        tracked.clear();
        trackedCount.value = 0;
    }, true);

    return {
        results: results as Readonly<Ref<Record<string, WidgetResult>>>,
        loading: loading as Readonly<Ref<Record<string, boolean>>>,
        errored: errored as Readonly<Ref<Record<string, string | null>>>,
        freshness: freshness as Readonly<Ref<Record<string, number>>>,
        live: live as Readonly<Ref<Record<string, boolean>>>,
        stale: stale as Readonly<Ref<Set<string>>>,
        connected,
        paused,
        track,
        untrack,
        invalidate,
        flush,
        signal,
    };
}
