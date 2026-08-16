import { computed, ref, toValue, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { highlightRuns, matchOffsets, type HighlightRun, type SearchMatch } from './useGlobalSearch';
import type { DemoEntry } from '@/demo/registry';

export interface DemoSearchResult extends DemoEntry {
    title: string;
    description: string;
    /** Field-tagged offsets, fed straight to SearchHighlight.vue. */
    matches: SearchMatch[];
    runs: HighlightRun[];
    descriptionRuns: HighlightRun[];
}

/**
 * Local, synchronous search over the registry payload. Matches stay OFFSETS
 * rendered by SearchHighlight.vue — never pre-rendered HTML, never v-html.
 */
export function useDemoSearch(entries: MaybeRefOrGetter<DemoEntry[]>): {
    query: Ref<string>;
    tag: Ref<string | null>;
    tags: ComputedRef<Array<{ id: string; count: number }>>;
    results: ComputedRef<DemoSearchResult[]>;
} {
    const { t } = useI18n();
    const query = ref('');
    const tag = ref<string | null>(null);

    const all = computed<DemoEntry[]>(() => {
        const value = toValue(entries);
        return Array.isArray(value) ? value : [];
    });

    const translated = computed(() =>
        all.value.map(entry => ({
            ...entry,
            title: t(entry.titleKey),
            description: t(entry.descriptionKey),
        })),
    );

    const tags = computed(() => {
        const counts = new Map<string, number>();
        for (const entry of all.value) {
            for (const id of entry.tags ?? []) {
                counts.set(id, (counts.get(id) ?? 0) + 1);
            }
        }

        return [...counts.entries()]
            .map(([id, count]) => ({ id, count }))
            .sort((a, b) => b.count - a.count || a.id.localeCompare(b.id));
    });

    const results = computed<DemoSearchResult[]>(() => {
        const q = query.value.trim();
        const activeTag = tag.value;

        const out: DemoSearchResult[] = [];

        for (const entry of translated.value) {
            if (activeTag && !(entry.tags ?? []).includes(activeTag)) continue;

            let titleMatches: SearchMatch[] = [];
            let descriptionMatches: SearchMatch[] = [];

            if (q !== '') {
                titleMatches = matchOffsets(entry.title, q, 'title');
                descriptionMatches = matchOffsets(entry.description, q, 'description');

                const tagHit = (entry.tags ?? []).some(id => id.toLowerCase().includes(q.toLowerCase()));

                if (titleMatches.length === 0 && descriptionMatches.length === 0 && !tagHit) continue;
            }

            out.push({
                ...entry,
                matches: [...titleMatches, ...descriptionMatches],
                runs: highlightRuns(entry.title, titleMatches, 'title'),
                descriptionRuns: highlightRuns(entry.description, descriptionMatches, 'description'),
            });
        }

        return out;
    });

    return { query, tag, tags, results };
}
