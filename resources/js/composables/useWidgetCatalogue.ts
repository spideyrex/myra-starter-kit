import { computed, ref, toValue, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';
import type { ChartType, ColSpan, ReportBinding, WidgetType } from '@/composables/useDashboardWidgets';

export interface CatalogueEntry {
    key: string;
    type: WidgetType;
    titleKey: string;
    descriptionKey?: string;
    categoryKey: string;
    icon: string;
    tags: string[];
    dimensions: string[];
    measures: string[];
    chartTypes: ChartType[];
    defaults?: ReportBinding;
    defaultColSpan?: ColSpan;
    maxInstances: number;
}

export type CatalogueResult = CatalogueEntry & { disabled: boolean; used: number };

/**
 * Browse the SERVER-filtered catalogue. Nothing here decides visibility — an
 * entry the actor may not add never reaches the client at all.
 */
export function useWidgetCatalogue(options: {
    entries: MaybeRefOrGetter<CatalogueEntry[]>;
    used: MaybeRefOrGetter<string[]>;
    /** Already-bound translator so search matches what the user actually reads. */
    translate?: (key: string) => string;
}): {
    query: Ref<string>;
    category: Ref<string | null>;
    categories: ComputedRef<Array<{ id: string; count: number }>>;
    results: ComputedRef<CatalogueResult[]>;
    atLimit: ComputedRef<boolean>;
} {
    const query = ref('');
    const category = ref<string | null>(null);

    const entries = computed<CatalogueEntry[]>(() => toValue(options.entries) ?? []);
    const used = computed<string[]>(() => toValue(options.used) ?? []);

    const usage = computed(() => {
        const counts = new Map<string, number>();

        for (const key of used.value) {
            counts.set(key, (counts.get(key) ?? 0) + 1);
        }

        return counts;
    });

    const categories = computed(() => {
        const counts = new Map<string, number>();

        for (const entry of entries.value) {
            counts.set(entry.categoryKey, (counts.get(entry.categoryKey) ?? 0) + 1);
        }

        return [...counts.entries()].map(([id, count]) => ({ id, count }));
    });

    const atLimit = computed(() => used.value.length >= MAX_INSTANCES);

    const results = computed<CatalogueResult[]>(() => {
        const needle = query.value.trim().toLowerCase();

        return entries.value
            .filter(entry => category.value === null || entry.categoryKey === category.value)
            .filter(entry => needle === '' || matches(entry, needle, options.translate))
            .map(entry => {
                const count = usage.value.get(entry.key) ?? 0;

                return {
                    ...entry,
                    used: count,
                    disabled: atLimit.value || count >= (entry.maxInstances ?? 1),
                };
            });
    });

    return { query, category, categories, results, atLimit };
}

/** Mirrors LayoutShape::MAX_INSTANCES; the server is still the authority. */
const MAX_INSTANCES = 12;

function matches(entry: CatalogueEntry, needle: string, translate?: (key: string) => string): boolean {
    const label = translate ? translate(entry.titleKey) : '';
    const description = translate && entry.descriptionKey ? translate(entry.descriptionKey) : '';

    const haystack = [entry.key, entry.titleKey, entry.descriptionKey ?? '', label, description, ...entry.tags]
        .join(' ')
        .toLowerCase();

    return haystack.includes(needle);
}
