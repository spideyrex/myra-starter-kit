<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Server, HeartPulse } from 'lucide-vue-next';

defineProps<{
    systemInfo: Record<string, any>;
    healthChecks: Array<{ name: string; status: string; message: string }>;
}>();
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'System Health' }]">
        <Head title="System Health" />
        <PageHeader title="System Health" description="Monitor your application's health and system information." />

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><Server class="size-4" />System Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="(value, key) in systemInfo" :key="key" class="space-y-1">
                            <p class="text-xs uppercase text-muted-foreground">{{ String(key).replace(/_/g, ' ') }}</p>
                            <p class="text-sm font-medium">{{ String(value) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><HeartPulse class="size-4" />Health Checks</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="healthChecks.length === 0" class="text-sm text-muted-foreground">No health checks configured.</div>
                    <div v-else class="space-y-3">
                        <div v-for="check in healthChecks" :key="check.name" class="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p class="text-sm font-medium">{{ check.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ check.message }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="relative flex size-2.5">
                                    <span
                                        class="absolute inline-flex size-full rounded-full opacity-75"
                                        :class="check.status === 'ok' ? 'bg-success animate-ping' : 'bg-destructive animate-ping'"
                                    />
                                    <span
                                        class="relative inline-flex size-2.5 rounded-full"
                                        :class="check.status === 'ok' ? 'bg-success' : 'bg-destructive'"
                                    />
                                </span>
                                <Badge :variant="check.status === 'ok' ? 'default' : 'destructive'">{{ check.status }}</Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
