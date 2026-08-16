<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Info, ShieldCheck, ShieldAlert, Lock } from 'lucide-vue-next';

interface TenantModelStatus {
    class: string;
    usesTrait: boolean;
    hasColumn: boolean;
    nullRows: number;
    scoped: boolean;
}

interface TenancyStatus {
    enabled: boolean;
    column: string;
    nullRows: string;
    models: TenantModelStatus[];
    currentTeam: { id: number; name: string } | null;
}

const props = defineProps<{ status: TenancyStatus }>();

const { t } = useI18n();

const enabled = computed(() => props.status.enabled);
const nullRowsLabel = computed(() =>
    props.status.nullRows === 'shared' ? t('tenancy.nullRowsShared') : t('tenancy.nullRowsStrict'),
);
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('navGroups.demo'), href: route('admin.demo.index') }, { label: t('tenancy.title') }]">
        <Head :title="t('tenancy.title')" />

        <PageHeader :title="t('tenancy.title')" :description="t('tenancy.subtitle')">
            <template #badge>
                <Badge :variant="enabled ? 'default' : 'secondary'" data-testid="tenancy-status">
                    {{ enabled ? t('tenancy.statusEnabled') : t('tenancy.statusDisabled') }}
                </Badge>
            </template>
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                        {{ t('tenancy.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Alert data-testid="tenancy-banner">
                <component :is="enabled ? ShieldAlert : ShieldCheck" class="size-4" aria-hidden="true" />
                <AlertTitle>
                    {{ enabled ? t('tenancy.statusEnabled') : t('tenancy.statusDisabled') }}
                </AlertTitle>
                <AlertDescription>
                    {{ enabled ? t('tenancy.bannerEnabled') : t('tenancy.bannerDisabled') }}
                </AlertDescription>
            </Alert>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-sm text-muted-foreground">{{ t('tenancy.column') }}</p>
                        <p class="mt-1 font-mono text-sm font-semibold">{{ status.column }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-sm text-muted-foreground">{{ t('tenancy.nullRows') }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ nullRowsLabel }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-sm text-muted-foreground">{{ t('tenancy.currentTeam') }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ status.currentTeam?.name ?? t('tenancy.noTeam') }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('tenancy.modelsTitle') }}</CardTitle>
                    <CardDescription>{{ t('tenancy.modelsDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <p v-if="status.models.length === 0" class="text-sm text-muted-foreground">
                        {{ t('tenancy.modelsEmpty') }}
                    </p>
                    <div v-else class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ t('tenancy.colModel') }}</TableHead>
                                    <TableHead>{{ t('tenancy.colTrait') }}</TableHead>
                                    <TableHead>{{ t('tenancy.colColumn') }}</TableHead>
                                    <TableHead>{{ t('tenancy.colNullRows') }}</TableHead>
                                    <TableHead>{{ t('tenancy.colScoped') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="model in status.models" :key="model.class" data-testid="tenancy-model-row">
                                    <TableCell class="font-mono text-xs">{{ model.class }}</TableCell>
                                    <TableCell>{{ model.usesTrait ? t('tenancy.yes') : t('tenancy.no') }}</TableCell>
                                    <TableCell>{{ model.hasColumn ? t('tenancy.yes') : t('tenancy.no') }}</TableCell>
                                    <TableCell>{{ model.nullRows }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="model.scoped ? 'default' : 'outline'" data-testid="tenancy-model-scoped">
                                            {{ model.scoped ? t('tenancy.yes') : t('tenancy.no') }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Info class="size-4" aria-hidden="true" />
                            {{ t('tenancy.honestTitle') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">{{ t('tenancy.honestBody') }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Lock class="size-4" aria-hidden="true" />
                            {{ t('tenancy.failClosedTitle') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">{{ t('tenancy.failClosedBody') }}</p>
                    </CardContent>
                </Card>
            </div>

            <Alert>
                <Info class="size-4" aria-hidden="true" />
                <AlertTitle>{{ t('tenancy.auditTitle') }}</AlertTitle>
                <AlertDescription>{{ t('tenancy.auditBody') }}</AlertDescription>
            </Alert>
        </div>
    </AuthenticatedLayout>
</template>
