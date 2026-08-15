<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TextColumn, DateColumn, BadgeColumn } from '@/composables/useTableSchema';
import {
    SelectFilter,
    TernaryFilter,
    Filter,
    DateRangeFilter,
    QueryBuilderFilter,
    TextConstraint,
    SelectConstraint,
    DateConstraint,
    RelationConstraint,
} from '@/composables/useTableFilters';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    records?: PaginatedData<any>;
    /** Legacy prop name, kept so a generated clone of this page still renders. */
    products?: PaginatedData<any>;
    filters: Record<string, string>;
    constraints?: { maxRules: number; maxDepth: number; fields: Array<Record<string, any>> };
}>();

const { t } = useI18n();

const rows = computed(() => props.records ?? props.products ?? ({ data: [], links: [], meta: {} } as any));

const columns = [
    TextColumn.make('name').label(t('filters.field.name')).sortable(),
    TextColumn.make('email').label(t('filters.field.email')).sortable(),
    TextColumn.make('phone').label(t('filters.field.phone')),
    BadgeColumn.make('status').label(t('filters.field.status')).colors({
        active: 'default',
        pending: 'secondary',
        suspended: 'outline',
    }),
    DateColumn.make('created_at').label(t('filters.field.createdAt')).sortable().format('date'),
];

const tableFilters = [
    SelectFilter.make('status')
        .label(t('filters.field.status'))
        .placeholder(t('common.filter'))
        .options({ active: t('status.active'), pending: t('status.pending'), suspended: t('status.inactive') }),

    TernaryFilter.make('verified')
        .label(t('filters.field.verifiedAt'))
        .trueLabel(t('status.enabled'))
        .falseLabel(t('status.disabled')),

    Filter.make('has_phone').label(t('filters.field.phone')),

    DateRangeFilter.make('created').label(t('filters.field.createdAt')),

    // Nested AND/OR groups. Every field and operator is re-checked server-side
    // against App\Admin\QueryBuilder\FieldSet — this declaration only shapes the UI.
    QueryBuilderFilter.make('query_builder')
        .label(t('filters.applyQuery'))
        .constraints([
            TextConstraint.make('name').labelKey('filters.field.name').contains(),
            TextConstraint.make('email').labelKey('filters.field.email').contains(),
            TextConstraint.make('phone').labelKey('filters.field.phone').nullable(),
            SelectConstraint.make('status').labelKey('filters.field.status').options({
                active: t('status.active'),
                pending: t('status.pending'),
                suspended: t('status.inactive'),
            }),
            DateConstraint.make('created_at').labelKey('filters.field.createdAt'),
            DateConstraint.make('email_verified_at').labelKey('filters.field.verifiedAt').nullable(),
            RelationConstraint.make('roles').labelKey('filters.field.roles').titleAttribute('name'),
        ])
        .maxRules(props.constraints?.maxRules ?? 25)
        .maxDepth(props.constraints?.maxDepth ?? 3)
        .deferred(),
];

/** The same one-liner adoption path, derived from the columns above. */
const derivedConstraintNames = QueryBuilderFilter.make('derived')
    .fromColumns(columns)
    .toSchema() as any;
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Advanced Filters' }]">
        <Head title="Advanced Filters" />

        <PageHeader
            title="Advanced Filters"
            description="Select, ternary, checkbox and date-range filters plus a nested AND/OR query builder compiled to SQL under a server-side field and operator whitelist."
        />

        <div class="mt-6 space-y-6">
            <DataTable
                :columns="columns"
                :data="rows"
                :filters="filters"
                :table-filters="tableFilters"
                route-name="admin.demo.advanced-filters"
                searchable
            />

            <Card>
                <CardHeader>
                    <CardTitle>Server-side whitelist</CardTitle>
                    <CardDescription>
                        The client constraint list carries no authority. Every submitted rule is re-checked
                        against this FieldSet; an unknown field, an operator the field does not allow, a value
                        outside a select's options, more than {{ constraints?.maxRules ?? 25 }} rules or nesting
                        deeper than {{ constraints?.maxDepth ?? 3 }} levels is a 422 — never a silently
                        unfiltered result set.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="field in constraints?.fields ?? []" :key="field.name" class="rounded-lg border p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ field.labelKey ? t(field.labelKey) : field.name }}</span>
                            <Badge variant="secondary">{{ field.type }}</Badge>
                            <code class="text-xs text-muted-foreground">{{ field.name }}</code>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Badge v-for="op in field.operators" :key="op" variant="outline" class="text-[11px] font-normal">
                                {{ t(`filters.op.${op}`) }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>fromColumns()</CardTitle>
                    <CardDescription>
                        One-line adoption: constraints derived from the columns already declared above.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="c in derivedConstraintNames.constraints" :key="c.name" variant="secondary" class="font-normal">
                            {{ c.label }} · {{ c.type }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
