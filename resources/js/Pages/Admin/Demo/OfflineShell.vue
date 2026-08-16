<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Check, ShieldCheck, X } from 'lucide-vue-next';
import { isCacheableRequest, unregisterAppShell } from '@/pwa/register';
import { useServiceWorker } from '@/composables/useServiceWorker';

defineProps<{ pwaEnabled: boolean }>();

const { t } = useI18n();
const sw = useServiceWorker();

interface Sample {
    label: string;
    method: string;
    path: string;
    headers?: Record<string, string>;
    origin?: string;
}

const samples: Sample[] = [
    { label: 'GET /build/app-abc123.js', method: 'GET', path: '/build/app-abc123.js' },
    { label: 'GET /offline.html', method: 'GET', path: '/offline.html' },
    { label: 'GET /manifest.webmanifest', method: 'GET', path: '/manifest.webmanifest' },
    { label: 'GET /favicon.ico', method: 'GET', path: '/favicon.ico' },
    { label: 'GET /admin/dashboard', method: 'GET', path: '/admin/dashboard' },
    { label: 'GET /admin/users?page=2', method: 'GET', path: '/admin/users?page=2' },
    { label: 'GET /admin/dashboard (X-Inertia)', method: 'GET', path: '/admin/dashboard', headers: { 'X-Inertia': 'true' } },
    { label: 'POST /admin/dashboard/widgets/data', method: 'POST', path: '/admin/dashboard/widgets/data' },
    { label: 'GET /admin/reports/users/data (JSON)', method: 'GET', path: '/admin/reports/users/data', headers: { Accept: 'application/json' } },
    { label: 'POST /admin/ai/assist (SSE)', method: 'POST', path: '/admin/ai/assist', headers: { Accept: 'text/event-stream' } },
    { label: 'GET /admin/search (XHR)', method: 'GET', path: '/admin/search', headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    { label: 'GET https://cdn.example.com/a.js', method: 'GET', path: '/a.js', origin: 'https://cdn.example.com' },
];

const decisions = computed(() => samples.map((sample) => {
    const origin = sample.origin ?? window.location.origin;

    return {
        ...sample,
        cacheable: isCacheableRequest({
            method: sample.method,
            headers: new Headers(sample.headers ?? {}),
            url: `${origin}${sample.path}`,
        }),
    };
}));

const purged = ref(false);

async function purge() {
    await sw.purge();
    purged.value = true;
}

async function unregister() {
    // Never touches firebase-messaging-sw.js.
    await unregisterAppShell();
    purged.value = true;
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.demo'), href: route('admin.demo.index') }, { label: t('assistant.offline.title') }]">
        <Head :title="t('assistant.offline.title')" />

        <PageHeader :title="t('assistant.offline.title')" :description="t('assistant.offline.description')">
            <template #badge>
                <Badge :variant="pwaEnabled ? 'default' : 'secondary'" data-testid="pwa-status">
                    {{ pwaEnabled ? t('assistant.enabled') : t('assistant.disabled') }}
                </Badge>
            </template>
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Alert>
                <ShieldCheck class="size-4" aria-hidden="true" />
                <AlertTitle>{{ t('assistant.pwa.allowListTitle') }}</AlertTitle>
                <AlertDescription>{{ t('assistant.pwa.allowListDescription') }}</AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('assistant.pwa.decisionTable') }}</CardTitle>
                    <CardDescription>{{ t('assistant.pwa.decisionHint') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ t('assistant.pwa.request') }}</TableHead>
                                    <TableHead class="w-32">{{ t('assistant.pwa.cached') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in decisions" :key="row.label" :data-testid="`sw-row-${row.label}`">
                                    <TableCell class="font-mono text-xs">{{ row.label }}</TableCell>
                                    <TableCell>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-sm"
                                            :class="row.cacheable ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive'"
                                        >
                                            <component :is="row.cacheable ? Check : X" class="size-4" aria-hidden="true" />
                                            {{ row.cacheable ? t('assistant.pwa.yes') : t('assistant.pwa.no') }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('assistant.pwa.registration') }}</CardTitle>
                    <CardDescription>
                        {{ sw.registered.value ? t('assistant.pwa.registered') : t('assistant.pwa.notRegistered') }}
                        · {{ sw.offline.value ? t('assistant.offline.banner') : t('assistant.pwa.online') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" @click="purge">{{ t('assistant.pwa.purge') }}</Button>
                    <Button type="button" variant="outline" @click="unregister">{{ t('assistant.pwa.unregister') }}</Button>
                    <Button
                        v-if="sw.updateAvailable.value"
                        type="button"
                        @click="sw.applyUpdate()"
                    >
                        {{ t('assistant.pwa.updateAvailable') }}
                    </Button>
                    <p v-if="purged" role="status" class="self-center text-sm text-muted-foreground">
                        {{ t('assistant.pwa.purged') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
