import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import axios from 'axios';
import { useGlobalSearch } from '@/composables/useGlobalSearch';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const get = axios.get as unknown as ReturnType<typeof vi.fn>;

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((r) => { resolve = r; });
    return { promise, resolve };
}

function group(name: string, ids: number[]) {
    return {
        key: name,
        labelKey: `search.groups.${name}`,
        group: name,
        items: ids.map(id => ({ id, title: `T${id}`, description: '', url: `/x/${id}` })),
    };
}

beforeAll(() => {
    (globalThis as any).route = () => '/admin/search';
});

beforeEach(() => {
    vi.useFakeTimers();
    get.mockReset();
    localStorage.clear();
});

afterEach(() => {
    vi.useRealTimers();
});

async function typeInto(query: { value: string }, term: string) {
    query.value = term;
    await nextTick();
}

/** Drain the microtask queue so a resolved axios mock lands. */
async function flush() {
    for (let i = 0; i < 5; i++) {
        await Promise.resolve();
        await nextTick();
    }
}

describe('useGlobalSearch race safety', () => {
    it('does not let a slow early response overwrite a newer one', async () => {
        const slow = deferred<any>();
        const fast = deferred<any>();
        get.mockReturnValueOnce(slow.promise).mockReturnValueOnce(fast.promise);

        const { query, results } = useGlobalSearch();

        await typeInto(query, 'ab');
        vi.advanceTimersByTime(250);
        await nextTick();

        await typeInto(query, 'abc');
        vi.advanceTimersByTime(250);
        await nextTick();

        fast.resolve({ data: { results: [group('roles', [2])] } });
        await flush();

        slow.resolve({ data: { results: [group('users', [1])] } });
        await flush();

        expect(results.value).toHaveLength(1);
        expect(results.value[0].items[0].id).toBe(2);
    });

    it('aborts the in-flight request on each new keystroke', async () => {
        const abort = vi.spyOn(AbortController.prototype, 'abort');
        get.mockResolvedValue({ data: { results: [] } });

        const { query } = useGlobalSearch();

        await typeInto(query, 'ab');
        vi.advanceTimersByTime(250);
        await nextTick();

        const before = abort.mock.calls.length;
        await typeInto(query, 'abc');

        expect(abort.mock.calls.length).toBeGreaterThan(before);
        abort.mockRestore();
    });

    it('clears loading and results for a sub-2-character term', async () => {
        get.mockResolvedValue({ data: { results: [group('users', [1])] } });

        const { query, results, loading, hasSearched } = useGlobalSearch();

        await typeInto(query, 'ab');
        vi.advanceTimersByTime(250);
        await flush();

        await typeInto(query, 'a');

        expect(loading.value).toBe(false);
        expect(results.value).toEqual([]);
        expect(hasSearched.value).toBe(false);
    });
});

describe('useGlobalSearch keyboard navigation', () => {
    async function seeded() {
        get.mockResolvedValue({ data: { results: [group('users', [1, 2]), group('roles', [3])] } });
        const search = useGlobalSearch();

        await typeInto(search.query, 'abc');
        vi.advanceTimersByTime(250);
        await flush();

        return search;
    }

    it('wraps next() and prev() over the flattened list', async () => {
        const { activeIndex, flatItems, next, prev } = await seeded();

        expect(flatItems.value).toHaveLength(3);
        expect(activeIndex.value).toBe(0);

        next(); next(); next();
        expect(activeIndex.value).toBe(0);

        prev();
        expect(activeIndex.value).toBe(2);
    });

    it('translates the group heading from labelKey', async () => {
        const { results } = await seeded();
        expect(results.value[0].group).toBe('Users');
    });

    it('records opened items as recent, most recent first and de-duplicated', async () => {
        const { flatItems, open, recent } = await seeded();

        open(flatItems.value[0]);
        open(flatItems.value[1]);
        open(flatItems.value[0]);

        expect(recent.value.map(r => r.id)).toEqual([1, 2]);
        expect(JSON.parse(localStorage.getItem('myra.search.recent')!)[0].id).toBe(1);
    });
});
