<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ShieldX, ArrowLeft } from 'lucide-vue-next';
</script>

<template>
    <Head title="403 - Forbidden" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-background">
        <div class="animate-fade-in-up flex flex-col items-center text-center">
            <div class="rounded-full bg-muted p-6">
                <ShieldX class="size-12 text-destructive" />
            </div>
            <h1 class="mt-6 text-6xl font-bold">403</h1>
            <h2 class="mt-2 text-xl font-semibold">Access Forbidden</h2>
            <p class="mt-2 max-w-sm text-muted-foreground">You don't have permission to access this page. Contact your administrator if you believe this is an error.</p>
            <Button as-child class="mt-6">
                <Link :href="route('dashboard')">
                    <ArrowLeft class="mr-2 size-4" />
                    Back to Dashboard
                </Link>
            </Button>
        </div>
    </div>
</template>
