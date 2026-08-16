/**
 * Client-side coercion for section data.
 *
 * The server normaliser already guarantees the shape; these keep the legacy
 * renderers safe when a block arrives from anywhere else (a preview payload,
 * a test, a future package) so `plan.features.split` can never throw.
 */
export function str(value: unknown): string {
    return typeof value === 'string' ? value : typeof value === 'number' ? String(value) : '';
}

export function bool(value: unknown): boolean {
    return value === true || value === 'true' || value === 1 || value === '1';
}

export function rows(value: unknown): Record<string, unknown>[] {
    return Array.isArray(value)
        ? (value.filter(row => row !== null && typeof row === 'object' && !Array.isArray(row)) as Record<
              string,
              unknown
          >[])
        : [];
}

export function nullableUrl(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}
