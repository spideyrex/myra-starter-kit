<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    buildExportHref,
    ClientExportTooLargeError,
    useExcelExport,
    type ExcelColumn,
} from '@/composables/useExcelExport';
import { Columns3, Download, FileSpreadsheet, FileText, Loader2 } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        /** Ziggy route NAME of the streaming server export (not an href). */
        csvRoute?: string;
        routeParams?: Record<string, any>;
        formats?: Array<'csv' | 'xlsx'>;
        columns: ExcelColumn[];
        data: Record<string, any>[];
        /** Carry the page's current query string into the export. */
        includeFilters?: boolean;
        xlsxFilename?: string;
        xlsxSheetName?: string;
    }>(),
    {
        formats: () => ['csv', 'xlsx'],
        includeFilters: true,
    },
);

const { t } = useI18n();
const { exportToXlsx, exporting } = useExcelExport();

const picked = ref<string[]>(props.columns.map(c => c.key));
const error = ref('');

function hrefFor(format: 'csv' | 'xlsx'): string | null {
    return buildExportHref({
        routeName: props.csvRoute,
        routeParams: props.routeParams,
        format,
        columns: picked.value,
        includeFilters: props.includeFilters,
        resolve: (name, params) => route(name, params),
    });
}

const csvHref = computed(() => hrefFor('csv'));
const serverXlsxHref = computed(() => (props.formats.includes('xlsx') ? hrefFor('xlsx') : null));

const pickedColumns = computed(() => props.columns.filter(c => picked.value.includes(c.key)));

function toggleColumn(key: string, on: boolean) {
    picked.value = on ? [...new Set([...picked.value, key])] : picked.value.filter(k => k !== key);
}

function selectAll() {
    picked.value = props.columns.map(c => c.key);
}

function selectNone() {
    picked.value = [];
}

async function handleClientXlsx() {
    error.value = '';
    try {
        await exportToXlsx({
            filename: props.xlsxFilename || 'export',
            sheetName: props.xlsxSheetName,
            columns: pickedColumns.value.length ? pickedColumns.value : props.columns,
            data: props.data,
        });
    } catch (e: any) {
        error.value =
            e instanceof ClientExportTooLargeError
                ? t('transfer.export.tooManyRows', { max: e.max })
                : t('transfer.export.tooManyRows', { max: 5000 });
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Popover v-if="columns.length">
            <PopoverTrigger as-child>
                <Button
                    variant="outline"
                    size="icon"
                    class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :aria-label="t('transfer.export.columns')"
                >
                    <Columns3 class="size-4" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-[min(20rem,calc(100vw-2rem))] p-3">
                <fieldset class="space-y-2">
                    <legend class="sr-only">{{ t('transfer.export.columns') }}</legend>
                    <div class="mb-2 flex gap-2">
                        <Button variant="ghost" size="sm" @click="selectAll">{{ t('transfer.export.selectAll') }}</Button>
                        <Button variant="ghost" size="sm" @click="selectNone">{{ t('transfer.export.selectNone') }}</Button>
                    </div>
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <div v-for="col in columns" :key="col.key" class="flex items-center gap-2">
                            <Checkbox
                                :id="`exp-col-${col.key}`"
                                :model-value="picked.includes(col.key)"
                                @update:model-value="(v: any) => toggleColumn(col.key, !!v)"
                            />
                            <Label :for="`exp-col-${col.key}`" class="cursor-pointer text-sm font-normal">
                                {{ col.header }}
                            </Label>
                        </div>
                    </div>
                    <p class="pt-2 text-xs text-muted-foreground">{{ t('transfer.export.keyOrder') }}</p>
                </fieldset>
            </PopoverContent>
        </Popover>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    :disabled="exporting"
                    class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Loader2 v-if="exporting" class="mr-2 size-4 animate-spin" />
                    <Download v-else class="mr-2 size-4" />
                    {{ t('transfer.export.button') }}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem v-if="csvHref" as="a" :href="csvHref">
                    <FileText class="mr-2 size-4" />
                    {{ t('transfer.export.csv') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="serverXlsxHref" as="a" :href="serverXlsxHref">
                    <FileSpreadsheet class="mr-2 size-4" />
                    {{ t('transfer.export.xlsx') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="csvHref" />
                <DropdownMenuItem @click="handleClientXlsx">
                    <FileSpreadsheet class="mr-2 size-4" />
                    {{ t('transfer.export.xlsxPageOnly') }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <p v-if="exporting" role="status" aria-live="polite" class="sr-only">
            {{ t('transfer.export.preparing') }}
        </p>
        <p v-if="error" role="alert" aria-live="assertive" class="text-xs text-destructive">
            {{ error }}
        </p>
    </div>
</template>
