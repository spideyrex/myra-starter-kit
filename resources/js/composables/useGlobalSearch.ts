import { computed, ref, watch } from 'vue';
import axios from 'axios';
import i18n from '@/i18n';

export interface SearchMatch {
    field: 'title' | 'description';
    start: number;
    length: number;
}

export interface SearchItem {
    id: number | string;
    title: string;
    description: string;
    url: string;
    score?: number;
    /** Offset pairs, never pre-rendered HTML. */
    matches?: SearchMatch[];
}

export interface SearchGroup {
    /** Translated heading. */
    group: string;
    key?: string;
    labelKey?: string;
    icon?: string | null;
    items: SearchItem[];
}

const RECENT_KEY = 'myra.search.recent';
const RECENT_MAX = 5;

function translate(key: string | undefined, fallback: string): string {
    if (!key) return fallback;
    try {
        const out = (i18n.global.t as (k: string) => string)(key);
        return !out || out === key ? fallback : out;
    } catch {
        return fallback;
    }
}

function readRecent(): SearchItem[] {
    try {
        const raw = localStorage.getItem(RECENT_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.slice(0, RECENT_MAX) : [];
    } catch {
        return [];
    }
}

function writeRecent(items: SearchItem[]): void {
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(items.slice(0, RECENT_MAX)));
    } catch {
        // Safari private mode.
    }
}

/**
 * Split `text` into runs so the caller can wrap matched runs in <mark>.
 * Text is never interpolated into HTML — this returns plain strings.
 */
export function highlightRuns(
    text: string,
    matches: SearchMatch[] | undefined,
    field: 'title' | 'description' = 'title',
): Array<{ text: string; mark: boolean }> {
    const ranges = (matches ?? [])
        .filter(m => m.field === field && m.length > 0 && m.start >= 0 && m.start < text.length)
        .sort((a, b) => a.start - b.start);

    if (ranges.length === 0) return [{ text, mark: false }];

    const runs: Array<{ text: string; mark: boolean }> = [];
    let cursor = 0;

    for (const range of ranges) {
        if (range.start < cursor) continue;
        if (range.start > cursor) runs.push({ text: text.slice(cursor, range.start), mark: false });
        const end = Math.min(text.length, range.start + range.length);
        runs.push({ text: text.slice(range.start, end), mark: true });
        cursor = end;
    }

    if (cursor < text.length) runs.push({ text: text.slice(cursor), mark: false });

    return runs.filter(r => r.text !== '');
}

export function useGlobalSearch() {
    const query = ref('');
    const results = ref<SearchGroup[]>([]);
    const loading = ref(false);
    const hasSearched = ref(false);
    const activeIndex = ref(0);
    const recent = ref<SearchItem[]>(readRecent());

    let debounceTimer: ReturnType<typeof setTimeout>;
    let controller: AbortController | null = null;
    let seq = 0;

    const flatItems = computed<SearchItem[]>(() => results.value.flatMap(g => g.items));

    watch(query, (val) => {
        clearTimeout(debounceTimer);
        // A slow early response must never overwrite a newer one.
        controller?.abort();

        if (val.length < 2) {
            results.value = [];
            hasSearched.value = false;
            loading.value = false;
            activeIndex.value = 0;
            return;
        }

        const mine = ++seq;
        controller = new AbortController();
        loading.value = true;

        debounceTimer = setTimeout(async () => {
            try {
                const res = await axios.get(route('admin.search'), {
                    params: { q: val },
                    signal: controller!.signal,
                });
                if (mine !== seq) return;
                results.value = (res.data.results ?? []).map((g: SearchGroup) => ({
                    ...g,
                    group: translate(g.labelKey, g.group),
                }));
                hasSearched.value = true;
                activeIndex.value = 0;
            } catch {
                if (mine === seq) results.value = [];
            } finally {
                if (mine === seq) loading.value = false;
            }
        }, 250);
    });

    function next() {
        const total = flatItems.value.length;
        if (total === 0) return;
        activeIndex.value = (activeIndex.value + 1) % total;
    }

    function prev() {
        const total = flatItems.value.length;
        if (total === 0) return;
        activeIndex.value = (activeIndex.value - 1 + total) % total;
    }

    function open(item?: SearchItem): SearchItem | null {
        const target = item ?? flatItems.value[activeIndex.value];
        if (!target) return null;

        recent.value = [target, ...recent.value.filter(r => r.url !== target.url)].slice(0, RECENT_MAX);
        writeRecent(recent.value);

        return target;
    }

    function clearRecent() {
        recent.value = [];
        writeRecent([]);
    }

    function reset() {
        clearTimeout(debounceTimer);
        controller?.abort();
        seq++;
        query.value = '';
        results.value = [];
        hasSearched.value = false;
        loading.value = false;
        activeIndex.value = 0;
    }

    return {
        query,
        results,
        loading,
        hasSearched,
        activeIndex,
        flatItems,
        recent,
        next,
        prev,
        open,
        clearRecent,
        reset,
    };
}
