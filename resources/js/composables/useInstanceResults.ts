import { ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue';
import type { ReportResultPayload, StatResultPayload } from '@/components/admin/charts/types';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

/**
 * Results for catalogue-added tiles.
 *
 * StatWidget / ChartWidget / TableWidget are pure presentation: they read
 * `result` and never fetch, so a widget the user added at runtime has no data
 * unless someone asks for it. One batched POST to the EXISTING widgets endpoint
 * does that — the server re-parses every spec through ReportRequest::parse and
 * Gate, so nothing here is authority.
 *
 * Only a SERVER-resolved binding carries `report`, so a tile the user just added
 * is skipped until the save round-trip brings its resolved schema back. Every
 * failure is silent: a tile with no result renders its declared empty state.
 */
export interface UseInstanceResults {
    results: Ref<Record<string, ReportResultPayload | StatResultPayload>>;
    loading: Ref<Record<string, boolean>>;
    refresh(): Promise<void>;
}

export interface UseInstanceResultsOptions {
    widgets: MaybeRefOrGetter<WidgetSchema[]>;
    /** Injected for tests; defaults to Ziggy's global `route()`. */
    resolveRoute?: (name: string, params?: any) => string;
    /** Injected for tests; defaults to the global `fetch`. */
    request?: (url: string, init: RequestInit) => Promise<Response>;
    routeName?: string;
}

/** Binding keys ReportRequest::parse understands. Everything else is dropped. */
const SPEC_KEYS = ['dimension', 'bucket', 'measures', 'limit'] as const;

function csrfToken(): string {
    if (typeof document === 'undefined') return '';

    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function specOf(widget: WidgetSchema): Record<string, unknown> | null {
    const binding = (widget as any).binding as Record<string, unknown> | undefined;
    const report = binding?.report;

    if (typeof report !== 'string' || report === '') return null;

    const spec: Record<string, unknown> = { report };

    for (const name of SPEC_KEYS) {
        if (binding?.[name] !== undefined) spec[name] = binding[name];
    }

    if (widget.type === 'stat') spec.mode = 'stat';

    return spec;
}

export function useInstanceResults(options: UseInstanceResultsOptions): UseInstanceResults {
    const results = ref<Record<string, ReportResultPayload | StatResultPayload>>({});
    const loading = ref<Record<string, boolean>>({});
    const routeName = options.routeName ?? 'admin.reports.widgets';

    let seq = 0;

    function specs(): Record<string, Record<string, unknown>> {
        const out: Record<string, Record<string, unknown>> = {};

        for (const widget of toValue(options.widgets) ?? []) {
            const spec = specOf(widget);
            if (spec !== null) out[widget.key] = spec;
        }

        return out;
    }

    async function refresh(): Promise<void> {
        const wanted = specs();
        const keys = Object.keys(wanted);
        const run = ++seq;

        if (keys.length === 0) {
            results.value = {};
            loading.value = {};

            return;
        }

        loading.value = Object.fromEntries(keys.map(key => [key, true]));

        try {
            const resolveRoute = options.resolveRoute
                ?? ((name: string, params?: any) => (globalThis as any).route(name, params));
            const send = options.request
                ?? ((url: string, init: RequestInit) => (globalThis as any).fetch(url, init));

            const response = await send(resolveRoute(routeName), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ widgets: wanted }),
            });

            if (run !== seq || !response.ok) return;

            const body = await response.json();

            if (run !== seq) return;

            if (body?.results && typeof body.results === 'object') results.value = body.results;
        } catch {
            // Fail soft on purpose: no data is an empty state, never a broken page.
        } finally {
            if (run === seq) loading.value = {};
        }
    }

    watch(() => JSON.stringify(specs()), () => { void refresh(); }, { immediate: true });

    return { results, loading, refresh };
}
