import { computed, ref } from 'vue';

export interface ImportColumnSchema {
    name: string;
    label: string;
    required?: boolean;
    example?: string | null;
}

export interface ImportRow {
    line: number;
    values: Record<string, any>;
    errors: Record<string, string[]>;
}

export interface CommitResponse {
    imported: number;
    updated: number;
    failed: number;
    next_offset: number;
    next_line: number;
    total_rows: number;
    done: boolean;
    status?: 'running' | 'done' | 'aborted';
    failuresUrl?: string | null;
}

export type CommitFn = (cursor: { offset: number; line: number }) => Promise<CommitResponse>;

/**
 * Drives the resumable commit loop: one chunk per request, advancing on the
 * byte offset the server returns. A failed chunk leaves the cursor where it
 * stopped, so Resume continues rather than restarting from 0.
 */
export function useImportRunner(commit: CommitFn) {
    const offset = ref(0);
    const line = ref(1);
    const imported = ref(0);
    const updated = ref(0);
    const failed = ref(0);
    const totalRows = ref(0);
    const processed = ref(0);
    const done = ref(false);
    const running = ref(false);
    const error = ref('');
    const status = ref<'idle' | 'running' | 'done' | 'aborted'>('idle');
    const failuresUrl = ref<string | null>(null);

    async function run(): Promise<void> {
        if (running.value || done.value) return;

        running.value = true;
        error.value = '';
        status.value = 'running';

        try {
            while (!done.value) {
                const res = await commit({ offset: offset.value, line: line.value });

                imported.value += res.imported;
                updated.value += res.updated;
                failed.value += res.failed;
                processed.value = imported.value + updated.value + failed.value;
                if (res.total_rows) totalRows.value = res.total_rows;
                if (res.failuresUrl) failuresUrl.value = res.failuresUrl;

                offset.value = res.next_offset;
                line.value = res.next_line;

                if (res.status === 'aborted') {
                    status.value = 'aborted';
                    done.value = true;
                    break;
                }

                if (res.done) {
                    status.value = 'done';
                    done.value = true;
                    break;
                }
            }
        } catch (e: any) {
            error.value = e?.response?.data?.message || e?.message || 'error';
            status.value = 'idle';
        } finally {
            running.value = false;
        }
    }

    /** Only offered after a failure; the cursor is untouched so no row is re-imported. */
    const canResume = computed(() => !!error.value && !done.value && offset.value > 0);

    const progress = computed(() =>
        totalRows.value > 0 ? Math.min(100, Math.round((processed.value / totalRows.value) * 100)) : 0,
    );

    function reset() {
        offset.value = 0;
        line.value = 1;
        imported.value = 0;
        updated.value = 0;
        failed.value = 0;
        processed.value = 0;
        totalRows.value = 0;
        done.value = false;
        running.value = false;
        error.value = '';
        status.value = 'idle';
        failuresUrl.value = null;
    }

    return {
        run,
        reset,
        offset,
        line,
        imported,
        updated,
        failed,
        totalRows,
        processed,
        progress,
        done,
        running,
        error,
        canResume,
        status,
        failuresUrl,
    };
}

/** The commit button stays disabled while cells are invalid, unless the user opts to skip them. */
export function canCommit(invalidRows: number, skipInvalid: boolean): boolean {
    return invalidRows === 0 || skipInvalid;
}
