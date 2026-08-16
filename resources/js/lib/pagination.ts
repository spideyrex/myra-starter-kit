import type { CursorPaginatedData, PaginatedData, TableData } from '@/types';

/**
 * The one runtime discriminator for the two table payloads. It lives here rather
 * than in types/index.d.ts because a .d.ts emits no JavaScript.
 */
export function isCursor<T>(d: TableData<T> | null | undefined): d is CursorPaginatedData<T> {
    return (d as any)?.meta?.mode === 'cursor';
}

export function asLengthAware<T>(d: TableData<T> | null | undefined): PaginatedData<T> | null {
    return d && !isCursor(d) ? (d as PaginatedData<T>) : null;
}

export function asCursor<T>(d: TableData<T> | null | undefined): CursorPaginatedData<T> | null {
    return isCursor(d) ? d : null;
}
