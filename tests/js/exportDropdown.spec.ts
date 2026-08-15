import { describe, expect, it } from 'vitest';
import {
    buildExportHref,
    ClientExportTooLargeError,
    MAX_CLIENT_ROWS,
    useExcelExport,
} from '@/composables/useExcelExport';

const resolve = (name: string, params: Record<string, any>) =>
    `${name}?${new URLSearchParams(
        Object.entries(params).flatMap(([k, v]) =>
            Array.isArray(v) ? v.map((x, i) => [`${k}[${i}]`, String(x)] as [string, string]) : [[k, String(v)] as [string, string]],
        ),
    ).toString()}`;

describe('buildExportHref', () => {
    it('carries the current query string into the server export', () => {
        const href = buildExportHref({
            routeName: 'admin.users.export-csv',
            format: 'csv',
            columns: ['id', 'name'],
            search: '?status=suspended&search=ada',
            resolve,
        });

        expect(href).toContain('status=suspended');
        expect(href).toContain('search=ada');
        expect(href).toContain('format=csv');
        expect(href).toContain('columns%5B0%5D=id');
    });

    it('omits the filters when includeFilters is false', () => {
        const href = buildExportHref({
            routeName: 'admin.users.export-csv',
            format: 'csv',
            columns: ['id'],
            includeFilters: false,
            search: '?status=suspended',
            resolve,
        });

        expect(href).not.toContain('status=suspended');
        expect(href).toContain('format=csv');
    });

    it('returns null without a route name', () => {
        expect(buildExportHref({ format: 'csv', columns: [], resolve })).toBeNull();
    });

    it('keeps explicit route params', () => {
        const href = buildExportHref({
            routeName: 'admin.demo.export-csv',
            routeParams: { team: 7 },
            format: 'xlsx',
            columns: [],
            search: '',
            resolve,
        });

        expect(href).toContain('team=7');
        expect(href).toContain('format=xlsx');
    });
});

describe('client XLSX guard', () => {
    it('refuses above MAX_CLIENT_ROWS and points at the server route', async () => {
        const { exportToXlsx, exporting } = useExcelExport();
        const data = Array.from({ length: MAX_CLIENT_ROWS + 1 }, (_, i) => ({ id: i }));

        await expect(
            exportToXlsx({ filename: 'x', columns: [{ header: 'ID', key: 'id' }], data }),
        ).rejects.toBeInstanceOf(ClientExportTooLargeError);

        // The guard runs before any work, so the button never sticks in a loading state.
        expect(exporting.value).toBe(false);
    });

    it('exposes the limit on the error so the message can name it', async () => {
        const { exportToXlsx } = useExcelExport();
        const data = Array.from({ length: MAX_CLIENT_ROWS + 1 }, (_, i) => ({ id: i }));

        await exportToXlsx({ filename: 'x', columns: [], data }).catch((e: ClientExportTooLargeError) => {
            expect(e.max).toBe(MAX_CLIENT_ROWS);
            expect(e.rows).toBe(MAX_CLIENT_ROWS + 1);
        });
    });
});
