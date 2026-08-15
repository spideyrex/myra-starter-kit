<script setup lang="ts">
import { computed, ref } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import ImportErrorGrid from '@/components/admin/ImportErrorGrid.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AlertCircle, ArrowLeft, ArrowRight, CheckCircle2, Download, Upload } from 'lucide-vue-next';
import {
    canCommit,
    importErrorMessage,
    useImportRunner,
    type CommitResponse,
    type ImportColumnSchema,
    type ImportRow,
} from '@/composables/useImportRunner';

const props = defineProps<{
    open: boolean;
    title: string;
    /** Registry key resolved by ImportController (config myra.imports.resources). */
    resource: string;
    /** Optional link to a sample CSV. */
    sampleHref?: string;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

type Step = 'upload' | 'map' | 'validate' | 'import' | 'result';

const step = ref<Step>('upload');
const file = ref<File | null>(null);
const loading = ref(false);
const errorMessage = ref('');

const token = ref('');
const headers = ref<string[]>([]);
const columns = ref<ImportColumnSchema[]>([]);
const mapping = ref<Record<string, string>>({});
const rows = ref<ImportRow[]>([]);
const invalidRows = ref(0);
const totalRows = ref(0);
const skipInvalid = ref(false);

const SKIP = '__skip__';

const runner = useImportRunner(
    async ({ offset, line }) => {
        const { data } = await axios.post<CommitResponse>(
            route('admin.import.commit', { resource: props.resource }),
            { token: token.value, offset, line, total_rows: totalRows.value, skip_invalid: skipInvalid.value },
        );
        return data;
    },
    { fallback: () => t('transfer.import.failed') },
);

function handleFileChange(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] || null;
}

function messageOf(e: any, fallback: string): string {
    return importErrorMessage(e, () => e?.response?.data?.error || fallback);
}

async function uploadAndPreview() {
    if (!file.value) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const form = new FormData();
        form.append('file', file.value);

        const { data } = await axios.post(route('admin.import.preview', { resource: props.resource }), form);

        token.value = data.token;
        headers.value = data.headers;
        columns.value = data.columns;
        mapping.value = data.mapping;
        totalRows.value = data.totalRows;
        step.value = 'map';
    } catch (e: any) {
        errorMessage.value = messageOf(e, t('transfer.import.badFile'));
    } finally {
        loading.value = false;
    }
}

const mappedCount = computed(() => Object.values(mapping.value).filter(v => v && v !== SKIP).length);

const requiredMapped = computed(() =>
    columns.value.filter(c => c.required).every(c => !!mapping.value[c.name] && mapping.value[c.name] !== SKIP),
);

function payloadMapping(): Record<string, string> {
    return Object.fromEntries(
        Object.entries(mapping.value).map(([k, v]) => [k, v === SKIP ? '' : v]),
    );
}

async function validateRows() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.post(route('admin.import.validate', { resource: props.resource }), {
            token: token.value,
            mapping: payloadMapping(),
            limit: 50,
        });

        rows.value = data.rows;
        invalidRows.value = data.invalidRows;
        totalRows.value = data.totalRows;
        step.value = 'validate';
    } catch (e: any) {
        errorMessage.value = messageOf(e, t('transfer.import.mappingRequired'));
    } finally {
        loading.value = false;
    }
}

const commitEnabled = computed(() => canCommit(invalidRows.value, skipInvalid.value));

async function startImport() {
    step.value = 'import';
    await runner.run();
    if (runner.done.value) step.value = 'result';
}

async function resumeImport() {
    await runner.run();
    if (runner.done.value) step.value = 'result';
}

function close() {
    emit('update:open', false);
    setTimeout(() => {
        step.value = 'upload';
        file.value = null;
        token.value = '';
        headers.value = [];
        columns.value = [];
        mapping.value = {};
        rows.value = [];
        invalidRows.value = 0;
        totalRows.value = 0;
        skipInvalid.value = false;
        errorMessage.value = '';
        runner.reset();
    }, 200);
}

function finish() {
    close();
    router.reload();
}
</script>

