import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick } from 'vue';

/** The fixture the PHP suite wrote from the REAL batch endpoint. */
import batch from './fixtures/widget-batch.json';

const postMock = vi.hoisted(() => vi.fn());
const listenMock = vi.hoisted(() => vi.fn());
const stopListeningMock = vi.hoisted(() => vi.fn());
const leaveMock = vi.hoisted(() => vi.fn());
const privateMock = vi.hoisted(() => vi.fn(() => ({
    listen: listenMock,
    stopListening: stopListeningMock,
})));

vi.mock('axios', () => ({ default: { post: postMock } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: {} }) }));
vi.mock('@/echo', () => ({
    default: { private: privateMock, leave: leaveMock, disconnect: vi.fn() },
    disconnect: vi.fn(),
}));

import { useDashboardBus } from '@/composables/useDashboardBus';

const SLOT = 'signups-by-status';
const PAYLOAD = (batch as any).results[SLOT];

function ok(slots: string[], version = 'v1') {
    const results: Record<string, any> = {};
    for (const slot of slots) results[slot] = { ...PAYLOAD, version };
    return { data: { results } };
}

async function settle(times = 4): Promise<void> {
    for (let i = 0; i < times; i++) {
        await Promise.resolve();
        await new Promise(resolve => setTimeout(resolve, 0));
    }
}

function setVisibility(value: 'visible' | 'hidden'): void {
    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => value,
    });
    document.dispatchEvent(new Event('visibilitychange'));
}

let scope: ReturnType<typeof effectScope>;

beforeEach(() => {
    vi.stubEnv('VITE_REVERB_APP_KEY', 'test-key');
    vi.stubGlobal('requestAnimationFrame', (cb: any) => { cb(0); return 1; });
    postMock.mockReset();
    leaveMock.mockReset();
    listenMock.mockReset();
    privateMock.mockClear();
    setVisibility('visible');
    scope = effectScope();
});

afterEach(() => {
    scope.stop();
    vi.restoreAllMocks();
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
});

describe('useDashboardBus', () => {
    it('coalesces twenty widgets mounting in one tick into ONE request', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ maxBatch: 24, channel: null }))!;

        for (let i = 0; i < 20; i++) {
            bus.track(`slot-${i}`, { report: 'users', dimension: 'status' });
        }

        await settle();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(Object.keys(postMock.mock.calls[0][1].widgets)).toHaveLength(20);
    });

    it('sends the server payload straight through to results', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track(SLOT, { report: 'users', dimension: 'status' });

        await settle();

        expect(bus.results.value[SLOT].payload).toMatchObject({ report: 'users' });
        expect((bus.results.value[SLOT].payload as any).rows).toHaveLength(PAYLOAD.rows.length);
        expect(bus.results.value[SLOT].version).toBe('v1');
        expect(bus.loading.value[SLOT]).toBe(false);
    });

    it('echoes the last-seen version back so the server can short-circuit', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track(SLOT, { report: 'users' });
        await settle();

        bus.invalidate([SLOT]);
        await settle();

        expect(postMock).toHaveBeenCalledTimes(2);
        expect(postMock.mock.calls[1][1].versions).toEqual({ [SLOT]: 'v1' });
    });

    it('leaves an unchanged slot identical by reference and only bumps freshness', async () => {
        let now = 1_000;
        vi.spyOn(Date, 'now').mockImplementation(() => (now += 1_000));

        postMock
            .mockImplementationOnce(async (_url: string, body: any) => ok(Object.keys(body.widgets)))
            .mockImplementationOnce(async () => ({ data: { results: { [SLOT]: { unchanged: true, version: 'v1' } } } }));

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track(SLOT, { report: 'users' });
        await settle();

        const held = bus.results.value[SLOT];
        const before = bus.freshness.value[SLOT];

        bus.invalidate([SLOT]);
        await settle();

        expect(bus.results.value[SLOT]).toBe(held);
        expect(bus.freshness.value[SLOT]).toBeGreaterThan(before);
    });

    it('collapses thirty signals inside the coalesce window into ONE request', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: null, coalesceMs: 60 }))!;
        bus.track(SLOT, { report: 'users' });

        await settle();
        postMock.mockClear();

        // A burst inside the window is ONE refetch, not thirty.
        for (let i = 0; i < 30; i++) bus.signal(['users']);

        await new Promise(resolve => setTimeout(resolve, 120));
        await settle();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(bus.live.value[SLOT]).toBe(true);
    });

    it('issues nothing while the tab is hidden and exactly one request on resume', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        setVisibility('hidden');

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track('a', { report: 'users' });
        bus.track('b', { report: 'users' });

        await settle();

        expect(postMock).toHaveBeenCalledTimes(0);

        setVisibility('visible');
        await nextTick();
        await settle();

        expect(postMock).toHaveBeenCalledTimes(1);
    });

    it('starts NO timer when nothing declares a poll interval', async () => {
        const spy = vi.spyOn(globalThis, 'setInterval');
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track('a', { report: 'users' });
        bus.track('b', { report: 'users' });

        await settle();

        expect(spy).not.toHaveBeenCalled();

        bus.track('c', { report: 'users' }, { pollSeconds: 60 });
        expect(spy).toHaveBeenCalledTimes(1);

        spy.mockRestore();
    });

    it('errors only the failing slot and leaves its siblings rendered', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => {
            if (Object.keys(body.widgets).includes('bad')) throw new Error('boom');
            return ok(Object.keys(body.widgets));
        });

        const bus = scope.run(() => useDashboardBus({ channel: null, maxBatch: 1 }))!;
        bus.track('good', { report: 'users' });
        bus.track('bad', { report: 'users' });

        await settle(8);

        expect(bus.errored.value.bad).toBe('live.errors.slot');
        expect(bus.errored.value.good).toBeNull();
        expect(bus.results.value.good).toBeTruthy();
        expect(bus.results.value.bad).toBeUndefined();
    });

    it('leaves the channel when the last tracked slot goes away', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: 'myra.dashboard.1' }))!;
        bus.track('a', { report: 'users' });

        await nextTick();
        await settle();

        expect(privateMock).toHaveBeenCalledWith('myra.dashboard.1');

        bus.untrack('a');

        await nextTick();
        await settle();

        expect(leaveMock).toHaveBeenCalledWith('myra.dashboard.1');
    });

    it('never opens a second socket for a second widget', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: 'myra.dashboard.1' }))!;

        for (let i = 0; i < 20; i++) bus.track(`slot-${i}`, { report: 'users' });

        await nextTick();
        await settle();

        expect(privateMock).toHaveBeenCalledTimes(1);
    });

    it('ignores a signal whose topics match nothing we track', async () => {
        postMock.mockImplementation(async (_url: string, body: any) => ok(Object.keys(body.widgets)));

        const bus = scope.run(() => useDashboardBus({ channel: null }))!;
        bus.track(SLOT, { report: 'users' });
        await settle();
        postMock.mockClear();

        bus.signal(['orders']);
        await settle();

        expect(postMock).not.toHaveBeenCalled();
    });
});
