<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    RotateCw,
    RotateCcw,
    FlipHorizontal2,
    FlipVertical2,
    ZoomIn,
    ZoomOut,
    RefreshCw,
} from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    open: boolean;
    file: File | null;
    aspectRatio?: number;
    outputType?: string;
    outputQuality?: number;
}>(), {
    outputType: 'image/jpeg',
    outputQuality: 0.9,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', file: File, dataUrl: string): void;
    (e: 'cancel'): void;
}>();

const imageRef = ref<HTMLImageElement | null>(null);
let cropper: Cropper | null = null;
let objectUrl: string | null = null;

watch(() => props.open, async (isOpen) => {
    if (isOpen && props.file) {
        await nextTick();
        await nextTick(); // double tick to ensure dialog DOM is rendered
        initCropper();
    } else {
        destroyCropper();
    }
});

function initCropper() {
    destroyCropper();
    if (!props.file || !imageRef.value) return;

    objectUrl = URL.createObjectURL(props.file);
    imageRef.value.src = objectUrl;

    cropper = new Cropper(imageRef.value, {
        aspectRatio: props.aspectRatio || NaN,
        viewMode: 1,
        autoCropArea: 0.9,
        responsive: true,
        restore: false,
        guides: true,
        center: true,
        highlight: true,
        background: true,
    });
}

function destroyCropper() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}

onBeforeUnmount(destroyCropper);

function rotateRight() { cropper?.rotate(90); }
function rotateLeft() { cropper?.rotate(-90); }
function flipH() {
    if (!cropper) return;
    const data = cropper.getData();
    cropper.scaleX(data.scaleX === -1 ? 1 : -1);
}
function flipV() {
    if (!cropper) return;
    const data = cropper.getData();
    cropper.scaleY(data.scaleY === -1 ? 1 : -1);
}
function zoomIn() { cropper?.zoom(0.1); }
function zoomOut() { cropper?.zoom(-0.1); }
function reset() { cropper?.reset(); }

function save() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas();
    const dataUrl = canvas.toDataURL(props.outputType, props.outputQuality);
    canvas.toBlob(
        (blob: Blob | null) => {
            if (!blob) return;
            const ext = props.outputType === 'image/png' ? '.png' : '.jpg';
            const name = (props.file?.name || 'cropped').replace(/\.[^.]+$/, '') + ext;
            const file = new File([blob], name, { type: props.outputType });
            emit('save', file, dataUrl);
            emit('update:open', false);
        },
        props.outputType,
        props.outputQuality,
    );
}

function cancel() {
    emit('cancel');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Edit Image</DialogTitle>
                <DialogDescription>Crop, rotate, and flip your image before uploading.</DialogDescription>
            </DialogHeader>

            <div class="overflow-hidden rounded-md bg-muted" style="max-height: 400px;">
                <img ref="imageRef" class="block max-w-full" alt="Image to crop" />
            </div>

            <div class="flex flex-wrap items-center justify-center gap-1">
                <Button variant="outline" size="icon" title="Rotate Left" @click="rotateLeft">
                    <RotateCcw class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Rotate Right" @click="rotateRight">
                    <RotateCw class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Flip Horizontal" @click="flipH">
                    <FlipHorizontal2 class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Flip Vertical" @click="flipV">
                    <FlipVertical2 class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Zoom In" @click="zoomIn">
                    <ZoomIn class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Zoom Out" @click="zoomOut">
                    <ZoomOut class="size-4" />
                </Button>
                <Button variant="outline" size="icon" title="Reset" @click="reset">
                    <RefreshCw class="size-4" />
                </Button>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="cancel">Cancel</Button>
                <Button @click="save">Apply Crop</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
