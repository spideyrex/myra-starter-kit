import { ref, type Ref } from 'vue';
import type { QueryGroup } from '@/types/admin';
import type { TableViewPayload } from '@/types/table-views';
import type {
    Bucket,
    CompareMode,
    PeriodSelection,
    ReportResultPayload,
    ReportSchema,
    ReportState,
} from '@/types/reports';

export const REPORT_REFRESH_DEBOUNCE = 150;

export interface UseReportStateOptions {
    reportKey: string;
    schema: Ref<ReportSchema>;
    initial: ReportState;
    initialResult: ReportResultPayload;
    /** default true — the URL IS the share link */
    syncUrl?: boolean;
    /** Route name of the POST endpoint; overridable so the demo page can reuse it. */
    dataRoute?: string;
}

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value));
}

function csrfToken(): string {
    if (typeof document === 'undefined') return '';
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export function useReportState(opts: UseReportStateOptions) {
    const state = ref<ReportState>(clone(opts.initial));
    const result = ref<ReportResultPayload>(clone(opts.initialResult));
    const loading = ref(false);
    const error = ref<string | null>(null);

    const dataRoute = opts.dataRoute ?? 'admin.reports.data';
    const syncUrl = opts.syncUrl !== false;

    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | null = null;
    let seq = 0;

    function serialised(): string {
        return JSON.stringify(state.value);
    }

    function pushUrl(): void {
        if (!syncUrl || typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        url.searchParams.set('state', serialised());
        window.history.replaceState(window.history.state, '', url.toString());
    }

    /**
     * Debounced, cancelling, and — critically — a 422 leaves `result` intact.
     * A failed refine must never blank the chart.
     */
    function refresh(): Promise<void> {
        clearTimeout(timer);
        controller?.abort();

        return new Promise<void>((resolve) => {
            timer = setTimeout(async () => {
                const mine = ++seq;
                controller = new AbortController();
                loading.value = true;

                try {
                    const response = await fetch(route(dataRoute, opts.reportKey), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify({ state: state.value }),
                        signal: controller.signal,
                    });

                    if (mine !== seq) return;

                    if (!response.ok) {
                        error.value = await errorKey(response);
                        return;
                    }

                    result.value = await response.json();
                    error.value = null;
                    pushUrl();
                } catch (e: any) {
                    if (mine === seq && e?.name !== 'AbortError') error.value = 'reports.errors.failed';
                } finally {
                    if (mine === seq) loading.value = false;
                    resolve();
                }
            }, REPORT_REFRESH_DEBOUNCE);
        });
    }

    /** The server returns an i18n KEY, never a rendered sentence. */
    async function errorKey(response: Response): Promise<string> {
        try {
            const body = await response.json();
            const message = body?.message;
            return typeof message === 'string' && message !== '' ? message : 'reports.errors.failed';
        } catch {
            return 'reports.errors.failed';
        }
    }

    function mutate(fn: () => void): void {
        fn();
        void refresh();
    }

    function setPeriod(p: PeriodSelection): void {
        mutate(() => { state.value.period = clone(p); });
    }

    function setCompare(mode: CompareMode): void {
        mutate(() => { state.value.compare = mode; });
    }

    function setDimension(key: string): void {
        mutate(() => {
            state.value.dimension = key;
            // A bucket only means anything on a date dimension; carrying a stale
            // one across a switch is how a chart silently regroups itself.
            const next = opts.schema.value.dimensions.find(d => d.key === key);
            if (!next || next.type !== 'date') state.value.bucket = null;
        });
    }

    function setBucket(b: Bucket): void {
        mutate(() => { state.value.bucket = b; });
    }

    function toggleMeasure(key: string): void {
        mutate(() => {
            const current = state.value.measures ?? [];
            const next = current.includes(key)
                ? current.filter(m => m !== key)
                : [...current, key].slice(0, opts.schema.value.maxMeasures);

            // The server would fall back to the defaults on an empty set; keeping
            // the last measure selected makes that impossible to trip by accident.
            state.value.measures = next.length > 0 ? next : current;
        });
    }

    function setQuery(group: QueryGroup | null): void {
        mutate(() => { state.value.query = group ? clone(group) : null; });
    }

    function setChart(type: string): void {
        // Presentation only — no server round trip.
        state.value.chart = type;
        pushUrl();
    }

    function setLimit(n: number): void {
        mutate(() => { state.value.limit = n; });
    }

    /** Merge/remove a cross-filter fragment keyed by the emitting widget. */
    function setCross(sourceKey: string, group: QueryGroup | null): void {
        mutate(() => {
            const cross = { ...(state.value.cross ?? {}) };
            if (group) cross[sourceKey] = clone(group);
            else delete cross[sourceKey];
            state.value.cross = cross;
        });
    }

    function clearCross(sourceKey?: string): void {
        mutate(() => {
            if (!sourceKey) { state.value.cross = {}; return; }
            const cross = { ...(state.value.cross ?? {}) };
            delete cross[sourceKey];
            state.value.cross = cross;
        });
    }

    function exportHref(format: string): string {
        const params = new URLSearchParams({ format, state: serialised() });
        return `${route('admin.reports.export', opts.reportKey)}?${params.toString()}`;
    }

    /** A ReportState IS a TableViewPayload — useTableViews saves it unchanged. */
    function toPayload(): TableViewPayload {
        return clone(state.value) as unknown as TableViewPayload;
    }

    function applyPayload(payload: TableViewPayload): void {
        mutate(() => { state.value = clone(payload) as unknown as ReportState; });
    }

    function cancel(): void {
        clearTimeout(timer);
        controller?.abort();
        seq++;
        loading.value = false;
    }

    return {
        state,
        result,
        loading,
        error,
        setPeriod,
        setCompare,
        setDimension,
        setBucket,
        toggleMeasure,
        setQuery,
        setChart,
        setLimit,
        setCross,
        clearCross,
        refresh,
        exportHref,
        toPayload,
        applyPayload,
        cancel,
    };
}
