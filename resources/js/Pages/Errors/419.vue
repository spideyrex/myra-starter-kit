<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Clock, ArrowLeft } from 'lucide-vue-next';

function refresh() {
    window.location.reload();
}
</script>

<template>
    <Head title="419 - Session Expired" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-background">
        <div class="animate-fade-in-up flex flex-col items-center text-center">
            <div class="rounded-full bg-muted p-6">
                <Clock class="size-12 text-muted-foreground" />
            </div>
            <h1 class="mt-6 text-6xl font-bold">419</h1>
            <h2 class="mt-2 text-xl font-semibold">Session Expired</h2>
            <p class="mt-2 max-w-sm text-muted-foreground">Your session has expired. Please refresh the page and try again.</p>
            <Button class="mt-6" @click="refresh">
                <ArrowLeft class="mr-2 size-4" />
                Refresh Page
            </Button>
        </div>
    </div>
</template>
