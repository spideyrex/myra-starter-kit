<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FileUpload from '@/components/FileUpload.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import Modal from '@/components/Modal.vue';
import EmptyState from '@/components/EmptyState.vue';
import { useConfirm } from '@/composables/useConfirm';
import type { PaginatedData } from '@/types';
import { ref, computed } from 'vue';
import { Upload, Trash2, Image as ImageIcon } from 'lucide-vue-next';

const props = defineProps<{
    media: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { confirm } = useConfirm();
const showUpload = ref(false);
const uploadKey = ref(0);
const uploadForm = useForm({ files: [] as File[] });
const selectedIds = ref<number[]>([]);

const allSelected = computed(() =>
    props.media.data.length > 0 && selectedIds.value.length === props.media.data.length,
);

function toggleSelect(id: number) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
}

function toggleAll(checked: boolean | 'indeterminate') {
    selectedIds.value = checked === true ? props.media.data.map((item: any) => item.id) : [];
}

function handleFiles(files: File[]) {
    uploadForm.files = files;
}

function upload() {
    uploadForm.post(route('admin.media.store'), {
        forceFormData: true,
        onSuccess: () => {
            showUpload.value = false;
            uploadForm.reset();
            uploadKey.value++;
        },
    });
}

async function deleteMedia(id: number) {
    const confirmed = await confirm({ title: 'Delete Media', description: 'This file will be permanently deleted.', variant: 'destructive', confirmText: 'Delete' });
    if (confirmed) router.delete(route('admin.media.destroy', id));
}

async function bulkDelete() {
    if (selectedIds.value.length === 0) return;
    const confirmed = await confirm({ title: 'Delete Files', description: `Are you sure you want to delete ${selectedIds.value.length} file(s)?`, variant: 'destructive', confirmText: 'Delete' });
    if (confirmed) {
        router.post(route('admin.media.bulk-action'), { ids: selectedIds.value }, {
            onSuccess: () => { selectedIds.value = []; },
        });
    }
}

function formatSize(bytes: number) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Content' }, { label: 'Media Manager' }]">
        <Head title="Media Manager" />
        <PageHeader title="Media Manager" description="Upload and manage your media files.">
            <template #actions>
                <template v-if="selectedIds.length > 0">
                    <Button variant="destructive" size="sm" @click="bulkDelete">
                        <Trash2 class="mr-2 size-4" />
                        Delete ({{ selectedIds.length }})
                    </Button>
                </template>
                <Button @click="showUpload = true"><Upload class="mr-2 size-4" />Upload</Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <EmptyState v-if="media.data.length === 0" title="No media files" description="Upload your first file to get started." :icon="ImageIcon">
                <template #action>
                    <Button @click="showUpload = true"><Upload class="mr-2 size-4" />Upload</Button>
                </template>
            </EmptyState>

            <template v-else>
                <div class="mb-3 flex items-center gap-3">
                    <Checkbox :model-value="allSelected" @update:model-value="toggleAll" />
                    <span class="text-sm text-muted-foreground">Select all</span>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    <Card v-for="item in media.data" :key="item.id" class="group overflow-hidden" :class="{ 'ring-2 ring-primary': selectedIds.includes(item.id) }">
                        <div class="relative aspect-square bg-muted">
                            <img v-if="item.mime_type?.startsWith('image')" :src="item.thumbnail" :alt="item.file_name" class="size-full object-cover" />
                            <div v-else class="flex size-full items-center justify-center">
                                <ImageIcon class="size-8 text-muted-foreground" />
                            </div>
                            <div class="absolute left-2 top-2">
                                <Checkbox :model-value="selectedIds.includes(item.id)" @update:model-value="toggleSelect(item.id)" class="bg-background" />
                            </div>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                <Button variant="destructive" size="sm" @click="deleteMedia(item.id)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <CardContent class="p-2">
                            <p class="truncate text-xs font-medium">{{ item.file_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatSize(item.size) }}</p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <div v-if="media.meta.last_page > 1" class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ media.meta.from }} to {{ media.meta.to }} of {{ media.meta.total }} files
                </p>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="link in media.meta.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
                        class="h-8 min-w-8 px-2 text-xs sm:px-3 sm:text-sm"
                        :disabled="!link.url || link.active"
                        @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                    >{{ link.label.replace(/&laquo;/g, '\u00AB').replace(/&raquo;/g, '\u00BB') }}</Button>
                </div>
            </div>
        </div>

        <Modal v-model:open="showUpload" title="Upload Files">
            <FileUpload :key="uploadKey" accept="image/*,application/pdf" :multiple="true" @files="handleFiles" />
            <template #footer>
                <LoadingButton @click="upload" :loading="uploadForm.processing" :disabled="uploadForm.files.length === 0">
                    <Upload class="mr-2 size-4" />Upload {{ uploadForm.files.length }} file(s)
                </LoadingButton>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
