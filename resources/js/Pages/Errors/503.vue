<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Wrench } from 'lucide-vue-next';
</script>

<template>
    <Head title="503 - Maintenance" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-background">
        <div class="animate-fade-in-up flex flex-col items-center text-center">
            <div class="rounded-full bg-muted p-6">
                <Wrench class="size-12 text-muted-foreground" />
            </div>
            <h1 class="mt-6 text-6xl font-bold">503</h1>
            <h2 class="mt-2 text-xl font-semibold">Under Maintenance</h2>
            <p class="mt-2 max-w-sm text-muted-foreground">We're currently performing maintenance. We'll be back shortly.</p>
        </div>
    </div>
</template>
