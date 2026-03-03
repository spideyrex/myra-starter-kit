<script setup lang="ts">
import { ref } from 'vue';
import { Upload, X, FileIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

const props = withDefaults(defineProps<{
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
}>(), {
    accept: 'image/*',
    multiple: false,
    maxSize: 10,
});

const emit = defineEmits<{
    files: [files: File[]];
}>();

const isDragging = ref(false);
const files = ref<File[]>([]);
const fileInput = ref<HTMLInputElement>();

function handleDrop(e: DragEvent) {
    isDragging.value = false;
    const droppedFiles = Array.from(e.dataTransfer?.files || []);
    addFiles(droppedFiles);
}

function handleFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    const selectedFiles = Array.from(target.files || []);
    addFiles(selectedFiles);
    target.value = '';
}

function addFiles(newFiles: File[]) {
    const maxBytes = props.maxSize * 1024 * 1024;
    const valid = newFiles.filter(f => f.size <= maxBytes);
    if (props.multiple) {
        files.value.push(...valid);
    } else {
        files.value = valid.slice(0, 1);
    }
    emit('files', files.value);
}

function removeFile(index: number) {
    files.value.splice(index, 1);
    emit('files', files.value);
}

function formatSize(bytes: number) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <div>
        <div
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors"
            :class="isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-primary/50'"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="handleDrop"
            @click="fileInput?.click()"
        >
            <Upload class="size-8 text-muted-foreground" />
            <p class="mt-2 text-sm text-muted-foreground">
                Drag & drop files here, or click to browse
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Max file size: {{ maxSize }}MB
            </p>
            <input
                ref="fileInput"
                type="file"
                :accept="accept"
                :multiple="multiple"
                class="hidden"
                @change="handleFileSelect"
            />
        </div>

        <div v-if="files.length" class="mt-4 space-y-2">
            <div
                v-for="(file, index) in files"
                :key="index"
                class="flex items-center gap-3 rounded-lg border p-3"
            >
                <FileIcon class="size-5 text-muted-foreground" />
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium">{{ file.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(file.size) }}</p>
                </div>
                <Button variant="ghost" size="sm" @click.stop="removeFile(index)">
                    <X class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
