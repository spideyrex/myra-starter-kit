<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FileQuestion, ArrowLeft } from 'lucide-vue-next';
</script>

<template>
    <Head title="404 - Not Found" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-background">
        <div class="animate-fade-in-up flex flex-col items-center text-center">
            <div class="rounded-full bg-muted p-6">
                <FileQuestion class="size-12 text-muted-foreground" />
            </div>
            <h1 class="mt-6 text-6xl font-bold">404</h1>
            <h2 class="mt-2 text-xl font-semibold">Page Not Found</h2>
            <p class="mt-2 max-w-sm text-muted-foreground">The page you're looking for doesn't exist or has been moved.</p>
            <Button as-child class="mt-6">
                <Link :href="route('dashboard')">
                    <ArrowLeft class="mr-2 size-4" />
                    Back to Dashboard
                </Link>
            </Button>
        </div>
    </div>
</template>
