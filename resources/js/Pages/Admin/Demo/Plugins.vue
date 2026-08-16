<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SimpleTable from '@/components/admin/SimpleTable.vue';
import CodeBlock from '@/components/admin/CodeBlock.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Puzzle, AlertTriangle } from 'lucide-vue-next';

interface PluginRow {
    id: string;
    class: string;
    permissions: number;
    reports: number;
    imports: number;
    routes: number;
    nav: number;
    migrations: number;
}

interface FailedRow {
    class: string;
    message: string;
}

const props = defineProps<{
    plugins: PluginRow[];
    failed: FailedRow[];
    strict: boolean;
}>();

const { t } = useI18n();

const columns = computed(() => [
    { key: 'id', label: t('plugins.columns.id') },
    { key: 'class', label: t('plugins.columns.class'), class: 'font-mono text-xs' },
    { key: 'permissions', label: t('plugins.columns.permissions') },
    { key: 'reports', label: t('plugins.columns.reports') },
    { key: 'imports', label: t('plugins.columns.imports') },
    { key: 'routes', label: t('plugins.columns.routes') },
    { key: 'nav', label: t('plugins.columns.nav') },
    { key: 'migrations', label: t('plugins.columns.migrations') },
]);

const sample = `<?php

namespace App\\Plugins\\Billing;

use App\\Admin\\Plugin\\Manifest;
use App\\Admin\\Plugin\\MyraPlugin;

class BillingPlugin extends MyraPlugin
{
    public static function id(): string
    {
        return 'billing';
    }

    public function manifest(Manifest $manifest): Manifest
    {
        return $manifest
            ->requires('2.4.0')
            ->permissions(['billing' => ['view', 'edit']])
            ->routes(fn () => Route::get('/billing', InvoicesController::class)
                ->middleware('permission:billing.view')->name('billing.index'));
    }
}
`;
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: t('nav.featureDemos'), href: route('admin.demo.index') },
        { label: t('plugins.title') },
    ]">
        <Head :title="t('plugins.title')" />

        <PageHeader :title="t('plugins.title')" :description="t('plugins.description')">
            <template #badge>
                <Badge :variant="props.strict ? 'default' : 'secondary'">
                    {{ props.strict ? t('plugins.strictOn') : t('plugins.strictOff') }}
                </Badge>
            </template>
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        {{ t('plugins.back') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="mt-6" aria-labelledby="myra-plugins-loaded">
            <h2 id="myra-plugins-loaded" class="flex items-center gap-2 text-lg font-semibold">
                <Puzzle class="size-4" aria-hidden="true" />
                {{ t('plugins.loaded') }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ t('plugins.loadedDescription') }}</p>

            <SimpleTable
                :columns="columns"
                :items="props.plugins"
                row-key="id"
                :empty-title="t('plugins.empty')"
                :empty-description="t('plugins.emptyDescription')"
                :empty-icon="Puzzle"
            />
        </section>

        <div class="mt-4 space-y-4">
            <Card v-if="props.failed.length > 0" class="border-destructive/40">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="size-4" aria-hidden="true" />
                        {{ t('plugins.failedTitle') }}
                    </CardTitle>
                    <CardDescription>{{ t('plugins.failedDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li
                            v-for="row in props.failed"
                            :key="row.class"
                            class="rounded-md border border-destructive/40 bg-destructive/5 p-3"
                        >
                            <p class="font-mono text-xs text-foreground">{{ row.class }}</p>
                            <p class="mt-1 text-sm text-destructive">{{ row.message }}</p>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('plugins.example.title') }}</CardTitle>
                    <CardDescription>{{ t('plugins.example.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <CodeBlock :value="sample" code-language="php" code-filename="BillingPlugin.php" />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
