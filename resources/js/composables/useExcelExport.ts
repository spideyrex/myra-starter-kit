import { ref } from 'vue';

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
