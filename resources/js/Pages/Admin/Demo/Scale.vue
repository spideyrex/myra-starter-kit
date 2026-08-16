<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import CodeBlock from '@/components/admin/CodeBlock.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TextColumn, BadgeColumn, DateColumn } from '@/composables/useTableSchema';
import { ArrowLeft } from 'lucide-vue-next';
import { isCursor } from '@/lib/pagination';
import type { CursorPaginatedData, PaginatedData, TableData } from '@/types';

const props = defineProps<{
    rows: TableData<any>;
    filters: Record<string, string>;
    total: number;
    perf: {
        virtualizeAbove: number;
        rowHeight: number;
        viewportHeight: number;
        overscan: number;
        stableSort: boolean;
    };
}>();

const { t } = useI18n();

const cursorMode = computed(() => isCursor(props.rows));
const lengthAware = computed<PaginatedData<any> | null>(() =>
    cursorMode.value ? null : (props.rows as PaginatedData<any>),
);
const cursor = computed<CursorPaginatedData<any> | null>(() =>
    cursorMode.value ? (props.rows as CursorPaginatedData<any>) : null,
);

const routeName = computed(() => (cursorMode.value ? 'admin.demo.scale-cursor' : 'admin.demo.scale'));

/**
 * The length-aware table here is always virtualised — that is what the page is
 * for. `perf.virtualizeAbove` is the framework's suggested threshold and is
 * reported in the banner, not applied to this demo.
 */
const virtualized = computed(() => !cursorMode.value);

const columns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('name').label(t('scale.name')).sortable(),
    TextColumn.make('email').label(t('scale.email')),
    BadgeColumn.make('status').label(t('scale.status')).colors({
        active: 'default',
        pending: 'secondary',
        suspended: 'destructive',
        archived: 'outline',
    }),
    TextColumn.make('amount').label(t('scale.amount')).alignEnd().sortable(),
    DateColumn.make('created_at').label(t('scale.createdAt')).format('datetime').sortable(),
];

const seedCommand = 'php artisan migrate\nphp artisan myra:scale-seed 100000 --fresh';
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: $t('navGroups.demo'), href: route('admin.demo.index') },
        { label: $t('scale.title') },
    ]">
        <Head :title="$t('scale.title')" />

        <PageHeader :title="$t('scale.title')" :description="$t('scale.description')">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        {{ $t('scale.backToDemos') }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <!-- Status banner -->
            <Card>
                <CardContent class="flex flex-wrap items-center gap-x-6 gap-y-2 py-4 text-sm">
                    <span class="font-medium">{{ $t('scale.rowsTotal', { n: total }) }}</span>
                    <span class="text-muted-foreground">
                        {{ $t('scale.mode') }}:
                        <Badge variant="secondary">{{ cursorMode ? $t('scale.modeCursor') : $t('scale.modeLengthAware') }}</Badge>
                    </span>
                    <span class="text-muted-foreground">
                        {{ $t('scale.virtualized') }}:
                        <Badge :variant="virtualized ? 'default' : 'outline'">{{ virtualized ? $t('scale.on') : $t('scale.off') }}</Badge>
                    </span>
                    <span class="text-muted-foreground">{{ $t('scale.rowHeight') }}: {{ perf.rowHeight }}px</span>
                    <span class="text-muted-foreground">{{ $t('scale.viewportHeight') }}: {{ perf.viewportHeight }}px</span>
                    <span class="text-muted-foreground">{{ $t('scale.overscan') }}: {{ perf.overscan }}</span>
                    <span class="text-muted-foreground">
                        {{ $t('scale.stableSort') }}:
                        <Badge :variant="perf.stableSort ? 'default' : 'outline'">{{ perf.stableSort ? $t('scale.on') : $t('scale.off') }}</Badge>
                    </span>
                </CardContent>
            </Card>

            <!-- Empty state: the operator has to seed on purpose -->
            <Card v-if="total === 0">
                <CardHeader>
                    <CardTitle>{{ $t('scale.empty') }}</CardTitle>
                    <CardDescription>{{ $t('scale.seedHint') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <CodeBlock :value="seedCommand" code-language="bash" :code-line-numbers="false" />
                </CardContent>
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <!-- Length-aware + virtualised -->
                <Card v-if="lengthAware">
                    <CardHeader>
                        <CardTitle>{{ $t('scale.lengthAwareTitle') }}</CardTitle>
                        <CardDescription>{{ $t('scale.lengthAwareDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            :columns="columns"
                            :data="lengthAware"
                            :filters="filters"
                            :route-name="routeName"
                            table-key="admin.demo.scale"
                            :virtualized="virtualized"
                            :row-height="perf.rowHeight"
                            :viewport-height="perf.viewportHeight"
                            :overscan="perf.overscan"
                            sticky-header
                        />
                    </CardContent>
                </Card>

                <!-- Cursor -->
                <Card v-if="cursor">
                    <CardHeader>
                        <CardTitle>{{ $t('scale.cursorTitle') }}</CardTitle>
                        <CardDescription>{{ $t('scale.cursorDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            :columns="columns"
                            :data="cursor"
                            :filters="filters"
                            route-name="admin.demo.scale-cursor"
                            table-key="admin.demo.scale-cursor"
                            sticky-header
                        />
                    </CardContent>
                </Card>

                <!-- The other mode is always one click away -->
                <Card v-if="!cursor">
                    <CardHeader>
                        <CardTitle>{{ $t('scale.cursorTitle') }}</CardTitle>
                        <CardDescription>{{ $t('scale.cursorDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" as-child>
                            <Link :href="route('admin.demo.scale-cursor')">{{ $t('scale.modeCursor') }}</Link>
                        </Button>
                    </CardContent>
                </Card>
                <Card v-if="!lengthAware">
                    <CardHeader>
                        <CardTitle>{{ $t('scale.lengthAwareTitle') }}</CardTitle>
                        <CardDescription>{{ $t('scale.lengthAwareDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" as-child>
                            <Link :href="route('admin.demo.scale')">{{ $t('scale.modeLengthAware') }}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
