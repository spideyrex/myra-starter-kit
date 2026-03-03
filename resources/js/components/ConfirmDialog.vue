<script setup lang="ts">
import { nextTick } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { AlertTriangle } from 'lucide-vue-next';

const {
    isOpen,
    title,
    description,
    confirmText,
    cancelText,
    variant,
    handleConfirm,
    handleCancel,
} = useConfirm();

function onOpenChange(v: boolean) {
    if (!v) {
        // Defer cancel to nextTick so that handleConfirm (from AlertDialogAction @click)
        // has a chance to resolve the promise first. If the dialog was closed via Escape
        // or overlay click (no button), handleCancel will still run on nextTick.
        nextTick(() => handleCancel());
    }
}
</script>

<template>
    <AlertDialog :open="isOpen" @update:open="onOpenChange">
        <AlertDialogContent>
            <AlertDialogHeader class="text-center sm:text-center">
                <div v-if="variant === 'destructive'" class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-destructive/10">
                    <AlertTriangle class="size-6 text-destructive" />
                </div>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description">{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="handleCancel">{{ cancelText }}</AlertDialogCancel>
                <AlertDialogAction
                    :class="variant === 'destructive' ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : ''"
                    @click="handleConfirm"
                >
                    {{ confirmText }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
