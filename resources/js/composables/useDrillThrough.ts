import { router } from '@inertiajs/vue3';
import type { DrillTarget } from '@/types/report-delivery';

/**
 * The server built the url and the params; the client only navigates. The tree
 * inside `params` carries no authority — the landing controller re-parses it
 * through its own FieldSet, so a hand-edited drill URL is validated exactly
 * like a hand-typed filter.
 */
export function useDrillThrough() {
    function href(drill: DrillTarget): string {
        const query = new URLSearchParams(drill.params).toString();

        return query ? `${drill.url}?${query}` : drill.url;
    }

    function go(drill: DrillTarget | null, mode: 'navigate' | 'newTab' = 'navigate'): void {
        if (!drill) return;

        if (mode === 'newTab') {
            if (typeof window !== 'undefined') window.open(href(drill), '_blank', 'noopener');
            return;
        }

        router.get(drill.url, drill.params, { preserveState: false, preserveScroll: false });
    }

    return { go, href };
}
