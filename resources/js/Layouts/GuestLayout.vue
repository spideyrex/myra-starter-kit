<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/composables/useFlashToasts';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
useFlashToasts();
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left branding panel (hidden on mobile) -->
        <div class="hidden lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center bg-primary px-8 text-primary-foreground">
            <div class="max-w-md space-y-6 text-center">
                <div class="flex items-center justify-center gap-3 text-3xl font-bold">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-primary-foreground text-primary">
                        A
                    </div>
                    {{ (page.props.siteSettings as any)?.site_name || 'Admin' }}
                </div>
                <blockquote class="mt-8 border-l-2 border-primary-foreground/30 pl-4 text-left text-lg italic text-primary-foreground/80">
                    "The best admin experience starts with a great foundation."
                </blockquote>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="flex w-full flex-col items-center justify-center px-4 py-8 lg:w-1/2">
            <!-- Mobile logo (visible on small screens only) -->
            <div class="mb-6 lg:hidden">
                <Link :href="route('login')">
                    <div class="flex items-center gap-2 text-2xl font-bold text-foreground">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            A
                        </div>
                        {{ (page.props.siteSettings as any)?.site_name || 'Admin' }}
                    </div>
                </Link>
            </div>

            <Card class="w-full max-w-md border-0 shadow-none lg:border lg:shadow-sm">
                <CardContent class="p-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>

    <Toaster />
</template>
