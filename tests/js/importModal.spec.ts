import { describe, expect, it, vi } from 'vitest';
import {
    canCommit,
    useImportRunner,
    type CommitResponse,
} from '@/composables/useImportRunner';

function chunk(partial: Partial<CommitResponse>): CommitResponse {
    return {
        imported: 0,
        updated: 0,
        failed: 0,
        next_offset: 0,
        next_line: 1,
        total_rows: 0,
        done: false,
        status: 'running',
        failuresUrl: null,
        ...partial,
    };
}

describe('useImportRunner', () => {
    it('advances on next_offset and stops on done', async () => {
        const commit = vi
            .fn()
            .mockResolvedValueOnce(chunk({ imported: 2, next_offset: 40, next_line: 3, total_rows: 5 }))
            .mockResolvedValueOnce(chunk({ imported: 2, next_offset: 80, next_line: 5, total_rows: 5 }))
            .mockResolvedValueOnce(
                chunk({ imported: 1, next_offset: 100, next_line: 6, total_rows: 5, done: true, status: 'done' }),
            );

        const runner = useImportRunner(commit);
        await runner.run();

        expect(commit).toHaveBeenCalledTimes(3);
        expect(commit.mock.calls.map(c => c[0].offset)).toEqual([0, 40, 80]);
        expect(runner.imported.value).toBe(5);
        expect(runner.done.value).toBe(true);
        expect(runner.status.value).toBe('done');
        expect(runner.progress.value).toBe(100);
    });

    it('stops on an aborted status without waiting for done', async () => {
        const commit = vi.fn().mockResolvedValue(
            chunk({ failed: 3, next_offset: 20, total_rows: 100, status: 'aborted', failuresUrl: '/f.csv' }),
        );

        const runner = useImportRunner(commit);
        await runner.run();

        expect(commit).toHaveBeenCalledTimes(1);
        expect(runner.status.value).toBe('aborted');
        expect(runner.done.value).toBe(true);
        expect(runner.failuresUrl.value).toBe('/f.csv');
    });

    it('surfaces Resume after a failed chunk without restarting from zero', async () => {
        const commit = vi
            .fn()
            .mockResolvedValueOnce(chunk({ imported: 2, next_offset: 40, next_line: 3, total_rows: 5 }))
            .mockRejectedValueOnce(new Error('network'))
            .mockResolvedValueOnce(
                chunk({ imported: 3, next_offset: 90, next_line: 6, total_rows: 5, done: true, status: 'done' }),
            );

        const runner = useImportRunner(commit);
        await runner.run();

        expect(runner.error.value).toBe('network');
        expect(runner.offset.value).toBe(40);
        expect(runner.canResume.value).toBe(true);
        expect(runner.done.value).toBe(false);

        await runner.run();

        expect(commit.mock.calls[2][0].offset).toBe(40);
        expect(runner.imported.value).toBe(5);
        expect(runner.done.value).toBe(true);
        expect(runner.canResume.value).toBe(false);
    });

    it('reset clears the cursor and the tallies', async () => {
        const commit = vi.fn().mockResolvedValue(
            chunk({ imported: 1, next_offset: 10, total_rows: 1, done: true, status: 'done' }),
        );

        const runner = useImportRunner(commit);
        await runner.run();
        runner.reset();

        expect(runner.offset.value).toBe(0);
        expect(runner.imported.value).toBe(0);
        expect(runner.done.value).toBe(false);
        expect(runner.status.value).toBe('idle');
    });
});

describe('canCommit', () => {
    it('is false while any cell error remains, unless invalid rows are skipped', () => {
        expect(canCommit(0, false)).toBe(true);
        expect(canCommit(3, false)).toBe(false);
        expect(canCommit(3, true)).toBe(true);
    });
});
