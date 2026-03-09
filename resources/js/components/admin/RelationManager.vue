<script setup lang="ts">
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ActionModal from '@/components/ActionModal.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';
import type { PaginatedData } from '@/types';
import type { SchemaItem } from '@/composables/useFormSchema';
import type { BaseColumn } from '@/composables/useTableSchema';
import type { BaseFilter } from '@/composables/useTableFilters';
import type { Action, ActionGroup, BulkAction } from '@/composables/useTableActions';

const props = withDefaults(defineProps<{
    title: string;
    description?: string;
    columns: BaseColumn[];
    data: PaginatedData<any>;
    routeName: string;
    routeParams?: Record<string, any>;
    filters?: Record<string, string>;
    tableFilters?: BaseFilter[];
    actions?: (Action | ActionGroup)[];
    bulkActions?: BulkAction[];
    searchable?: boolean;
    searchPlaceholder?: string;
    queryPrefix?: string;
    // Create support
    createSchema?: SchemaItem[];
    createRouteName?: string;
    createRouteParams?: Record<string, any>;
    createDefaults?: Record<string, any>;
    createLabel?: string;
}>(), {
    searchable: true,
    searchPlaceholder: 'Search...',
    createLabel: 'Create',
});

const createModalOpen = ref(false);

const createModalConfig = props.createSchema && props.createRouteName ? {
    title: `Create ${props.title}`,
    schema: props.createSchema,
    routeName: props.createRouteName,
    routeParams: props.createRouteParams,
    method: 'post' as const,
    defaults: props.createDefaults || {},
    submitLabel: props.createLabel,
} : null;
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>{{ title }}</CardTitle>
                    <CardDescription v-if="description">{{ description }}</CardDescription>
                </div>
                <Button
                    v-if="createSchema && createRouteName"
                    size="sm"
                    @click="createModalOpen = true"
                >
                    <Plus class="mr-2 size-4" />
                    {{ createLabel }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <DataTable
                :columns="columns"
                :data="data"
                :filters="filters"
                :route-name="routeName"
                :route-params="routeParams"
                :searchable="searchable"
                :search-placeholder="searchPlaceholder"
                :query-prefix="queryPrefix"
                :table-filters="tableFilters"
                :actions="actions"
                :bulk-actions="bulkActions"
            />
        </CardContent>
    </Card>

    <ActionModal
        v-if="createModalConfig"
        v-model:open="createModalOpen"
        :config="createModalConfig"
    />
</template>
