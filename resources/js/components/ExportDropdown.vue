<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useExcelExport, type ExcelColumn } from '@/composables/useExcelExport';
import { Download, FileSpreadsheet, FileText, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    csvHref?: string;
    xlsxFilename?: string;
    xlsxSheetName?: string;
    columns: ExcelColumn[];
    data: Record<string, any>[];
}>();

const { exportToXlsx, exporting } = useExcelExport();

function handleXlsxExport() {
    exportToXlsx({
        filename: props.xlsxFilename || 'export',
        sheetName: props.xlsxSheetName,
        columns: props.columns,
        data: props.data,
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" :disabled="exporting">
                <Loader2 v-if="exporting" class="mr-2 size-4 animate-spin" />
                <Download v-else class="mr-2 size-4" />
                Export
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-if="csvHref" as="a" :href="csvHref">
                <FileText class="mr-2 size-4" />
                Export CSV
            </DropdownMenuItem>
            <DropdownMenuItem @click="handleXlsxExport">
                <FileSpreadsheet class="mr-2 size-4" />
                Export XLSX
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
