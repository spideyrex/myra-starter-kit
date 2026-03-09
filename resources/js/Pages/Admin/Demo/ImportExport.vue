<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import ImportModal from '@/components/ImportModal.vue';
import { Button } from '@/components/ui/button';
import { TextColumn, DateColumn } from '@/composables/useTableSchema';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Download, Upload, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    contacts: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const showImport = ref(false);

const columns = [
    TextColumn.make('name').label('Name').sortable().grow(),
    TextColumn.make('email').sortable(),
    TextColumn.make('phone').label('Phone'),
    TextColumn.make('company').sortable(),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Contacts', 'Are you sure you want to delete the selected contacts?')
        .icon(Trash2),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Import & Export' }]">
        <Head title="Import & Export Demo" />

        <PageHeader title="Import & Export" description="CSV import with column mapping and preview, plus streaming CSV export.">
            <template #actions>
                <Button variant="outline" as="a" :href="route('admin.demo.export-csv')">
                    <Download class="mr-2 size-4" />
                    Export CSV
                </Button>
                <Button variant="outline" @click="showImport = true">
                    <Upload class="mr-2 size-4" />
                    Import CSV
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="contacts"
                :filters="filters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.demo.import-export"
                search-placeholder="Search contacts..."
            />
        </div>

        <ImportModal
            v-model:open="showImport"
            title="Import Contacts"
            :preview-route="route('admin.demo.import-preview')"
            :execute-route="route('admin.demo.import-execute')"
            resource="contacts"
            :expected-columns="['name', 'email', 'phone', 'company']"
        />
    </AuthenticatedLayout>
</template>
