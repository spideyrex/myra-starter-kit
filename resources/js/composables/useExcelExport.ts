import { ref } from 'vue';

/**
 * Hard ceiling for the browser-side XLSX path. Building a workbook in memory
 * is not a substitute for the streamed server export; above this the caller is
 * pointed at the server route instead of being handed a truncated file.
 */
export const MAX_CLIENT_ROWS = 5000;

export class ClientExportTooLargeError extends Error {
    constructor(public readonly rows: number, public readonly max: number = MAX_CLIENT_ROWS) {
        super('client-export-too-large');
        this.name = 'ClientExportTooLargeError';
    }
}

export interface ExportHrefOptions {
    routeName?: string;
    routeParams?: Record<string, any>;
    format: 'csv' | 'xlsx';
    columns: string[];
    /** Carry the page's active filters into the export. Default true. */
    includeFilters?: boolean;
    /** Query string to read; defaults to window.location.search. */
    search?: string;
    resolve: (name: string, params: Record<string, any>) => string;
}

/**
 * Server-export href. The pre-v2.2 ExportDropdown took a pre-built href that
 * never carried the active filters, so "Export CSV" silently exported the whole
 * table. Reading the query string here keeps DataTable out of it.
 */
export function buildExportHref(options: ExportHrefOptions): string | null {
    if (!options.routeName) return null;

    const raw =
        options.includeFilters === false
            ? ''
            : options.search ?? (typeof window !== 'undefined' ? window.location.search : '');

    const filters = Object.fromEntries(new URLSearchParams(raw));

    return options.resolve(options.routeName, {
        ...(options.routeParams ?? {}),
        ...filters,
        format: options.format,
        columns: options.columns,
    });
}

export interface ExcelColumn {
    header: string;
    key: string;
    width?: number;
}

export interface ExcelExportOptions {
    filename: string;
    sheetName?: string;
    columns: ExcelColumn[];
    data: Record<string, any>[];
    headerStyle?: {
        bgColor?: string;
        fontColor?: string;
        bold?: boolean;
    };
}

export function useExcelExport() {
    const exporting = ref(false);

    async function exportToXlsx(options: ExcelExportOptions) {
        if (options.data.length > MAX_CLIENT_ROWS) {
            throw new ClientExportTooLargeError(options.data.length);
        }

        exporting.value = true;
        try {
            const [ExcelJS, { saveAs }] = await Promise.all([
                import('exceljs'),
                import('file-saver'),
            ]);

            const workbook = new ExcelJS.Workbook();
            const sheet = workbook.addWorksheet(options.sheetName || 'Sheet1');

            // Set columns
            sheet.columns = options.columns.map(col => ({
                header: col.header,
                key: col.key,
                width: col.width || Math.max(col.header.length + 4, 15),
            }));

            // Add data rows
            for (const row of options.data) {
                sheet.addRow(row);
            }

            // Style header row
            const headerRow = sheet.getRow(1);
            const style = options.headerStyle || {};
            headerRow.eachCell(cell => {
                cell.font = {
                    bold: style.bold !== false,
                    color: { argb: (style.fontColor || 'FFFFFF').replace('#', '') },
                };
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: (style.bgColor || '4F46E5').replace('#', '') },
                };
                cell.alignment = { vertical: 'middle', horizontal: 'left' };
                cell.border = {
                    bottom: { style: 'thin', color: { argb: 'D1D5DB' } },
                };
            });

            // Auto-fit columns based on content
            sheet.columns.forEach(column => {
                if (!column || !column.eachCell) return;
                let maxLength = (column.header as string)?.length || 10;
                column.eachCell({ includeEmpty: false }, cell => {
                    const cellLength = cell.value ? String(cell.value).length : 0;
                    maxLength = Math.max(maxLength, cellLength);
                });
                column.width = Math.min(maxLength + 3, 50);
            });

            // Generate buffer and save
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            });
            const fname = options.filename.endsWith('.xlsx') ? options.filename : `${options.filename}.xlsx`;
            saveAs(blob, fname);
        } finally {
            exporting.value = false;
        }
    }

    return { exportToXlsx, exporting };
}
