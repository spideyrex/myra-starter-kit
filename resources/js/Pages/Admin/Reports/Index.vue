<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { BarChart3 } from 'lucide-vue-next';
import type { ReportSchema } from '@/types/reports';

defineProps<{ reports: ReportSchema[] }>();

const { t } = useI18n();
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('reports.title') }]">
        <Head :title="t('reports.title')" />

        <PageHeader :title="t('reports.title')" :description="t('reports.subtitle')" />

        <EmptyState v-if="reports.length === 0" :title="t('reports.empty')" :icon="BarChart3" class="mt-8" />

        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="report in reports" :key="report.key">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <BarChart3 class="size-4 text-muted-foreground" aria-hidden="true" />
                        {{ t(report.titleKey) }}
                    </CardTitle>
                    <CardDescription>
                        {{ t(report.dimensions[0]?.labelKey ?? 'reports.toolbar.dimension') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="m in report.measures" :key="m.key" variant="secondary" class="font-normal">
                            {{ t(m.labelKey) }}
                        </Badge>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="route('admin.reports.show', report.key)">{{ t('reports.open') }}</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
