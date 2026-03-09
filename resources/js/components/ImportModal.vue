<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import Modal from '@/components/Modal.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Upload, CheckCircle2, AlertCircle, ArrowRight, ArrowLeft } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    open: boolean;
    title: string;
    previewRoute: string;
    executeRoute: string;
    resource: string;
    expectedColumns: string[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

type Step = 'upload' | 'map' | 'preview' | 'result';

const step = ref<Step>('upload');
const file = ref<File | null>(null);
const loading = ref(false);
const csvHeaders = ref<string[]>([]);
const previewData = ref<any[]>([]);
const totalRows = ref(0);
const mapping = ref<Record<string, string>>({});
const importResult = ref<{ imported: number; errors: string[] } | null>(null);
const errorMessage = ref('');

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] || null;
}

async function uploadAndPreview() {
    if (!file.value) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file.value);
        formData.append('resource', props.resource);

        const response = await axios.post(props.previewRoute, formData);
        csvHeaders.value = response.data.headers;
        previewData.value = response.data.preview;
        totalRows.value = response.data.total_rows;

        // Auto-map matching columns
        for (const expected of props.expectedColumns) {
            const match = csvHeaders.value.find(h =>
                h.toLowerCase() === expected.toLowerCase() ||
                h.toLowerCase().replace(/[\s-]/g, '_') === expected.toLowerCase(),
            );
            if (match) {
                mapping.value[expected] = match;
            }
        }

        step.value = 'map';
    } catch (e: any) {
        errorMessage.value = e.response?.data?.error || e.response?.data?.message || 'Failed to parse CSV file.';
    } finally {
        loading.value = false;
    }
}

const mappedCount = computed(() =>
    Object.values(mapping.value).filter(v => v).length,
);

async function executeImport() {
    if (!file.value) return;
    loading.value = true;
    errorMessage.value = '';

    try {
        const formData = new FormData();
        formData.append('file', file.value);
        formData.append('resource', props.resource);
        formData.append('mapping', JSON.stringify(mapping.value));

        // Send mapping as flat form fields for Laravel
        for (const [key, val] of Object.entries(mapping.value)) {
            formData.append(`mapping[${key}]`, val);
        }

        const response = await axios.post(props.executeRoute, formData);
        importResult.value = response.data;
        step.value = 'result';
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'Import failed.';
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('update:open', false);
    // Reset on close
    setTimeout(() => {
        step.value = 'upload';
        file.value = null;
        csvHeaders.value = [];
        previewData.value = [];
        mapping.value = {};
        importResult.value = null;
        errorMessage.value = '';
    }, 200);
}

function finish() {
    close();
    router.reload();
}
</script>

<template>
    <Modal :open="open" :title="title" @update:open="close">
        <!-- Step 1: Upload -->
        <div v-if="step === 'upload'" class="space-y-4">
            <div>
                <Label>CSV File</Label>
                <input
                    type="file"
                    accept=".csv,.txt"
                    class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                    @change="handleFileChange"
                />
            </div>
            <p class="text-xs text-muted-foreground">
                Expected columns: {{ expectedColumns.join(', ') }}
            </p>
            <Alert v-if="errorMessage" variant="destructive">
                <AlertCircle class="size-4" />
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>
        </div>

        <!-- Step 2: Map Columns -->
        <div v-else-if="step === 'map'" class="space-y-4">
            <p class="text-sm text-muted-foreground">
                Found {{ totalRows }} row(s). Map CSV columns to fields:
            </p>
            <div class="space-y-3">
                <div v-for="col in expectedColumns" :key="col" class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                    <Label class="text-sm font-medium sm:w-28 sm:shrink-0 sm:text-right">{{ col }}</Label>
                    <ArrowLeft class="hidden size-4 text-muted-foreground shrink-0 sm:block" />
                    <Select
                        :model-value="mapping[col] || ''"
                        @update:model-value="(v: any) => mapping[col] = v"
                    >
                        <SelectTrigger class="w-full sm:flex-1">
                            <SelectValue placeholder="Select column..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">-- Skip --</SelectItem>
                            <SelectItem v-for="h in csvHeaders" :key="h" :value="h">{{ h }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ mappedCount }} of {{ expectedColumns.length }} columns mapped.
            </p>
            <Alert v-if="errorMessage" variant="destructive">
                <AlertCircle class="size-4" />
                <AlertDescription>{{ errorMessage }}</AlertDescription>
            </Alert>
        </div>

        <!-- Step 3: Preview -->
        <div v-else-if="step === 'preview'" class="space-y-4">
            <p class="text-sm text-muted-foreground">
                Preview of first {{ previewData.length }} rows ({{ totalRows }} total):
            </p>
            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th v-for="col in expectedColumns.filter(c => mapping[c])" :key="col" class="px-3 py-2 text-left font-medium">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in previewData" :key="i" class="border-t">
                            <td v-for="col in expectedColumns.filter(c => mapping[c])" :key="col" class="px-3 py-2">
                                {{ row[mapping[col]] ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Step 4: Result -->
        <div v-else-if="step === 'result' && importResult" class="space-y-4">
            <div class="flex items-center gap-3">
                <CheckCircle2 class="size-8 text-success" />
                <div>
                    <p class="font-medium">Import Complete</p>
                    <p class="text-sm text-muted-foreground">
                        {{ importResult.imported }} record(s) imported successfully.
                    </p>
                </div>
            </div>
            <div v-if="importResult.errors.length > 0" class="space-y-1">
                <p class="text-sm font-medium text-destructive">Errors ({{ importResult.errors.length }}):</p>
                <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-destructive">{{ err }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                <div>
                    <Button v-if="step === 'map'" variant="outline" size="sm" @click="step = 'upload'">
                        <ArrowLeft class="mr-2 size-4" />Back
                    </Button>
                    <Button v-if="step === 'preview'" variant="outline" size="sm" @click="step = 'map'">
                        <ArrowLeft class="mr-2 size-4" />Back
                    </Button>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" size="sm" @click="close">Cancel</Button>

                    <LoadingButton v-if="step === 'upload'" :loading="loading" :disabled="!file" size="sm" @click="uploadAndPreview">
                        <Upload class="mr-2 size-4" />Upload & Preview
                    </LoadingButton>

                    <Button v-if="step === 'map'" size="sm" @click="step = 'preview'">
                        Preview <ArrowRight class="ml-2 size-4" />
                    </Button>

                    <LoadingButton v-if="step === 'preview'" :loading="loading" size="sm" @click="executeImport">
                        Import {{ totalRows }} Row(s)
                    </LoadingButton>

                    <Button v-if="step === 'result'" size="sm" @click="finish">
                        Done
                    </Button>
                </div>
            </div>
        </template>
    </Modal>
</template>
