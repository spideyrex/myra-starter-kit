<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import FileUpload from '@/components/FileUpload.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Card, CardContent } from '@/components/ui/card';
import Modal from '@/components/Modal.vue';
import EmptyState from '@/components/EmptyState.vue';
import { useConfirm } from '@/composables/useConfirm';
import type { PaginatedData } from '@/types';
import { ref } from 'vue';
import { Upload, Trash2, Image as ImageIcon } from 'lucide-vue-next';

const props = defineProps<{
    media: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { confirm } = useConfirm();
const showUpload = ref(false);
const uploadKey = ref(0);
const uploadForm = useForm({ files: [] as File[] });

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
                <Button @click="showUpload = true"><Upload class="mr-2 size-4" />Upload</Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <EmptyState v-if="media.data.length === 0" title="No media files" description="Upload your first file to get started." :icon="ImageIcon">
                <template #action>
                    <Button @click="showUpload = true"><Upload class="mr-2 size-4" />Upload</Button>
                </template>
            </EmptyState>

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <Card v-for="item in media.data" :key="item.id" class="group overflow-hidden">
                    <div class="relative aspect-square bg-muted">
                        <img v-if="item.mime_type?.startsWith('image')" :src="item.thumbnail" :alt="item.file_name" class="size-full object-cover" />
                        <div v-else class="flex size-full items-center justify-center">
                            <ImageIcon class="size-8 text-muted-foreground" />
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

            <div v-if="media.meta.last_page > 1" class="mt-6 flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ media.meta.from }} to {{ media.meta.to }} of {{ media.meta.total }} files
                </p>
                <div class="flex gap-1">
                    <Button
                        v-for="link in media.meta.links"
                        :key="link.label"
                        variant="outline"
                        size="sm"
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
