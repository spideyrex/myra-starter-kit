<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, LayoutGrid, ShieldCheck, UserRound } from 'lucide-vue-next';

interface Starter {
    role: string;
    visible: string[];
    hidden: string[];
}

defineProps<{ starters: Starter[] }>();

const { t } = useI18n();

const ladder = ['personal', 'role', 'none'] as const;
const steps = ['key', 'guest', 'personal', 'role', 'none'] as const;
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('navGroups.demo'), href: route('admin.demo.index') },
            { label: t('demos.roleDashboards.title') },
        ]"
    >
        <Head :title="t('demos.roleDashboards.title')" />

        <PageHeader :title="t('demos.roleDashboards.title')" :description="t('demos.roleDashboards.description')">
            <template #badge>
                <Badge variant="secondary">{{ t('gallery.demos.roleDashboards.badge') }}</Badge>
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
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('demos.roleDashboards.ladder.title') }}</CardTitle>
                    <CardDescription>{{ t('demos.roleDashboards.ladder.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <ol class="space-y-3">
                        <li
                            v-for="(rung, index) in ladder"
                            :key="rung"
                            class="flex gap-3 rounded-lg border border-border bg-muted/40 p-4"
                            :data-testid="`ladder-${rung}`"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                                aria-hidden="true"
                            >
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 space-y-1">
                                <p class="flex items-center gap-2 text-sm font-semibold">
                                    <component
                                        :is="rung === 'personal' ? UserRound : LayoutGrid"
                                        v-if="rung !== 'none'"
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {{ t(`demos.roleDashboards.ladder.${rung}.label`) }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ t(`demos.roleDashboards.ladder.${rung}.body`) }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('demos.roleDashboards.chain.title') }}</CardTitle>
                    <CardDescription>{{ t('demos.roleDashboards.chain.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableCaption class="sr-only">{{ t('demos.roleDashboards.chain.caption') }}</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col" class="w-64">{{ t('demos.roleDashboards.chain.step') }}</TableHead>
                                    <TableHead scope="col">{{ t('demos.roleDashboards.chain.result') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="step in steps" :key="step" :data-testid="`chain-${step}`">
                                    <TableCell class="font-medium">{{ t(`demos.roleDashboards.chain.steps.${step}.label`) }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ t(`demos.roleDashboards.chain.steps.${step}.result`) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('demos.roleDashboards.priority.title') }}</CardTitle>
                    <CardDescription>{{ t('demos.roleDashboards.priority.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-2 text-sm text-muted-foreground">
                    <p>{{ t('demos.roleDashboards.priority.fallThrough') }}</p>
                    <p>{{ t('demos.roleDashboards.priority.tie') }}</p>
                    <p>{{ t('demos.roleDashboards.priority.revocation') }}</p>
                </CardContent>
            </Card>

            <Alert>
                <ShieldCheck class="size-4" aria-hidden="true" />
                <AlertTitle>{{ t('demos.roleDashboards.security.title') }}</AlertTitle>
                <AlertDescription>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>{{ t('demos.roleDashboards.security.instances') }}</li>
                        <li>{{ t('demos.roleDashboards.security.entries') }}</li>
                    </ul>
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('demos.roleDashboards.starters.title') }}</CardTitle>
                    <CardDescription>{{ t('demos.roleDashboards.starters.description') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableCaption class="sr-only">{{ t('demos.roleDashboards.starters.caption') }}</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col" class="w-40">{{ t('demos.roleDashboards.starters.role') }}</TableHead>
                                    <TableHead scope="col">{{ t('demos.roleDashboards.starters.visible') }}</TableHead>
                                    <TableHead scope="col">{{ t('demos.roleDashboards.starters.hidden') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="starter in starters" :key="starter.role" :data-testid="`starter-${starter.role}`">
                                    <TableCell class="font-medium">{{ starter.role }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1.5">
                                            <Badge v-for="key in starter.visible" :key="key" variant="secondary" class="font-mono text-xs">
                                                {{ key }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="starter.hidden.length" class="flex flex-wrap gap-1.5">
                                            <Badge v-for="key in starter.hidden" :key="key" variant="outline" class="font-mono text-xs">
                                                {{ key }}
                                            </Badge>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">{{ t('demos.roleDashboards.starters.none') }}</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <p class="text-sm text-muted-foreground">{{ t('demos.roleDashboards.starters.note') }}</p>

                    <pre class="overflow-x-auto rounded-md border border-border bg-muted p-3 text-xs"><code>php artisan myra:role-dashboards:seed</code></pre>

                    <p class="text-sm text-muted-foreground">{{ t('demos.roleDashboards.starters.optIn') }}</p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