<template>
    <Modal :open="open" :title="title" @update:open="close">
        <!-- 1. Upload -->
        <div v-if="step === 'upload'" class="space-y-4">
            <div>
                <Label for="import-file">{{ t('transfer.import.file') }}</Label>
                <input
                    id="import-file"
                    type="file"
                    accept=".csv,.txt"
                    class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    @change="handleFileChange"
                />
            </div>
            <p v-if="sampleHref" class="text-xs">
                <a :href="sampleHref" class="underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                    {{ t('transfer.import.downloadSample') }}
                </a>
            </p>
        </div>

        <!-- 2. Map -->
        <div v-else-if="step === 'map'" class="space-y-4">
            <p class="text-sm text-muted-foreground">{{ t('transfer.import.map') }}</p>
            <fieldset class="space-y-3">
                <legend class="sr-only">{{ t('transfer.import.map') }}</legend>
                <div
                    v-for="col in columns"
                    :key="col.name"
                    class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3"
                >
                    <Label :for="`map-${col.name}`" class="text-sm font-medium sm:w-32 sm:shrink-0 sm:text-right">
                        {{ col.label }}
                        <span v-if="col.required" class="text-destructive">*</span>
                    </Label>
                    <ArrowLeft class="hidden size-4 shrink-0 text-muted-foreground sm:block" aria-hidden="true" />
                    <Select
                        :model-value="mapping[col.name] || SKIP"
                        @update:model-value="(v: any) => (mapping[col.name] = v)"
                    >
                        <SelectTrigger :id="`map-${col.name}`" class="w-full sm:flex-1">
                            <SelectValue :placeholder="t('transfer.import.sourceColumn')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="SKIP">{{ t('transfer.import.skipColumn') }}</SelectItem>
                            <SelectItem v-for="h in headers" :key="h" :value="h">{{ h }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </fieldset>
            <p class="text-xs text-muted-foreground">
                {{ t('transfer.import.columnsMapped', { mapped: mappedCount, total: columns.length }) }}
            </p>
        </div>

        <!-- 3. Validate -->
        <div v-else-if="step === 'validate'" class="space-y-4">
            <p class="text-sm text-muted-foreground">
                {{ t('transfer.import.progress', { done: rows.length, total: totalRows }) }}
            </p>
            <p v-if="invalidRows > 0" class="flex items-center gap-2 text-sm text-destructive">
                <AlertCircle class="size-4" aria-hidden="true" />
                {{ t('transfer.import.invalidRows', { count: invalidRows }) }}
            </p>
            <ImportErrorGrid :columns="columns" :rows="rows" />
            <div v-if="invalidRows > 0" class="flex items-center gap-2">
                <Checkbox id="skip-invalid" v-model="skipInvalid" />
                <Label for="skip-invalid" class="cursor-pointer text-sm font-normal">
                    {{ t('transfer.import.skip') }}
                </Label>
            </div>
        </div>

        <!-- 4. Import -->
        <div v-else-if="step === 'import'" class="space-y-4">
            <!-- ProgressRoot already renders role="progressbar" + aria-valuenow/min/max. -->
            <Progress
                :model-value="runner.progress.value"
                :max="100"
                :aria-label="t('transfer.import.a11y.progress')"
            />
            <p aria-live="polite" aria-atomic="true" class="text-sm text-muted-foreground">
                {{ t('transfer.import.progress', { done: runner.processed.value, total: runner.totalRows.value || totalRows }) }}
            </p>
            <Alert v-if="runner.error.value" variant="destructive" aria-live="assertive">
                <AlertCircle class="size-4" />
                <AlertDescription>{{ runner.error.value }}</AlertDescription>
            </Alert>
        </div>

        <!-- 5. Result -->
        <div v-else-if="step === 'result'" class="space-y-4">
            <div class="flex items-center gap-3">
                <CheckCircle2 v-if="runner.status.value === 'done'" class="size-8 text-success" aria-hidden="true" />
                <AlertCircle v-else class="size-8 text-destructive" aria-hidden="true" />
                <div>
                    <p class="font-medium">
                        {{ runner.status.value === 'aborted' ? t('transfer.import.aborted') : t('transfer.import.done') }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ t('transfer.import.rowsImported', {
                            imported: runner.imported.value,
                            updated: runner.updated.value,
                            failed: runner.failed.value,
                        }) }}
                    </p>
                </div>
            </div>
            <Button v-if="runner.failuresUrl.value" variant="outline" size="sm" as-child>
                <a :href="runner.failuresUrl.value">
                    <Download class="mr-2 size-4" aria-hidden="true" />
                    {{ t('transfer.import.failuresCsv') }}
                </a>
            </Button>
        </div>

        <Alert v-if="errorMessage" variant="destructive" aria-live="assertive" class="mt-4">
            <AlertCircle class="size-4" />
            <AlertDescription>{{ errorMessage }}</AlertDescription>
        </Alert>

        <template #footer>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                <div>
                    <Button v-if="step === 'map'" variant="outline" size="sm" @click="step = 'upload'">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />{{ t('common.back') }}
                    </Button>
                    <Button v-if="step === 'validate'" variant="outline" size="sm" @click="step = 'map'">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />{{ t('common.back') }}
                    </Button>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" size="sm" @click="close">{{ t('common.cancel') }}</Button>

                    <LoadingButton
                        v-if="step === 'upload'"
                        :loading="loading"
                        :disabled="!file"
                        size="sm"
                        @click="uploadAndPreview"
                    >
                        <Upload class="mr-2 size-4" aria-hidden="true" />{{ t('transfer.import.upload') }}
                    </LoadingButton>

                    <LoadingButton
                        v-if="step === 'map'"
                        :loading="loading"
                        :disabled="!requiredMapped"
                        size="sm"
                        @click="validateRows"
                    >
                        {{ t('transfer.import.validate') }}
                        <ArrowRight class="ml-2 size-4" aria-hidden="true" />
                    </LoadingButton>

                    <Button v-if="step === 'validate'" size="sm" :disabled="!commitEnabled" @click="startImport">
                        {{ t('transfer.import.commit') }}
                    </Button>

                    <LoadingButton
                        v-if="step === 'import' && runner.canResume.value"
                        :loading="runner.running.value"
                        size="sm"
                        @click="resumeImport"
                    >
                        {{ t('transfer.import.resume') }}
                    </LoadingButton>

                    <Button v-if="step === 'result'" size="sm" @click="finish">{{ t('common.close') }}</Button>
                </div>
            </div>
        </template>
    </Modal>
</template>
